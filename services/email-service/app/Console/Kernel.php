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
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Main email-to-ticket conversion process every 5 minutes
        $schedule->command('emails:to-tickets')
            ->everyFiveMinutes()
            ->withoutOverlapping(10) // Wait up to 10 minutes for overlapping
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/email-to-ticket.log'))
            ->emailOutputOnFailure(env('ADMIN_EMAIL', 'admin@aidly.com'))
            ->description('Fetch emails and convert them to tickets');

        // Cleanup old attachments daily at 2 AM
        $schedule->call(function () {
            app(\App\Services\AttachmentService::class)->cleanupOldAttachments(90);
        })->dailyAt('02:00')
          ->description('Clean up attachments older than 90 days');

        // Retry failed emails every 15 minutes
        $schedule->command('emails:to-tickets --process-only --limit=20')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->runInBackground()
            ->description('Retry processing failed emails');

        // Legacy commands (kept for backward compatibility)
        // Fetch emails every 5 minutes
        $schedule->command('emails:fetch')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground()
            ->when(function () {
                return env('USE_LEGACY_EMAIL_COMMANDS', false);
            });

        // Process emails every 2 minutes
        $schedule->command('emails:process')
            ->everyTwoMinutes()
            ->withoutOverlapping()
            ->runInBackground()
            ->when(function () {
                return env('USE_LEGACY_EMAIL_COMMANDS', false);
            });
    }
}
