<?php

namespace App\Jobs;

use App\Models\Report;
use App\Models\ReportExecution;
use App\Models\ScheduledReport;
use App\Services\ReportExecutionService;
use App\Services\EmailService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Exception;

class ExecuteScheduledReportJob extends Job
{
    protected $scheduledReport;
    protected $report;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     *
     * @param ScheduledReport $scheduledReport
     * @return void
     */
    public function __construct(ScheduledReport $scheduledReport)
    {
        $this->scheduledReport = $scheduledReport;
        $this->report = $scheduledReport->report;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $execution = null;

        try {
            // Create execution record
            $execution = ReportExecution::create([
                'report_id' => $this->report->id,
                'executed_by' => null, // System execution
                'status' => 'processing',
                'started_at' => Carbon::now(),
                'parameters_used' => json_encode($this->report->parameters)
            ]);

            // Execute the report
            $reportService = app(ReportExecutionService::class);
            $result = $reportService->execute($this->report, $execution);

            // Update execution record with success
            $execution->update([
                'status' => 'completed',
                'completed_at' => Carbon::now(),
                'execution_time_ms' => Carbon::now()->diffInMilliseconds($execution->started_at),
                'record_count' => $result['record_count'] ?? 0,
                'file_path' => $result['file_path'] ?? null,
                'file_size' => $result['file_size'] ?? null
            ]);

            // Update scheduled report
            $this->scheduledReport->update([
                'last_run_at' => Carbon::now(),
                'next_run_at' => $this->calculateNextRunTime(),
                'run_count' => $this->scheduledReport->run_count + 1
            ]);

            // Send report to recipients if configured
            if ($this->scheduledReport->recipients && count($this->scheduledReport->recipients) > 0) {
                $this->sendReportToRecipients($result, $execution);
            }

            // Clean up old executions (keep last 30)
            $this->cleanupOldExecutions();

        } catch (Exception $e) {
            // Log the error
            \Log::error('Scheduled report execution failed', [
                'report_id' => $this->report->id,
                'scheduled_report_id' => $this->scheduledReport->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Update execution record with failure
            if ($execution) {
                $execution->update([
                    'status' => 'failed',
                    'completed_at' => Carbon::now(),
                    'execution_time_ms' => Carbon::now()->diffInMilliseconds($execution->started_at),
                    'error_message' => $e->getMessage()
                ]);
            }

            // Update failure count
            $this->scheduledReport->update([
                'failure_count' => $this->scheduledReport->failure_count + 1
            ]);

            // Disable scheduled report after 5 consecutive failures
            if ($this->scheduledReport->failure_count >= 5) {
                $this->scheduledReport->update([
                    'is_active' => false
                ]);

                // Notify admin about disabled scheduled report
                $this->notifyAdminAboutDisabledReport();
            }

            throw $e; // Re-throw to trigger job retry mechanism
        }
    }

    /**
     * Calculate the next run time based on frequency
     *
     * @return Carbon
     */
    protected function calculateNextRunTime()
    {
        $now = Carbon::now($this->scheduledReport->timezone);

        switch ($this->scheduledReport->frequency) {
            case 'hourly':
                return $now->addHour();

            case 'daily':
                $next = $now->copy()->addDay();
                if ($this->scheduledReport->time_of_day) {
                    $time = Carbon::parse($this->scheduledReport->time_of_day);
                    $next->setTime($time->hour, $time->minute, 0);
                }
                return $next;

            case 'weekly':
                $next = $now->copy()->addWeek();
                if ($this->scheduledReport->day_of_week) {
                    $next->next($this->scheduledReport->day_of_week);
                }
                if ($this->scheduledReport->time_of_day) {
                    $time = Carbon::parse($this->scheduledReport->time_of_day);
                    $next->setTime($time->hour, $time->minute, 0);
                }
                return $next;

            case 'monthly':
                $next = $now->copy()->addMonth();
                if ($this->scheduledReport->day_of_month) {
                    $next->day = min($this->scheduledReport->day_of_month, $next->daysInMonth);
                }
                if ($this->scheduledReport->time_of_day) {
                    $time = Carbon::parse($this->scheduledReport->time_of_day);
                    $next->setTime($time->hour, $time->minute, 0);
                }
                return $next;

            default:
                return $now->addDay(); // Default to daily
        }
    }

    /**
     * Send report to configured recipients
     *
     * @param array $result
     * @param ReportExecution $execution
     * @return void
     */
    protected function sendReportToRecipients($result, $execution)
    {
        $emailService = app(EmailService::class);

        $subject = sprintf(
            'Scheduled Report: %s - %s',
            $this->report->name,
            Carbon::now()->format('Y-m-d H:i')
        );

        $attachmentPath = $result['file_path'] ?? null;

        foreach ($this->scheduledReport->recipients as $recipient) {
            try {
                $emailService->sendReport(
                    $recipient,
                    $subject,
                    $this->report,
                    $execution,
                    $attachmentPath
                );
            } catch (Exception $e) {
                \Log::error('Failed to send scheduled report email', [
                    'recipient' => $recipient,
                    'report_id' => $this->report->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Clean up old report executions
     *
     * @return void
     */
    protected function cleanupOldExecutions()
    {
        $keepCount = 30; // Keep last 30 executions

        $oldExecutions = ReportExecution::where('report_id', $this->report->id)
            ->orderBy('created_at', 'desc')
            ->skip($keepCount)
            ->take(100)
            ->get();

        foreach ($oldExecutions as $execution) {
            // Delete associated file if exists
            if ($execution->file_path && Storage::exists($execution->file_path)) {
                Storage::delete($execution->file_path);
            }

            $execution->delete();
        }
    }

    /**
     * Notify admin about disabled scheduled report
     *
     * @return void
     */
    protected function notifyAdminAboutDisabledReport()
    {
        // Here you would implement notification to admin
        // This could be via email, in-app notification, or logging to a monitoring system

        \Log::critical('Scheduled report disabled due to repeated failures', [
            'report_id' => $this->report->id,
            'report_name' => $this->report->name,
            'scheduled_report_id' => $this->scheduledReport->id,
            'failure_count' => $this->scheduledReport->failure_count
        ]);
    }

    /**
     * Handle a job failure
     *
     * @param Exception $exception
     * @return void
     */
    public function failed(Exception $exception)
    {
        \Log::error('Scheduled report job completely failed after retries', [
            'report_id' => $this->report->id,
            'scheduled_report_id' => $this->scheduledReport->id,
            'error' => $exception->getMessage()
        ]);
    }
}