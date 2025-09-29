<?php

namespace App\Jobs;

use App\Models\Report;
use App\Models\ScheduledReport;
use App\Models\ReportExecution;
use App\Services\ReportExecutionService;
use App\Services\EmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ExecuteScheduledReport implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected $scheduledReport;

    /**
     * Create a new job instance.
     */
    public function __construct(ScheduledReport $scheduledReport)
    {
        $this->scheduledReport = $scheduledReport;
    }

    /**
     * Execute the job.
     */
    public function handle(ReportExecutionService $executionService, EmailService $emailService)
    {
        try {
            Log::info("Executing scheduled report: {$this->scheduledReport->report->name}");

            // Execute the report
            $execution = $executionService->executeReport(
                $this->scheduledReport->report,
                [],
                'scheduled',
                null // System execution
            );

            if ($execution->status === ReportExecution::STATUS_COMPLETED) {
                // Send email to recipients
                $this->sendReportEmail($execution, $emailService);

                // Mark as successfully run
                $this->scheduledReport->markAsRun();

                Log::info("Scheduled report completed successfully: {$this->scheduledReport->report->name}");
            } else {
                throw new \Exception("Report execution failed: {$execution->error_message}");
            }

        } catch (\Exception $e) {
            Log::error("Scheduled report failed: {$this->scheduledReport->report->name}", [
                'error' => $e->getMessage(),
                'report_id' => $this->scheduledReport->report_id
            ]);

            // Mark as failed
            $this->scheduledReport->markAsFailed();

            throw $e; // Re-throw to mark job as failed
        }
    }

    /**
     * Send report email to recipients
     */
    private function sendReportEmail(ReportExecution $execution, EmailService $emailService)
    {
        $report = $this->scheduledReport->report;
        $recipients = $this->scheduledReport->recipients;

        if (empty($recipients)) {
            Log::warning("No recipients configured for scheduled report: {$report->name}");
            return;
        }

        $subject = "Scheduled Report: {$report->name}";
        $generatedAt = Carbon::now($this->scheduledReport->timezone ?? 'UTC')->format('Y-m-d H:i:s T');

        $body = $this->buildEmailBody($report, $execution, $generatedAt);

        $attachments = [];
        if ($execution->file_path) {
            $attachments[] = [
                'path' => storage_path('app/' . $execution->file_path),
                'name' => pathinfo($execution->file_path, PATHINFO_BASENAME),
                'mime' => 'application/octet-stream'
            ];
        }

        foreach ($recipients as $recipient) {
            try {
                $emailService->sendEmail(
                    $recipient,
                    $subject,
                    $body,
                    $attachments
                );

                Log::info("Report email sent to: {$recipient}");
            } catch (\Exception $e) {
                Log::error("Failed to send report email to: {$recipient}", [
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Build email body content
     */
    private function buildEmailBody(Report $report, ReportExecution $execution, string $generatedAt): string
    {
        $recordCount = $execution->record_count;
        $executionTime = $execution->execution_time_seconds;

        return "
<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <title>{$report->name}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .metrics { background-color: #e9ecef; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .footer { font-size: 12px; color: #6c757d; margin-top: 30px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h2>{$report->name}</h2>
            <p><strong>Generated:</strong> {$generatedAt}</p>
            <p><strong>Description:</strong> {$report->description}</p>
        </div>

        <div class='metrics'>
            <h3>Report Metrics</h3>
            <ul>
                <li><strong>Records:</strong> " . number_format($recordCount) . "</li>
                <li><strong>Execution Time:</strong> {$executionTime} seconds</li>
                <li><strong>Status:</strong> " . ucfirst($execution->status) . "</li>
            </ul>
        </div>

        <p>This report was automatically generated and sent as part of your scheduled report subscription.</p>

        " . ($execution->file_path ? "<p><strong>Note:</strong> The full report data is attached to this email.</p>" : "") . "

        <div class='footer'>
            <p>AidlY Analytics Service | Generated automatically</p>
            <p>To modify or unsubscribe from this report, please contact your administrator.</p>
        </div>
    </div>
</body>
</html>";
    }

    /**
     * The job failed to process.
     */
    public function failed(\Exception $exception)
    {
        Log::error("Scheduled report job failed permanently", [
            'report_name' => $this->scheduledReport->report->name,
            'error' => $exception->getMessage()
        ]);

        $this->scheduledReport->markAsFailed();
    }
}