<?php

namespace App\Console\Commands;

/***
Notes
- The PHP command now sends all credentials grouped by tenant/webhook to the Python script in one run, enabling the Python to enumerate users/sites once per group and partition work across all app credentials for that tenant. This mimics and extends the behavior in your “Developer Message” reference, and is suitable for 1500+ users/sites.
- The Python script renews existing subscriptions when possible, creates missing ones, and removes stale subscriptions that no longer belong to the app’s assigned partition, with retries on 429/5xx.
- Concurrency controls:
  - --batch option in the Laravel command controls per-app parallelism (default 10).
  - At the group level, up to 8 app-credentials are run in parallel; adjust in orchestrate_group if needed.
- If some groups must use different exclusion lists, add excluded_users/excluded_sites arrays in your DataConfig->m365_config_json and they will be merged for the group automatically.
- Per-credential cap (1500 by default)
  - The Python code computes the combined resource list (users’ OneDrive and all site-library drives), then assigns at most --limit resources to each credential. No credential gets more than the cap. If total exceeds capacity, it logs the overflow count, does not delete anything outside the assigned set for safety, and finishes successfully with a warning. Increase credentials or lower the limit to cover all resources.
  - You can adjust the cap with the Laravel option --limit=1500.

- Tenant-aware orchestration
  - The Laravel command groups configs by tenant_id + webhook_url + client_state.
  - The Python script enumerates users and sites with a token from the same tenant and filters credentials to that tenant. No cross-tenant mixing occurs. Credentials with a mismatched tenant_id are ignored (logged) within that group.

Notes
- Concurrency: --batch controls per-credential parallel renew/create workers (default 10). The script simultaneously processes up to 8 credentials per tenant; adjust inside orchestrate_group if needed.
- Exclusions: Add excluded_users and excluded_sites arrays to m365_config_json in DataConfig. Users can be excluded by id or userPrincipalName; sites can be excluded by site id or hostname.
- Safe deletion: Stale deletion runs only when the group covers all resources within capacity. If overflow exists, stale deletion is skipped automatically to avoid removing subscriptions that were not assigned due 
**/

use Illuminate\Console\Command;
use App\Models\DataConfig;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use App\Notifications\M365WebhookRenewalFailed;

class RenewM365Webhooks extends Command
{
    protected $signature = 'renew:m365-webhooks
        {--batch=10 : Per-credential worker concurrency for Graph calls}
        {--limit=1500 : Max resources (users + site drives) per credential}
        {--python=python3 : Python interpreter}
        {--script=/home/cybersecai/htdocs/www.cybersecai.io/webhook/onedrive_sharepoint_renew_webhook.py : Python script path}';

    protected $description = 'Renew or create Microsoft Graph webhooks across tenants with per-credential resource caps.';

    public function handle()
    {
        $errors = [];
        $successes = [];

        if (!function_exists('proc_open')) {
            $msg = 'proc_open is not enabled in PHP.';
            $this->error($msg);
            Log::error($msg);
            return 1;
        }

        $who = trim(@shell_exec('whoami'));
        $pyCmd = $this->option('python') ?? 'python3';
        $pyver = trim(@shell_exec($pyCmd . ' --version 2>&1'));
        $this->info("Running as user: $who");
        $this->info("$pyCmd --version output: $pyver");

        $configs = DataConfig::where('data_sources', '"M365 - OneDrive, SharePoint & Teams Files"')->get();
        if ($configs->isEmpty()) {
            $msg = 'No DataConfig rows found for "M365 - OneDrive, SharePoint & Teams Files".';
            $this->warn($msg);
            Log::warning($msg);
            return 0;
        }

        $groups = [];
        $totalCreds = 0;

        foreach ($configs as $config) {
            $m365 = $config->m365_config_json;

            $required = ['tenant_id', 'client_id', 'client_secret', 'webhook_url', 'webhook_client_state'];
            $missing = [];
            foreach ($required as $key) {
                if (!isset($m365[$key]) || $m365[$key] === '') {
                    $missing[] = $key;
                }
            }
            if (!empty($missing)) {
                $msg = "Config #{$config->id} missing required keys: " . implode(', ', $missing);
                $this->error($msg);
                Log::error($msg);
                $errors[] = $msg;
                continue;
            }

            $tenantId = $m365['tenant_id'];
            $webhookUrl = $m365['webhook_url'];
            $clientState = $m365['webhook_client_state'];
            $groupKey = $tenantId . '|' . $webhookUrl . '|' . $clientState;

            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'tenant_id' => $tenantId,
                    'webhook_url' => $webhookUrl,
                    'client_state' => $clientState,
                    'credentials' => [],
                    'excluded_users' => [],
                    'excluded_sites' => [],
                    'config_ids' => [],
                ];
            }

            $clientId = $m365['client_id'];
            if (!array_key_exists($clientId, $groups[$groupKey]['credentials'])) {
                $groups[$groupKey]['credentials'][$clientId] = [
                    'tenant_id' => $tenantId,
                    'client_id' => $clientId,
                    'client_secret' => $m365['client_secret'],
                ];
                $totalCreds++;
            }

