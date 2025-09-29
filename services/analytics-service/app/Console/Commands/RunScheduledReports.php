<?php

namespace App\Console\Commands;

use App\Models\ScheduledReport;
use App\Jobs\ExecuteScheduledReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunScheduledReports extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'reports:run-scheduled {--limit=10 : Maximum number of reports to process}';

    /**
     * The console command description.
     */
    protected $description = 'Run due scheduled reports';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $limit = (int) $this->option('limit');

        // Get due reports
        $dueReports = ScheduledReport::due()
            ->with('report')
            ->limit($limit)
            ->get();

        if ($dueReports->isEmpty()) {
            $this->info('No scheduled reports are due.');
            return 0;
        }

        $this->info("Found {$dueReports->count()} due scheduled reports.");

        foreach ($dueReports as $scheduledReport) {
            try {
                $this->line("Processing: {$scheduledReport->report->name}");

                // Dispatch job to queue
                ExecuteScheduledReport::dispatch($scheduledReport);

                $this->info("✓ Queued: {$scheduledReport->report->name}");

            } catch (\Exception $e) {
                $this->error("✗ Failed to queue: {$scheduledReport->report->name}");
                $this->error("  Error: {$e->getMessage()}");

                Log::error("Failed to queue scheduled report", [
                    'report_id' => $scheduledReport->report_id,
                    'error' => $e->getMessage()
                ]);

                // Mark as failed
                $scheduledReport->markAsFailed();
            }
        }

        $this->info('Finished processing scheduled reports.');
        return 0;
    }
}