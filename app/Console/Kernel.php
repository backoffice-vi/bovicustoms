<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Poll the CAPS FTP server for attachment response files
        // (ETD<filename>_ATT_REP.TXT) every 15 minutes.
        $schedule->job(new \App\Jobs\PollFtpAttachmentResponses())
            ->everyFifteenMinutes()
            ->withoutOverlapping(10)
            ->onOneServer()
            ->name('poll-ftp-attachment-responses');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
