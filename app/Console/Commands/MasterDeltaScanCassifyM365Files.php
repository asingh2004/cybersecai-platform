<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DataConfig;
use Illuminate\Support\Facades\Log;

class DeltaScanM365Files extends Command
{
    protected $signature = 'masterdeltascanclassify:m365files {--max-workers=8 : Max workers for multiprocessing}';
    protected $description = 'Master scan and classify M365 OneDrive/SharePoint files via Python (multiprocessing-safe, resumable, writes compliance/secrets outputs)';

    public function handle()
    {
        $configs = DataConfig::where('data_sources', '"M365 - OneDrive, SharePoint & Teams Files"')->get();
        $errors = 0; $successes = 0;

        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        foreach ($configs as $config) {
            $config_id = $config->id;

            $m365 = $config->m365_config_json;
            $required = ['tenant_id', 'client_id', 'client_secret'];
            foreach ($required as $field) {
                if (empty($m365[$field])) {
                    $msg = "Config #{$config_id} missing `$field`";
                    $this->error($msg);
                    Log::error($msg);
                    $errors++;
                    continue 2;
                }
            }

            $tenant_id = $m365['tenant_id'];
            $client_id = $m365['client_id'];
            $client_secret = $m365['client_secret'];

            // Double-decode regulations JSON
            $regulations_raw = $config->regulations;
            if (!is_string($regulations_raw) || trim($regulations_raw) === '') {
                $regulations = '[]';
            } else {
                $lev1 = json_decode($regulations_raw, true);
                if (json_last_error() !== JSON_ERROR_NONE || !is_array($lev1)) {
                    $regulations = '[]';
                } else {
                    $regulations = json_encode($lev1, JSON_UNESCAPED_UNICODE);
                }
            }

            Log::info("[MasterM365] config_id=$config_id, regulations arg: $regulations");

            $subs_file = "/home/cybersecai/htdocs/www.cybersecai.io/webhook/subscriptions_bulk_{$config_id}.json";
            $script    = '/home/cybersecai/htdocs/www.cybersecai.io/webhook/M365/m365_master_scan_delta_classify_step1.py';

            if (!file_exists($subs_file)) {
                $msg = "Config #{$config_id} missing subscriptions file: $subs_file";
                $this->error($msg);  Log::error($msg); $errors++; continue;
            }
            if (!file_exists($script)) {
                $msg = "Scan script missing: $script";
                $this->error($msg);  Log::error($msg); $errors++; continue;
            }

            $max_workers = (int)$this->option('max-workers') ?: 8;

            $cmd = [
                'python3',
                $script,
                $tenant_id,
                $client_id,
                $client_secret,
                (string)$config_id,
                $subs_file,
                $regulations,
                "--workers=$max_workers"
            ];

            // Log both shell-formatted and array-format command
            $cmd_shell = implode(' ', array_map('escapeshellarg', $cmd));
            Log::info("[MasterM365] COMMAND for config $config_id: " . $cmd_shell);
            Log::info("[MasteraM365] CMD ARRAY for config $config_id: " . json_encode($cmd));
            $this->info("CMD (array): " . json_encode($cmd, JSON_UNESCAPED_UNICODE));
            $this->info("CMD (string): $cmd_shell");

            $process = proc_open($cmd, $descriptorspec, $pipes, dirname($script));
            if (!is_resource($process)) {
                $msg = "Failed to launch scan script for config #$config_id";
                $this->error($msg); Log::error($msg); $errors++; continue;
            }

            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            $status = proc_close($process);

            Log::info("[MasterM365] STDOUT (Config $config_id): " . $stdout);
            Log::info("[MasterM365] STDERR (Config $config_id): " . $stderr);
            Log::info("[MasterM365] Status (Config $config_id): $status");

            if ($status === 0) {
                $successMsg = "Scan completed (Config #$config_id): " . trim($stdout);
                $this->info($successMsg); Log::info($successMsg);
                $successes++;
            } else {
                $errMsg = "Scan ERROR (Config #$config_id): Exit $status; STDERR: $stderr; STDOUT: $stdout";
                $this->error($errMsg); Log::error($errMsg); $errors++;
            }
        }

        $summary = "M365 Files delta scan and clssify finished: $successes successes, $errors errors.";
        $this->info($summary);
        Log::info($summary);

        return $errors ? 1 : 0;
    }
}