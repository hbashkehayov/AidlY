<?php

namespace App\Services;

use App\Models\Report;
use App\Models\ReportExecution;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ReportExecutionService
{
    /**
     * Execute a report and return the execution record
     */
    public function executeReport(Report $report, array $parameters = [], string $executionType = 'manual', ?string $userId = null): ReportExecution
    {
        $startTime = microtime(true);

        // Create execution record
        $execution = ReportExecution::create([
            'report_id' => $report->id,
            'executed_by' => $userId,
            'execution_type' => $executionType,
            'status' => ReportExecution::STATUS_RUNNING
        ]);

        try {
            // Execute the query
            $results = $this->executeQuery($report->query_sql, $parameters);
            $recordCount = count($results);

            $executionTime = round((microtime(true) - $startTime) * 1000);

            // Update execution record
            $execution->update([
                'status' => ReportExecution::STATUS_COMPLETED,
                'record_count' => $recordCount,
                'execution_time_ms' => $executionTime
            ]);

            // Update report's last executed time
            $report->update(['last_executed_at' => now()]);

            return $execution;

        } catch (\Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000);

            $execution->update([
                'status' => ReportExecution::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'execution_time_ms' => $executionTime
            ]);

            throw $e;
        }
    }

    /**
     * Execute a report and export to file
     */
    public function executeReportWithExport(Report $report, array $parameters = [], string $format = 'csv', string $executionType = 'manual', ?string $userId = null): ReportExecution
    {
        $startTime = microtime(true);

        $execution = ReportExecution::create([
            'report_id' => $report->id,
            'executed_by' => $userId,
            'execution_type' => $executionType,
            'status' => ReportExecution::STATUS_RUNNING
        ]);

        try {
            // Execute the query
            $results = $this->executeQuery($report->query_sql, $parameters);
            $recordCount = count($results);

            // Export to file
            $filePath = $this->exportResultsToFile($results, $report->columns, $format, $execution);

            $executionTime = round((microtime(true) - $startTime) * 1000);

            $execution->update([
                'status' => ReportExecution::STATUS_COMPLETED,
                'record_count' => $recordCount,
                'execution_time_ms' => $executionTime,
                'file_path' => $filePath
            ]);

            $report->update(['last_executed_at' => now()]);

            return $execution;

        } catch (\Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000);

            $execution->update([
                'status' => ReportExecution::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'execution_time_ms' => $executionTime
            ]);

            throw $e;
        }
    }

    /**
     * Execute SQL query with parameters
     */
    private function executeQuery(string $sql, array $parameters = []): array
    {
        // Basic security validation
        $sql = trim($sql);
        if (!preg_match('/^SELECT\s+/i', $sql) || preg_match('/\b(DROP|DELETE|UPDATE|INSERT|ALTER|CREATE)\b/i', $sql)) {
            throw new \Exception('Only SELECT queries are allowed');
        }

        // Replace parameter placeholders
        foreach ($parameters as $index => $value) {
            $placeholder = '$' . ($index + 1);
            $sql = str_replace($placeholder, "'" . addslashes($value) . "'", $sql);
        }

        // Add query timeout
        DB::statement('SET statement_timeout = ?', [env('ANALYTICS_QUERY_TIMEOUT', 30) * 1000]);

        try {
            $results = DB::select($sql);
            return $results;
        } catch (\Exception $e) {
            throw new \Exception("Query execution failed: {$e->getMessage()}");
        }
    }

    /**
     * Export results to file
     */
    private function exportResultsToFile(array $results, array $columns, string $format, ReportExecution $execution): string
    {
        if (empty($results)) {
            throw new \Exception('No data to export');
        }

        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "report_{$execution->id}_{$timestamp}.{$format}";

        if ($format === 'csv') {
            return $this->exportToCsv($results, $columns, $filename);
        } elseif ($format === 'json') {
            return $this->exportToJson($results, $filename);
        }

        // Default to CSV
        return $this->exportToCsv($results, $columns, $filename);
    }

    /**
     * Export results to CSV
     */
    private function exportToCsv(array $results, array $columns, string $filename): string
    {
        $content = '';

        // Add header row
        $content .= implode(',', array_map(function($col) {
            return '"' . str_replace('"', '""', ucwords(str_replace('_', ' ', $col))) . '"';
        }, $columns)) . "\n";

        // Add data rows
        foreach ($results as $row) {
            $rowData = [];
            foreach ($columns as $column) {
                $value = $row->$column ?? '';

                // Format dates nicely
                if ($value instanceof \DateTime || (is_string($value) && strtotime($value))) {
                    try {
                        $value = Carbon::parse($value)->format('Y-m-d H:i:s');
                    } catch (\Exception $e) {
                        // Keep original value if parsing fails
                    }
                }

                $rowData[] = '"' . str_replace('"', '""', $value) . '"';
            }
            $content .= implode(',', $rowData) . "\n";
        }

        // Store file
        $path = "exports/{$filename}";
        Storage::disk('local')->put($path, $content);

        return $path;
    }

    /**
     * Export results to JSON
     */
    private function exportToJson(array $results, string $filename): string
    {
        $content = json_encode([
            'generated_at' => now()->toISOString(),
            'record_count' => count($results),
            'data' => $results
        ], JSON_PRETTY_PRINT);

        $path = "exports/{$filename}";
        Storage::disk('local')->put($path, $content);

        return $path;
    }

    /**
     * Get execution statistics for a report
     */
    public function getExecutionStats(Report $report): array
    {
        $executions = $report->executions()
            ->where('created_at', '>=', now()->subDays(30))
            ->get();

        $successful = $executions->where('status', ReportExecution::STATUS_COMPLETED);
        $failed = $executions->where('status', ReportExecution::STATUS_FAILED);

        return [
            'total_executions' => $executions->count(),
            'successful_executions' => $successful->count(),
            'failed_executions' => $failed->count(),
            'success_rate' => $executions->count() > 0 ? round(($successful->count() / $executions->count()) * 100, 2) : 0,
            'avg_execution_time_ms' => $successful->avg('execution_time_ms'),
            'avg_record_count' => $successful->avg('record_count'),
            'last_execution_at' => $executions->max('created_at'),
            'last_successful_execution_at' => $successful->max('created_at'),
            'period_days' => 30
        ];
    }

    /**
     * Clean up old execution files
     */
    public function cleanupOldExecutions(int $retentionDays = 90)
    {
        $cutoffDate = now()->subDays($retentionDays);

        $oldExecutions = ReportExecution::where('created_at', '<', $cutoffDate)
            ->whereNotNull('file_path')
            ->get();

        $deletedCount = 0;
        foreach ($oldExecutions as $execution) {
            try {
                if (Storage::disk('local')->exists($execution->file_path)) {
                    Storage::disk('local')->delete($execution->file_path);
                }
                $execution->update(['file_path' => null]);
                $deletedCount++;
            } catch (\Exception $e) {
                \Log::warning("Failed to cleanup execution file: {$execution->file_path}", [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $deletedCount;
    }
}