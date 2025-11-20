<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //\App\Console\Commands\ScanM365Files::class,
      \App\Console\Commands\ScanM365Files::class,
      \App\Console\Commands\DeltaScanS3Files::class, 
      \App\Console\Commands\DeltaScanM365Files::class, 
      \App\Console\Commands\SMBScanAndClassify::class,
      \App\Console\Commands\DeleteOldCSVs::class,
       \App\Console\Commands\ExportUserFindingsCsv::class,
      \App\Console\Commands\FinalJsonGraphIngestCommand::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        //$schedule->command('renew:m365-webhooks')->dailyAt('02:00')->runInBackground(); // every night at 2 AM, adjust as needed
      $schedule->command('renew:m365-webhooks --batch=10 --limit=1500')
        ->dailyAt('02:00')
        ->timezone('UTC')              // or your preferred TZ
        ->onOneServer()                // if you have multiple app servers
        ->withoutOverlapping(720)      // don’t start if a previous run is still going (minutes)
        ->runInBackground()            // don’t block the scheduler loop
        ->appendOutputTo(storage_path('logs/renew_m365_webhooks.log'));
      	
      	// Run every year, in command function it will run every 2 years - Scans full tenancy, best for first scan only
    	///$schedule->command('scan:m365files --max-workers=2')->everySixHours()->runInBackground();
      	//This is NOT required as delta will do both///
      	//$schedule->command('scan:m365files --max-workers=2')->yearly()->runInBackground();
      
      	// Run every 6 hours - Picks up Deltas
      	//$schedule->command('delta:m365files --max-workers=2')->everySixHours()->runInBackground();
      
      	//// Run every 6 hours - It scans, picks up delta and classifies - It is the MASTER
      	////$schedule->command('masterdeltascanclassify:m365files --max-workers=2')->everySixHours()->runInBackground();
      

      	//- Process all JSONs (no required arguments):
  		//	php artisan files:json-ingest
    	$schedule->command('FinalJsonGraphIngestCommand --basePath=/home/cybersecai/htdocs/www.cybersecai.io/webhook')
        	->dailyAt('01:15')
        	->withoutOverlapping()
        	->onOneServer();
      
      	// Run at 4 am daily hours - It scans, picks up delta and classifies - It is the MASTER
      	$schedule->command('masterdeltascanclassify:m365files --max-workers=2')->dailyAt('03:00')->runInBackground();
      
      
      	$schedule->command('s3masterclassifier:s3files --max-workers=2')->dailyAt('04:00')->runInBackground();
      
      	$schedule->command('smb:scan-classify')->dailyAt('04:30')->runInBackground();
      
      	// Every 6 hours
    	//$schedule->command('agentic:delete-old-csvs')->everySixHours();
      
      	// Daily
      	$schedule->command('agentic:delete-old-csvs')->dailyAt('04:55')->runInBackground();
      
      
      	$schedule->command('export:user-findings-csv')->dailyAt('05:00')->runInBackground();
      
      	
      
    }
   

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