            if (!empty($m365['excluded_users']) && is_array($m365['excluded_users'])) {
                $groups[$groupKey]['excluded_users'] = array_values(array_unique(array_merge(
                    $groups[$groupKey]['excluded_users'],
                    $m365['excluded_users']
                )));
            }
            if (!empty($m365['excluded_sites']) && is_array($m365['excluded_sites'])) {
                $groups[$groupKey]['excluded_sites'] = array_values(array_unique(array_merge(
                    $groups[$groupKey]['excluded_sites'],
                    $m365['excluded_sites']
                )));
            }

            $groups[$groupKey]['config_ids'][] = $config->id;
        }

        if (empty($groups)) {
            $msg = 'No valid M365 configs found after validation.';
            $this->error($msg);
            Log::error($msg);
            return 1;
        }

        $this->info("Prepared " . count($groups) . " tenant/webhook groups with $totalCreds unique credentials.");

        $payload = [
            'batch_size' => (int)$this->option('batch'),
            'max_per_credential' => (int)$this->option('limit'),
            'groups' => array_values(array_map(function ($g) {
                $g['credentials'] = array_values($g['credentials']);
                return $g;
            }, $groups))
        ];

        $script = $this->option('script');
        if (!file_exists($script)) {
            $msg = "Python script not found at: $script";
            $this->error($msg);
            Log::error($msg);
            return 1;
        }

        $cmd = [$pyCmd, $script, '--bulk'];
        $cmd_string = implode(" ", array_map('escapeshellarg', $cmd));
        $this->info("Executing: $cmd_string with JSON payload via STDIN");
        Log::info("Executing: $cmd_string");

        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'], // stdout (JSON only)
            2 => ['pipe', 'w'], // stderr (logs)
        ];

        $process = proc_open($cmd, $descriptorspec, $pipes, dirname($script));

        if (!is_resource($process)) {
            $msg = "Could not start Python script";
            $this->error($msg);
            Log::error($msg);
            return 1;
        }

        fwrite($pipes[0], json_encode($payload));
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);

        if (!empty(trim($stderr))) {
            Log::info("[PY STDERR] " . trim($stderr));
        }

        if ($status === 0) {
            $msg = "Python completed successfully.";
            $this->info($msg);
            Log::info($msg);

            $data = json_decode($stdout, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $msg = "Could not parse Python stdout as JSON: " . json_last_error_msg();
                $snippet = substr($stdout, 0, 1000);
                $this->error($msg . " | STDOUT snippet: " . $snippet);
                Log::error($msg . " | STDOUT snippet: " . $snippet);
                $errors[] = $msg;
            } else {
                $overallOk = $data['ok'] ?? false;
                $groupResults = $data['group_results'] ?? [];
                $errorCount = $data['error_count'] ?? 0;

                foreach ($groupResults as $gr) {
                    $summary = sprintf(
                        "Tenant %s | creds=%d | users=%d | sites=%d | created=%d | renewed=%d | valid=%d | errors=%d | deleted_stale=%d | overflow=%d",
                        $gr['tenant_id'] ?? '?',
                        $gr['num_credentials'] ?? 0,
                        $gr['users_count'] ?? 0,
                        $gr['sites_count'] ?? 0,
                        $gr['created'] ?? 0,
                        $gr['renewed'] ?? 0,
                        $gr['already_valid'] ?? 0,
                        $gr['errors'] ?? 0,
                        $gr['deleted_stale'] ?? 0,
                        $gr['overflow_resources'] ?? 0
                    );
                    $this->info($summary);
                    Log::info($summary);
                }

                if (!$overallOk || $errorCount > 0) {
                    $msg = "Bulk renewal finished with errors: count=$errorCount";
                    $this->error($msg);
                    Log::error($msg);
                    $errors[] = $msg;
                } else {
                    $msg = "Bulk renewal finished OK.";
                    $this->info($msg);
                    Log::info($msg);
                    $successes[] = $msg;
                }
            }
        } else {
            $msg = "Python error. Exit code: $status | STDERR: " . trim($stderr) . " | STDOUT: " . substr(trim($stdout), 0, 1000);
            $this->error($msg);
            Log::error($msg);
            $errors[] = $msg;
        }

        if (!empty($errors)) {
            $this->sendAdminNotification($errors);
        }

        $summary = "RenewM365Webhooks finished with " . count($successes) . " successes and " . count($errors) . " errors.";
        $this->info($summary);
        Log::info($summary);

        return empty($errors) ? 0 : 1;
    }

    protected function sendAdminNotification(array $errors): void
    {
        try {
            $recipients = collect();
            if (Schema::hasTable('users')) {
                if (Schema::hasColumn('users', 'is_admin')) {
                    $recipients = \App\Models\User::where('is_admin', 1)->get();
                } else {
                    $recipients = \App\Models\User::take(3)->get();
                }
            }

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new M365WebhookRenewalFailed($errors));
                return;
            }

            $emailsCsv = config('mail.admin_recipients', env('ADMIN_EMAIL', ''));
            $emails = array_filter(array_map('trim', explode(',', (string)$emailsCsv)));
            foreach ($emails as $email) {
                Notification::route('mail', $email)->notify(new M365WebhookRenewalFailed($errors));
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send admin notification: ' . $e->getMessage());
        }
    }
}