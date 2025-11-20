<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DataConfig;
use Illuminate\Support\Facades\Log;

class DeltaScanS3Files extends Command
{
    protected $signature = 's3masterclassifier:s3files {--max-workers=8 : Max workers for multiprocessing}';

    protected $description = 'Delta scan and classify AWS S3 files via Python, highly scalable, checkpointed.';
    
    public function handle()
    {
        $configs = DataConfig::where('data_sources', '"Amazon Web Services (AWS) S3"')->get();
        $errors = 0; $successes = 0;

        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        foreach ($configs as $config) {
            $config_id = $config->id;

            // Fetch S3 config (could be a dedicated json column; adapt if needed)
            $s3raw = $config->m365_config_json;
            $raw = is_array($s3raw) ? $s3raw : json_decode($s3raw, true);
            $s3Config = $raw['json'] ?? $raw;
            foreach (['aws_access_key_id', 'aws_secret_access_key', 'region'] as $field) {
                if (empty($s3Config[$field])) {
                    $msg = "[MasterS3] Config #{$config_id} missing `$field`";
                    $this->error($msg); Log::error($msg); $errors++; continue 2;
                }
            }
            $access_key = $s3Config['aws_access_key_id'];
            $secret_key = $s3Config['aws_secret_access_key'];
            $region     = $s3Config['region'];

            // (Optional, pass specific S3 bucket)
            // $bucket = $s3Config['bucket_name'] ?? null;

            // Regulations
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

            Log::info("[MasterS3] config_id=$config_id, regulations arg: $regulations");

            $script = '/home/cybersecai/htdocs/www.cybersecai.io/webhook/S3/s3_master_scan_delta_classify_step1.py';
            if (!file_exists($script)) {
                $msg = "[MasterS3] Scan script missing: $script";
                $this->error($msg); Log::error($msg); $errors++; continue;
            }

            $max_workers = (int)$this->option('max-workers') ?: 8;

            // Compose command (scan all buckets for the account/config; pass $bucket after $region if you want only one)
            $cmd = [
                'python3',
                $script,
                $access_key,
                $secret_key,
                (string)$config_id,
                $regulations,
                $region,
                // $bucket    // << Uncomment to force a single bucket
            ];

            $cmd_shell = implode(' ', array_map('escapeshellarg', $cmd));
            Log::info("[MasterS3] COMMAND: " . $cmd_shell);
            $this->info("CMD (array): " . json_encode($cmd, JSON_UNESCAPED_UNICODE));
            $this->info("CMD (string): $cmd_shell");

            $process = proc_open($cmd, $descriptorspec, $pipes, dirname($script));
            if (!is_resource($process)) {
                $msg = "[MasterS3] Failed to launch scan script for config #$config_id";
                $this->error($msg); Log::error($msg); $errors++; continue;
            }

            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            $status = proc_close($process);

            Log::info("[MasterS3] STDOUT (Config $config_id): " . $stdout);
            Log::info("[MasterS3] STDERR (Config $config_id): " . $stderr);
            Log::info("[MasterS3] Status (Config $config_id): $status");

            if ($status === 0) {
                $successMsg = "S3 Delta scan completed (Config #$config_id): " . trim($stdout);
                $this->info($successMsg); Log::info($successMsg);
                $successes++;
            } else {
                $errMsg = "S3 Delta Scan ERROR (Config #$config_id): Exit $status; STDERR: $stderr; STDOUT: $stdout";
                $this->error($errMsg); Log::error($errMsg); $errors++;
            }
        }

        $summary = "AWS S3 Delta scan/classify finished: $successes successes, $errors errors.";
        $this->info($summary); Log::info($summary);

        return $errors ? 1 : 0;
    }
}