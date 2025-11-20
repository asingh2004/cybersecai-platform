<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DataConfig;

//SSH run>>php artisan smb:scan-classify

class SMBScanAndClassify extends Command
{
    // No {config_id}
    protected $signature = 'smb:scan-classify';
    protected $description = 'Scan SMB share and classify files for all SMB Fileshare configs';

    public function handle()
    {
        $configs = DataConfig::where('data_sources', '"SMB Fileshare"')->get();
        if ($configs->isEmpty()) {
            $this->error("No configs found with data_sources='\"SMB Fileshare\"'");
            return 1;
        }

        $total = $configs->count();
        $errorCount = 0;

        foreach ($configs as $i => $config) {
            $config_id = $config->id;
            $this->info("[$i/$total] Processing config_id: $config_id");

            $smb = $config->m365_config_json;
            $regulations = $config->regulations ?? '[]';

            $server     = $smb['smb_server']  ?? '';
            $share      = $smb['share_name']  ?? '';
            $username   = $smb['username']    ?? '';
            $password   = $smb['password']    ?? '';
            $domain     = $smb['domain']      ?? '';
            $base_path  = $smb['base_path']   ?? '';

            $py_cmd = [
                'python3',
                '/home/cybersecai/htdocs/www.cybersecai.io/webhook/SMB/SMB_master_scan_delta_classify_step1.py',
                $server,
                $username,
                $password,
                $share,
                $domain,
                $base_path,
                $config_id,
                $regulations
            ];

            $cwd = '/home/cybersecai/htdocs/www.cybersecai.io/webhook/SMB';

            $this->info("Running: " . implode(' ', array_map('escapeshellarg', $py_cmd)));

            $process = proc_open(
                $py_cmd,
                [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ],
                $pipes,
                $cwd
            );

            if (!is_resource($process)) {
                $this->error("Could not start Python script for config_id {$config_id}!");
                $errorCount++;
                continue;
            }

            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            $exitCode = proc_close($process);

            if ($exitCode === 0) {
                $this->info("config_id {$config_id}: Python script succeeded");
                $json = json_decode($stdout, true);
                $sample = is_array($json) ? json_encode(array_slice($json, 0, 3), JSON_PRETTY_PRINT) : $stdout;
                $this->line($sample);
            } else {
                $this->error("config_id {$config_id}: Python script failed!");
                $this->line("STDOUT: $stdout");
                $this->line("STDERR: $stderr");
                $errorCount++;
            }
        }

        if ($errorCount > 0) {
            $this->error("Completed with $errorCount errors out of $total configs.");
            return 2;
        }
        $this->info("All config(s) processed successfully.");
        return 0;
    }
}