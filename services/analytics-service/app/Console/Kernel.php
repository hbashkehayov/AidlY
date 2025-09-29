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
        Commands\RunScheduledReports::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Run scheduled reports every 15 minutes
        $schedule->command('reports:run-scheduled')
                 ->everyFifteenMinutes()
                 ->withoutOverlapping()
                 ->runInBackground();

        // Cleanup old report executions daily
        $schedule->call(function () {
            app(\App\Services\ReportExecutionService::class)->cleanupOldExecutions(90);
        })->daily()->at('02:00');

        // Aggregate daily metrics at 1 AM
        $schedule->call(function () {
            $yesterday = now()->subDay()->format('Y-m-d');
            \App\Models\TicketMetrics::aggregateForDate($yesterday);
        })->daily()->at('01:00');
    }
}
