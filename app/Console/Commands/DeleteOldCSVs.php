<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DeleteOldCSVs extends Command
{
    protected $signature = 'agentic:delete-old-csvs';
    protected $description = 'Delete all .csv files in the agentic tmp_csv folder every 24 hours.';

    // Set your target dir path
    private $csvFolder = '/home/cybersecai/htdocs/www.cybersecai.io/webhook/agenticai/tmp_csv/';

    public function handle()
    {
        $deleted = 0;
        $files = glob($this->csvFolder . '*.*');
        foreach ($files as $file) {
            // Unlink (delete) only .csv files
            if (is_file($file)) {
                unlink($file);
                $this->info("Deleted: $file");
                $deleted++;
            }
        }
        $this->info("Cleanup complete. Deleted $deleted CSV file(s).");
        return 0;
    }
}