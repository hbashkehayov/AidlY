<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use App\Services\AIProviderService;
use Carbon\Carbon;

class MonitoringController extends Controller
{
    protected $providerService;

    public function __construct(AIProviderService $providerService)
    {
        $this->providerService = $providerService;
    }

    /**
     * Get overall metrics
     */
    public function metrics(Request $request)
    {
        $timeRange = $request->get('range', '24h');
        $startTime = $this->getStartTime($timeRange);

        try {
            $metrics = [
                'summary' => $this->getSummaryMetrics($startTime),
                'processing' => $this->getProcessingMetrics($startTime),
                'providers' => $this->getProviderMetrics($startTime),
                'queue' => $this->getQueueMetrics(),
                'performance' => $this->getPerformanceMetrics($startTime),
                'errors' => $this->getErrorMetrics($startTime),
                'timestamp' => now()->toISOString()
            ];

            return response()->json([
                'success' => true,
                'data' => $metrics
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get health status
     */
    public function health(Request $request)
    {
        try {
            $health = [
                'status' => 'healthy',
                'checks' => [],
                'timestamp' => now()->toISOString()
            ];

            // Check database
            $health['checks']['database'] = $this->checkDatabase();

            // Check Redis
            $health['checks']['redis'] = $this->checkRedis();

            // Check queue
            $health['checks']['queue'] = $this->checkQueue();

            // Check AI providers
            $health['checks']['providers'] = $this->checkProviders();

            // Determine overall health
            $health['status'] = $this->determineOverallHealth($health['checks']);

            return response()->json($health);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    /**
     * Get logs
     */
    public function logs(Request $request)
    {
        $level = $request->get('level', 'all');
        $limit = $request->get('limit', 100);
        $offset = $request->get('offset', 0);

        try {
            $query = DB::table('ai_processing_logs');

            if ($level !== 'all') {
                $query->where('level', $level);
            }

            $logs = $query->orderBy('created_at', 'desc')
                ->limit($limit)
                ->offset($offset)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $logs,
                'total' => $query->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get performance metrics
     */
    public function performance(Request $request)
    {
        $timeRange = $request->get('range', '1h');
        $interval = $request->get('interval', '5m');

        try {
            $startTime = $this->getStartTime($timeRange);
            $endTime = now();

            $performance = [
                'response_times' => $this->getResponseTimes($startTime, $endTime, $interval),
                'throughput' => $this->getThroughput($startTime, $endTime, $interval),
                'success_rate' => $this->getSuccessRate($startTime, $endTime, $interval),
                'resource_usage' => $this->getResourceUsage(),
                'timestamp' => now()->toISOString()
            ];

            return response()->json([
                'success' => true,
                'data' => $performance
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get error metrics
     */
    public function errors(Request $request)
    {
        $timeRange = $request->get('range', '24h');
        $groupBy = $request->get('group_by', 'type');

        try {
            $startTime = $this->getStartTime($timeRange);

            $errors = DB::table('ai_processing_queue')
                ->where('status', 'failed')
                ->where('created_at', '>=', $startTime)
                ->select(
                    DB::raw("COUNT(*) as count"),
                    DB::raw("error_message"),
                    DB::raw("provider"),
                    DB::raw("action")
                )
                ->groupBy(['error_message', 'provider', 'action'])
                ->orderBy('count', 'desc')
                ->get();

            $errorSummary = [
                'total_errors' => $errors->sum('count'),
                'errors_by_type' => $this->groupErrors($errors, $groupBy),
                'recent_errors' => $this->getRecentErrors(10),
                'error_rate' => $this->calculateErrorRate($startTime),
                'timestamp' => now()->toISOString()
            ];

            return response()->json([
                'success' => true,
                'data' => $errorSummary
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get summary metrics
     */
    protected function getSummaryMetrics($startTime)
    {
        $totalRequests = DB::table('ai_processing_queue')
            ->where('created_at', '>=', $startTime)
            ->count();

        $completedRequests = DB::table('ai_processing_queue')
            ->where('created_at', '>=', $startTime)
            ->where('status', 'completed')
            ->count();

        $failedRequests = DB::table('ai_processing_queue')
            ->where('created_at', '>=', $startTime)
            ->where('status', 'failed')
            ->count();

        $pendingRequests = DB::table('ai_processing_queue')
            ->whereIn('status', ['pending', 'processing'])
            ->count();

        return [
            'total_requests' => $totalRequests,
            'completed_requests' => $completedRequests,
            'failed_requests' => $failedRequests,
            'pending_requests' => $pendingRequests,
            'success_rate' => $totalRequests > 0
                ? round(($completedRequests / $totalRequests) * 100, 2)
                : 0,
        ];
    }

    /**
     * Get processing metrics
     */
    protected function getProcessingMetrics($startTime)
    {
        $metrics = DB::table('ai_processing_queue')
            ->where('created_at', '>=', $startTime)
            ->where('status', 'completed')
            ->select(
                DB::raw('AVG(EXTRACT(EPOCH FROM (processed_at - created_at))) as avg_processing_time'),
                DB::raw('MIN(EXTRACT(EPOCH FROM (processed_at - created_at))) as min_processing_time'),
                DB::raw('MAX(EXTRACT(EPOCH FROM (processed_at - created_at))) as max_processing_time'),
                DB::raw('COUNT(*) as total_processed')
            )
            ->first();

        return [
            'avg_processing_time' => round($metrics->avg_processing_time ?? 0, 2),
            'min_processing_time' => round($metrics->min_processing_time ?? 0, 2),
            'max_processing_time' => round($metrics->max_processing_time ?? 0, 2),
            'total_processed' => $metrics->total_processed ?? 0,
        ];
    }

    /**
     * Get provider metrics
     */
    protected function getProviderMetrics($startTime)
    {
        $providers = config('ai.providers', []);
        $metrics = [];

        foreach (array_keys($providers) as $provider) {
            if (!$providers[$provider]['enabled']) {
                continue;
            }

            $providerMetrics = DB::table('ai_processing_queue')
                ->where('created_at', '>=', $startTime)
                ->where('provider', $provider)
                ->select(
                    DB::raw('COUNT(*) as total_requests'),
                    DB::raw('SUM(CASE WHEN status = \'completed\' THEN 1 ELSE 0 END) as completed'),
                    DB::raw('SUM(CASE WHEN status = \'failed\' THEN 1 ELSE 0 END) as failed'),
                    DB::raw('AVG(CASE WHEN status = \'completed\' THEN EXTRACT(EPOCH FROM (processed_at - created_at)) END) as avg_response_time')
                )
                ->first();

            $metrics[$provider] = [
                'total_requests' => $providerMetrics->total_requests ?? 0,
                'completed' => $providerMetrics->completed ?? 0,
                'failed' => $providerMetrics->failed ?? 0,
                'avg_response_time' => round($providerMetrics->avg_response_time ?? 0, 2),
                'health' => $this->getProviderHealth($provider),
            ];
        }

        return $metrics;
    }

    /**
     * Get queue metrics
     */
    protected function getQueueMetrics()
    {
        try {
            $queueSizes = [];
            $priorities = config('queue.priorities', []);

            foreach ($priorities as $priority => $queue) {
                $size = Redis::llen("queues:$queue");
                $queueSizes[$priority] = $size;
            }

            return [
                'sizes' => $queueSizes,
                'total' => array_sum($queueSizes),
                'failed_jobs' => DB::table('failed_jobs')->count(),
            ];

        } catch (\Exception $e) {
            return [
                'error' => 'Unable to fetch queue metrics',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get performance metrics
     */
    protected function getPerformanceMetrics($startTime)
    {
        $intervals = $this->generateTimeIntervals($startTime, now(), '1h');
        $performance = [];

        foreach ($intervals as $interval) {
            $metrics = DB::table('ai_processing_queue')
                ->where('created_at', '>=', $interval['start'])
                ->where('created_at', '<', $interval['end'])
                ->where('status', 'completed')
                ->select(
                    DB::raw('AVG(EXTRACT(EPOCH FROM (processed_at - created_at))) as avg_response_time'),
                    DB::raw('COUNT(*) as throughput')
                )
                ->first();

            $performance[] = [
                'timestamp' => $interval['start']->toISOString(),
                'avg_response_time' => round($metrics->avg_response_time ?? 0, 2),
                'throughput' => $metrics->throughput ?? 0,
            ];
        }

        return $performance;
    }

    /**
     * Get error metrics
     */
    protected function getErrorMetrics($startTime)
    {
        return DB::table('ai_processing_queue')
            ->where('created_at', '>=', $startTime)
            ->where('status', 'failed')
            ->select('error_message', DB::raw('COUNT(*) as count'))
            ->groupBy('error_message')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get();
    }

    /**
     * Check database health
     */
    protected function checkDatabase()
    {
        try {
            DB::select('SELECT 1');
            return ['status' => 'healthy', 'message' => 'Database connection OK'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => $e->getMessage()];
        }
    }

    /**
     * Check Redis health
     */
    protected function checkRedis()
    {
        try {
            Redis::ping();
            return ['status' => 'healthy', 'message' => 'Redis connection OK'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => $e->getMessage()];
        }
    }

    /**
     * Check queue health
     */
    protected function checkQueue()
    {
        try {
            $failedJobs = DB::table('failed_jobs')->count();

            if ($failedJobs > 100) {
                return ['status' => 'warning', 'message' => "High number of failed jobs: $failedJobs"];
            }

            return ['status' => 'healthy', 'message' => 'Queue system operational'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => $e->getMessage()];
        }
    }

    /**
     * Check AI providers health
     */
    protected function checkProviders()
    {
        $providers = config('ai.providers', []);
        $results = [];

        foreach (array_keys($providers) as $provider) {
            if (!$providers[$provider]['enabled']) {
                continue;
            }

            try {
                $adapter = $this->providerService->getAdapter($provider);
                $health = $adapter->healthCheck();
                $results[$provider] = $health;
            } catch (\Exception $e) {
                $results[$provider] = [
                    'status' => 'unhealthy',
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Get provider health status
     */
    protected function getProviderHealth($provider)
    {
        $cacheKey = "provider_health_$provider";

        return Cache::remember($cacheKey, 60, function () use ($provider) {
            try {
                $adapter = $this->providerService->getAdapter($provider);
                return $adapter->healthCheck();
            } catch (\Exception $e) {
                return ['status' => 'unknown', 'error' => $e->getMessage()];
            }
        });
    }

    /**
     * Determine overall health status
     */
    protected function determineOverallHealth($checks)
    {
        $hasUnhealthy = false;
        $hasWarning = false;

        foreach ($checks as $check) {
            if (is_array($check)) {
                if (isset($check['status'])) {
                    if ($check['status'] === 'unhealthy') {
                        $hasUnhealthy = true;
                    } elseif ($check['status'] === 'warning') {
                        $hasWarning = true;
                    }
                } else {
                    // Check nested provider statuses
                    foreach ($check as $subCheck) {
                        if (isset($subCheck['status']) && $subCheck['status'] === 'unhealthy') {
                            $hasUnhealthy = true;
                        }
                    }
                }
            }
        }

        if ($hasUnhealthy) {
            return 'unhealthy';
        } elseif ($hasWarning) {
            return 'degraded';
        }

        return 'healthy';
    }

    /**
     * Get start time based on range
     */
    protected function getStartTime($range)
    {
        return match($range) {
            '1h' => now()->subHour(),
            '6h' => now()->subHours(6),
            '24h' => now()->subDay(),
            '7d' => now()->subWeek(),
            '30d' => now()->subMonth(),
            default => now()->subDay(),
        };
    }

    /**
     * Generate time intervals
     */
    protected function generateTimeIntervals($start, $end, $interval)
    {
        $intervals = [];
        $current = $start->copy();

        while ($current < $end) {
            $intervalEnd = $current->copy()->add(CarbonInterval::fromString($interval));

            $intervals[] = [
                'start' => $current->copy(),
                'end' => $intervalEnd > $end ? $end : $intervalEnd
            ];

            $current = $intervalEnd;
        }

        return $intervals;
    }

    /**
     * Get response times
     */
    protected function getResponseTimes($startTime, $endTime, $interval)
    {
        $intervals = $this->generateTimeIntervals($startTime, $endTime, $interval);
        $responseTimes = [];

        foreach ($intervals as $int) {
            $avg = DB::table('ai_processing_queue')
                ->where('created_at', '>=', $int['start'])
                ->where('created_at', '<', $int['end'])
                ->where('status', 'completed')
                ->avg(DB::raw('EXTRACT(EPOCH FROM (processed_at - created_at))'));

            $responseTimes[] = [
                'timestamp' => $int['start']->toISOString(),
                'value' => round($avg ?? 0, 2)
            ];
        }

        return $responseTimes;
    }

    /**
     * Get throughput
     */
    protected function getThroughput($startTime, $endTime, $interval)
    {
        $intervals = $this->generateTimeIntervals($startTime, $endTime, $interval);
        $throughput = [];

        foreach ($intervals as $int) {
            $count = DB::table('ai_processing_queue')
                ->where('created_at', '>=', $int['start'])
                ->where('created_at', '<', $int['end'])
                ->count();

            $throughput[] = [
                'timestamp' => $int['start']->toISOString(),
                'value' => $count
            ];
        }

        return $throughput;
    }

    /**
     * Get success rate
     */
    protected function getSuccessRate($startTime, $endTime, $interval)
    {
        $intervals = $this->generateTimeIntervals($startTime, $endTime, $interval);
        $successRates = [];

        foreach ($intervals as $int) {
            $total = DB::table('ai_processing_queue')
                ->where('created_at', '>=', $int['start'])
                ->where('created_at', '<', $int['end'])
                ->count();

            $successful = DB::table('ai_processing_queue')
                ->where('created_at', '>=', $int['start'])
                ->where('created_at', '<', $int['end'])
                ->where('status', 'completed')
                ->count();

            $rate = $total > 0 ? ($successful / $total) * 100 : 0;

            $successRates[] = [
                'timestamp' => $int['start']->toISOString(),
                'value' => round($rate, 2)
            ];
        }

        return $successRates;
    }

    /**
     * Get resource usage
     */
    protected function getResourceUsage()
    {
        return [
            'memory' => [
                'used' => memory_get_usage(true),
                'peak' => memory_get_peak_usage(true),
                'limit' => ini_get('memory_limit')
            ],
            'cpu' => sys_getloadavg(),
            'disk' => [
                'free' => disk_free_space('/'),
                'total' => disk_total_space('/')
            ]
        ];
    }

    /**
     * Group errors
     */
    protected function groupErrors($errors, $groupBy)
    {
        return $errors->groupBy($groupBy)->map(function ($group) {
            return $group->sum('count');
        });
    }

    /**
     * Get recent errors
     */
    protected function getRecentErrors($limit)
    {
        return DB::table('ai_processing_queue')
            ->where('status', 'failed')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get(['id', 'provider', 'action', 'error_message', 'created_at']);
    }

    /**
     * Calculate error rate
     */
    protected function calculateErrorRate($startTime)
    {
        $total = DB::table('ai_processing_queue')
            ->where('created_at', '>=', $startTime)
            ->count();

        $errors = DB::table('ai_processing_queue')
            ->where('created_at', '>=', $startTime)
            ->where('status', 'failed')
            ->count();

        return $total > 0 ? round(($errors / $total) * 100, 2) : 0;
    }
}