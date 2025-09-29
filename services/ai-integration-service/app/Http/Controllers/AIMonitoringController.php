<?php

namespace App\Http\Controllers;

use App\Models\AIConfiguration;
use App\Services\FeatureFlagService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Laravel\Lumen\Routing\Controller as BaseController;

class AIMonitoringController extends BaseController
{
    private FeatureFlagService $featureFlagService;

    public function __construct(FeatureFlagService $featureFlagService)
    {
        $this->featureFlagService = $featureFlagService;
    }

    /**
     * Get AI service health status
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function health()
    {
        try {
            $health = [
                'status' => 'healthy',
                'timestamp' => now()->toISOString(),
                'checks' => [
                    'database' => $this->checkDatabase(),
                    'redis' => $this->checkRedis(),
                    'configurations' => $this->checkConfigurations(),
                    'feature_flags' => $this->checkFeatureFlags(),
                    'providers' => $this->checkProviders(),
                    'queue' => $this->checkQueue(),
                ],
                'summary' => [
                    'active_configurations' => 0,
                    'enabled_features' => 0,
                    'healthy_providers' => 0,
                    'total_requests_today' => 0
                ]
            ];

            // Determine overall health status
            $failedChecks = array_filter($health['checks'], fn($check) => $check['status'] !== 'healthy');
            if (count($failedChecks) > 0) {
                $health['status'] = count($failedChecks) > 2 ? 'unhealthy' : 'degraded';
            }

            // Calculate summary
            $health['summary']['active_configurations'] = AIConfiguration::active()->count();
            $health['summary']['enabled_features'] = count(array_filter($this->featureFlagService->getAllFlags()));
            $health['summary']['healthy_providers'] = count(array_filter($health['checks']['providers']['providers'] ?? [], fn($p) => $p['healthy']));

            return response()->json([
                'success' => true,
                'data' => $health
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 'unhealthy',
                'message' => 'Health check failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get AI service metrics
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function metrics(Request $request)
    {
        try {
            $timeframe = $request->get('timeframe', '24h');
            $provider = $request->get('provider');

            $metrics = [
                'overview' => $this->getOverviewMetrics($timeframe),
                'performance' => $this->getPerformanceMetrics($timeframe),
                'usage' => $this->getUsageMetrics($timeframe, $provider),
                'errors' => $this->getErrorMetrics($timeframe),
                'feature_flags' => $this->featureFlagService->getStats(),
            ];

            return response()->json([
                'success' => true,
                'data' => $metrics,
                'timeframe' => $timeframe,
                'generated_at' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve metrics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get provider status
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function providerStatus(Request $request)
    {
        try {
            $providers = AIConfiguration::active()
                ->select(['id', 'name', 'provider', 'is_active', 'last_used_at', 'requests_count', 'success_count', 'error_count'])
                ->get()
                ->map(function ($config) {
                    return [
                        'id' => $config->id,
                        'name' => $config->name,
                        'provider' => $config->provider,
                        'is_active' => $config->is_active,
                        'last_used_at' => $config->last_used_at,
                        'requests_count' => $config->requests_count,
                        'success_rate' => $config->requests_count > 0 ? ($config->success_count / $config->requests_count) * 100 : 0,
                        'error_rate' => $config->requests_count > 0 ? ($config->error_count / $config->requests_count) * 100 : 0,
                        'healthy' => $this->isProviderHealthy($config),
                        'features' => $config->getEnabledFeatures()
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $providers,
                'summary' => [
                    'total' => $providers->count(),
                    'healthy' => $providers->where('healthy', true)->count(),
                    'unhealthy' => $providers->where('healthy', false)->count(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get provider status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get processing queue status
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function queueStatus()
    {
        try {
            $status = [
                'queues' => [
                    'ai_processing' => $this->getQueueStats('ai_processing'),
                    'ai_categorization' => $this->getQueueStats('ai_categorization'),
                    'ai_suggestions' => $this->getQueueStats('ai_suggestions'),
                    'ai_sentiment' => $this->getQueueStats('ai_sentiment'),
                ],
                'summary' => [
                    'total_pending' => 0,
                    'total_processing' => 0,
                    'total_failed' => 0,
                ],
                'workers' => $this->getWorkerStatus()
            ];

            // Calculate summary
            foreach ($status['queues'] as $queue) {
                $status['summary']['total_pending'] += $queue['pending'];
                $status['summary']['total_processing'] += $queue['processing'];
                $status['summary']['total_failed'] += $queue['failed'];
            }

            return response()->json([
                'success' => true,
                'data' => $status
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get queue status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get AI processing logs
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logs(Request $request)
    {
        try {
            $limit = $request->get('limit', 100);
            $level = $request->get('level', 'all');
            $provider = $request->get('provider');

            // Mock log data - in production, you'd query actual logs
            $logs = collect([
                [
                    'id' => '1',
                    'timestamp' => now()->subMinutes(5)->toISOString(),
                    'level' => 'info',
                    'provider' => 'openai',
                    'message' => 'Ticket categorization completed successfully',
                    'context' => ['ticket_id' => 'tkn-123', 'category' => 'technical', 'confidence' => 0.89],
                ],
                [
                    'id' => '2',
                    'timestamp' => now()->subMinutes(10)->toISOString(),
                    'level' => 'warning',
                    'provider' => 'claude',
                    'message' => 'API rate limit approaching',
                    'context' => ['requests_remaining' => 50, 'reset_time' => now()->addHour()->toISOString()],
                ],
                [
                    'id' => '3',
                    'timestamp' => now()->subMinutes(15)->toISOString(),
                    'level' => 'error',
                    'provider' => 'gemini',
                    'message' => 'Failed to process sentiment analysis',
                    'context' => ['error' => 'API timeout', 'ticket_id' => 'tkn-456'],
                ],
            ])->take($limit);

            // Filter by level and provider
            if ($level !== 'all') {
                $logs = $logs->where('level', $level);
            }

            if ($provider) {
                $logs = $logs->where('provider', $provider);
            }

            return response()->json([
                'success' => true,
                'data' => $logs->values()->all(),
                'meta' => [
                    'total' => $logs->count(),
                    'limit' => $limit,
                    'filters' => [
                        'level' => $level,
                        'provider' => $provider
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve logs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check database connectivity
     */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            return ['status' => 'healthy', 'message' => 'Database connection successful'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => 'Database connection failed: ' . $e->getMessage()];
        }
    }

    /**
     * Check Redis connectivity
     */
    private function checkRedis(): array
    {
        try {
            Redis::ping();
            return ['status' => 'healthy', 'message' => 'Redis connection successful'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => 'Redis connection failed: ' . $e->getMessage()];
        }
    }

    /**
     * Check AI configurations
     */
    private function checkConfigurations(): array
    {
        try {
            $total = AIConfiguration::count();
            $active = AIConfiguration::active()->count();

            return [
                'status' => $active > 0 ? 'healthy' : 'warning',
                'message' => "Active configurations: {$active}/{$total}",
                'data' => ['total' => $total, 'active' => $active]
            ];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => 'Configuration check failed: ' . $e->getMessage()];
        }
    }

    /**
     * Check feature flags
     */
    private function checkFeatureFlags(): array
    {
        try {
            $stats = $this->featureFlagService->getStats();
            return [
                'status' => 'healthy',
                'message' => "Feature flags operational",
                'data' => $stats
            ];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => 'Feature flags check failed: ' . $e->getMessage()];
        }
    }

    /**
     * Check AI providers
     */
    private function checkProviders(): array
    {
        try {
            $providers = AIConfiguration::active()->get()->map(function ($config) {
                return [
                    'name' => $config->name,
                    'provider' => $config->provider,
                    'healthy' => $this->isProviderHealthy($config)
                ];
            });

            $healthyCount = $providers->where('healthy', true)->count();
            $totalCount = $providers->count();

            return [
                'status' => $healthyCount > 0 ? 'healthy' : 'unhealthy',
                'message' => "Healthy providers: {$healthyCount}/{$totalCount}",
                'providers' => $providers->toArray()
            ];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => 'Provider check failed: ' . $e->getMessage()];
        }
    }

    /**
     * Check queue system
     */
    private function checkQueue(): array
    {
        try {
            // Mock queue check - in production, check actual queue system
            return [
                'status' => 'healthy',
                'message' => 'Queue system operational',
                'data' => ['workers' => 3, 'pending_jobs' => 0, 'failed_jobs' => 0]
            ];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => 'Queue check failed: ' . $e->getMessage()];
        }
    }

    private function getOverviewMetrics(string $timeframe): array
    {
        // Mock data - in production, query actual metrics
        return [
            'total_requests' => 1250,
            'successful_requests' => 1180,
            'failed_requests' => 70,
            'average_response_time' => 850, // ms
            'success_rate' => 94.4
        ];
    }

    private function getPerformanceMetrics(string $timeframe): array
    {
        // Mock data
        return [
            'avg_processing_time' => 1200, // ms
            'p95_processing_time' => 2500,
            'p99_processing_time' => 4200,
            'throughput' => 15.2, // requests per minute
        ];
    }

    private function getUsageMetrics(string $timeframe, ?string $provider): array
    {
        // Mock data
        return [
            'by_feature' => [
                'categorization' => 450,
                'sentiment' => 320,
                'suggestions' => 280,
                'prioritization' => 200
            ],
            'by_provider' => [
                'openai' => 600,
                'claude' => 350,
                'gemini' => 300
            ]
        ];
    }

    private function getErrorMetrics(string $timeframe): array
    {
        // Mock data
        return [
            'by_type' => [
                'timeout' => 25,
                'api_limit' => 18,
                'invalid_response' => 15,
                'network_error' => 12
            ],
            'by_provider' => [
                'openai' => 30,
                'claude' => 25,
                'gemini' => 15
            ]
        ];
    }

    private function isProviderHealthy(AIConfiguration $config): bool
    {
        // Check if provider has been used recently and has good success rate
        if (!$config->last_used_at || $config->last_used_at->lt(now()->subHour())) {
            return true; // Not used recently, consider healthy
        }

        if ($config->requests_count === 0) {
            return true; // No requests yet
        }

        $successRate = ($config->success_count / $config->requests_count) * 100;
        return $successRate >= 80; // Consider healthy if success rate >= 80%
    }

    private function getQueueStats(string $queueName): array
    {
        // Mock data - in production, query actual queue system
        return [
            'name' => $queueName,
            'pending' => rand(0, 10),
            'processing' => rand(0, 3),
            'failed' => rand(0, 2),
            'completed_today' => rand(100, 500)
        ];
    }

    private function getWorkerStatus(): array
    {
        // Mock data
        return [
            'total' => 3,
            'active' => 3,
            'idle' => 0,
            'failed' => 0
        ];
    }
}