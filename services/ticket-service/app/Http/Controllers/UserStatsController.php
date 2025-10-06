<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class UserStatsController extends Controller
{
    /**
     * Get ticket statistics for all users (aggregated)
     *
     * This replaces making N separate queries with a single efficient GROUP BY query
     *
     * @return JsonResponse
     */
    public function getUserTicketStats(Request $request): JsonResponse
    {
        try {
            // Optional: Cache the results for 5 minutes to reduce database load
            $cacheKey = 'user_ticket_stats';
            $cacheDuration = 300; // 5 minutes

            // Check if we should bypass cache (for real-time updates)
            $useCache = !$request->has('no_cache');

            $stats = $useCache
                ? Cache::remember($cacheKey, $cacheDuration, function () {
                    return $this->calculateUserStats();
                })
                : $this->calculateUserStats();

            return response()->json([
                'success' => true,
                'data' => $stats,
                'cached' => $useCache,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch user ticket statistics',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Calculate ticket statistics for all users
     *
     * Uses a single SQL query with GROUP BY for efficiency
     *
     * @return array
     */
    protected function calculateUserStats(): array
    {
        // Single efficient query that groups tickets by assigned agent
        $results = DB::table('tickets')
            ->select([
                'assigned_agent_id',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_count"),
                DB::raw("SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_count"),
                DB::raw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count"),
                DB::raw("SUM(CASE WHEN status = 'on_hold' THEN 1 ELSE 0 END) as on_hold_count"),
                DB::raw("SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_count"),
                DB::raw("SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_count"),
                DB::raw("SUM(CASE WHEN status IN ('new', 'open', 'pending', 'on_hold') THEN 1 ELSE 0 END) as open_total"),
                DB::raw("SUM(CASE WHEN status IN ('resolved', 'closed') THEN 1 ELSE 0 END) as closed_total"),
            ])
            ->whereNotNull('assigned_agent_id')
            ->where('is_deleted', false) // Only count active tickets
            ->groupBy('assigned_agent_id')
            ->get();

        // Transform to associative array for easy lookup
        $statsMap = [];
        foreach ($results as $row) {
            $statsMap[$row->assigned_agent_id] = [
                'user_id' => $row->assigned_agent_id,
                'total' => (int) $row->total,
                'open' => (int) $row->open_total,
                'closed' => (int) $row->closed_total,
                'breakdown' => [
                    'new' => (int) $row->new_count,
                    'open' => (int) $row->open_count,
                    'pending' => (int) $row->pending_count,
                    'on_hold' => (int) $row->on_hold_count,
                    'resolved' => (int) $row->resolved_count,
                    'closed' => (int) $row->closed_count,
                ],
            ];
        }

        return $statsMap;
    }

    /**
     * Get ticket statistics for a specific user
     *
     * @param string $userId
     * @return JsonResponse
     */
    public function getUserTicketStatsById(Request $request, string $userId): JsonResponse
    {
        try {
            $stats = DB::table('tickets')
                ->select([
                    DB::raw('COUNT(*) as total'),
                    DB::raw("SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_count"),
                    DB::raw("SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_count"),
                    DB::raw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count"),
                    DB::raw("SUM(CASE WHEN status = 'on_hold' THEN 1 ELSE 0 END) as on_hold_count"),
                    DB::raw("SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_count"),
                    DB::raw("SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_count"),
                ])
                ->where('assigned_agent_id', $userId)
                ->where('is_deleted', false)
                ->first();

            if (!$stats) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'user_id' => $userId,
                        'total' => 0,
                        'open' => 0,
                        'closed' => 0,
                    ],
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'user_id' => $userId,
                    'total' => (int) $stats->total,
                    'open' => (int) ($stats->new_count + $stats->open_count + $stats->pending_count + $stats->on_hold_count),
                    'closed' => (int) ($stats->resolved_count + $stats->closed_count),
                    'breakdown' => [
                        'new' => (int) $stats->new_count,
                        'open' => (int) $stats->open_count,
                        'pending' => (int) $stats->pending_count,
                        'on_hold' => (int) $stats->on_hold_count,
                        'resolved' => (int) $stats->resolved_count,
                        'closed' => (int) $stats->closed_count,
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch user ticket statistics',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
