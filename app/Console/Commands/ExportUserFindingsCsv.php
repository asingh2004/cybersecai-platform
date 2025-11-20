<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\DataConfig;
use Illuminate\Support\Facades\Http;

class ExportUserFindingsCsv extends Command
{
    protected $signature = 'export:user-findings-csv';
    protected $description = 'Export high and all risk findings as CSV for every user, placed in user-specific filenames.';

    public function handle()
    {
        $orchestratorUrl = 'http://127.0.0.1:8224/agentic/auto_orchestrate';

        $users = \App\Models\User::all();

        foreach ($users as $user) {
            // For each user, get ALL config IDs
          	//if (isset($user->business_id) && intval($user->business_id) !== 0) {
    			//$configIds = \App\Models\DataConfig::where('user_id', $user->business_id)->pluck('id')->toArray();
			//} else {
    		//	$configIds = \App\Models\DataConfig::where('user_id', $user->id)->pluck('id')->toArray();
			//}
            $configIds = \App\Models\DataConfig::where('user_id', $user->id)->pluck('id')->toArray();

            if (empty($configIds)) {
                $this->info("User id {$user->id}: No config ids, skipping...");
                continue;
            }

            // HIGH RISK CSV batch
            $highResp = Http::timeout(1200)
                ->post($orchestratorUrl, [
                    'user_query'    => 'Export high risk findings as csv',
                    'prior_context' => [
                        'operation' => 'high_risk_csv_batch',
                        'config_ids' => $configIds,
                        'user_id'    => $user->id,
                    ],
                ]);
            if ($highResp->ok()) {
                $highCsv = $highResp->json()['csv_filename'] ?? null;
                $this->info("HighRisk for user {$user->id} => " . ($highCsv ?? 'No filename returned'));
            } else {
                $this->error("HighRisk orchestrator failed for {$user->id}: ".$highResp->body());
            }

            // ALL RISK CSV batch
            $allResp = Http::timeout(1200)
                ->post($orchestratorUrl, [
                    'user_query'    => 'Export all findings as csv',
                    'prior_context' => [
                        'operation' => 'allrisk_csv_batch',
                        'config_ids' => $configIds,
                        'user_id'    => $user->id,
                    ],
                ]);
            if ($allResp->ok()) {
                $allCsv = $allResp->json()['csv_filename'] ?? null;
                $this->info("AllRisk for user {$user->id} => " . ($allCsv ?? 'No filename returned'));
            } else {
                $this->error("AllRisk orchestrator failed for {$user->id}: ".$allResp->body());
            }
        }

        $this->info('Done!');
        return 0;
    }
}