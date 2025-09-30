<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\FetchEmailsCommand::class,
        Commands\ProcessEmailsCommand::class,
        Commands\EmailToTicketCommand::class,
        Commands\ProcessSharedMailboxCommand::class,
        Commands\ProcessEmailsSingleCommand::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // 1. Fetch emails from Gmail and queue them
        $schedule->command('email:process')
            ->everyFiveMinutes()
            ->withoutOverlapping(10)
            ->appendOutputTo(storage_path('logs/email-processing.log'))
            ->description('Fetch emails from Gmail and queue them');

        // 2. Convert queued emails to tickets
        $schedule->command('emails:to-tickets')
            ->everyFiveMinutes()
            ->withoutOverlapping(10)
            ->appendOutputTo(storage_path('logs/email-to-ticket.log'))
            ->description('Convert queued emails to tickets');

        // Cleanup old attachments daily at 2 AM
        $schedule->call(function () {
            if (class_exists(\App\Services\AttachmentService::class)) {
                app(\App\Services\AttachmentService::class)->cleanupOldAttachments(90);
            }
        })->dailyAt('02:00')
          ->description('Clean up attachments older than 90 days');
    }
}
