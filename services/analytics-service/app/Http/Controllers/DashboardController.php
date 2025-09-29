<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsEvent;
use App\Models\TicketMetrics;
use App\Models\AgentMetrics;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        try {
            // Fetch real data from database
            $totalTickets = DB::connection('pgsql')->table('tickets')->count();
            $openTickets = DB::connection('pgsql')->table('tickets')->where('status', 'open')->count();
            $resolvedTickets = DB::connection('pgsql')->table('tickets')->where('status', 'resolved')->count();
            $pendingTickets = DB::connection('pgsql')->table('tickets')->where('status', 'pending')->count();
            $newTicketsToday = DB::connection('pgsql')->table('tickets')
                ->where('status', 'new')
                ->whereDate('created_at', Carbon::today())
                ->count();
            $resolvedToday = DB::connection('pgsql')->table('tickets')
                ->where('status', 'resolved')
                ->whereDate('updated_at', Carbon::today())
                ->count();

            // Count active customers (users with role 'customer' who have tickets)
            $activeCustomers = DB::connection('pgsql')->table('users')
                ->where('role', 'customer')
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                          ->from('tickets')
                          ->whereRaw('tickets.client_id = users.id');
                })
                ->count();

            // Count active agents
            $activeAgents = DB::connection('pgsql')->table('users')->where('role', 'agent')->count();

            // Calculate week-over-week changes
            $lastWeekStart = Carbon::now()->subWeek()->startOfWeek();
            $lastWeekEnd = Carbon::now()->subWeek()->endOfWeek();
            $thisWeekStart = Carbon::now()->startOfWeek();

            $openTicketsLastWeek = DB::connection('pgsql')->table('tickets')
                ->where('status', 'open')
                ->whereBetween('created_at', [$lastWeekStart, $lastWeekEnd])
                ->count();

            $pendingTicketsLastWeek = DB::connection('pgsql')->table('tickets')
                ->where('status', 'pending')
                ->whereBetween('created_at', [$lastWeekStart, $lastWeekEnd])
                ->count();

            $activeCustomersLastWeek = DB::connection('pgsql')->table('users')
                ->where('role', 'customer')
                ->whereBetween('created_at', [$lastWeekStart, $lastWeekEnd])
                ->count();

            // Calculate percentage changes
            $openTicketsChange = $openTicketsLastWeek > 0 ?
                round((($openTickets - $openTicketsLastWeek) / $openTicketsLastWeek) * 100, 1) : 0;
            $pendingTicketsChange = $pendingTicketsLastWeek > 0 ?
                round((($pendingTickets - $pendingTicketsLastWeek) / $pendingTicketsLastWeek) * 100, 1) : 0;
            $activeCustomersChange = $activeCustomersLastWeek > 0 ?
                round((($activeCustomers - $activeCustomersLastWeek) / $activeCustomersLastWeek) * 100, 1) : 0;

            // Calculate real average response time first
            $avgResponseMinutes = DB::connection('pgsql')->table('tickets')
                ->whereNotNull('first_response_at')
                ->whereNotNull('created_at')
                ->selectRaw('AVG(EXTRACT(EPOCH FROM (first_response_at - created_at))/60) as avg_minutes')
                ->value('avg_minutes');

            $avgResponseHours = $avgResponseMinutes ? round($avgResponseMinutes / 60, 1) : 0;

            // Calculate real average resolution time
            $avgResolutionMinutes = DB::connection('pgsql')->table('tickets')
                ->whereNotNull('resolved_at')
                ->whereNotNull('created_at')
                ->selectRaw('AVG(EXTRACT(EPOCH FROM (resolved_at - created_at))/60) as avg_minutes')
                ->value('avg_minutes');

            $avgResolutionHours = $avgResolutionMinutes ? round($avgResolutionMinutes / 60, 1) : 0;

            // Calculate average response time change from last week
            $avgResponseMinutesLastWeek = DB::connection('pgsql')->table('tickets')
                ->whereNotNull('first_response_at')
                ->whereNotNull('created_at')
                ->whereBetween('created_at', [$lastWeekStart, $lastWeekEnd])
                ->selectRaw('AVG(EXTRACT(EPOCH FROM (first_response_at - created_at))/60) as avg_minutes')
                ->value('avg_minutes');

            $avgResponseHoursLastWeek = $avgResponseMinutesLastWeek ? round($avgResponseMinutesLastWeek / 60, 1) : 0;

            $avgResponseTimeChange = ($avgResponseHoursLastWeek > 0 && $avgResponseHours > 0) ?
                round((($avgResponseHours - $avgResponseHoursLastWeek) / $avgResponseHoursLastWeek) * 100, 1) : 0;

            // Priority distribution
            $priorityDistribution = DB::connection('pgsql')->table('tickets')
                ->select('priority', DB::raw('count(*) as count'))
                ->groupBy('priority')
                ->get()
                ->map(function ($item) use ($totalTickets) {
                    return [
                        'priority' => $item->priority,
                        'count' => $item->count,
                        'percentage' => $totalTickets > 0 ? round(($item->count / $totalTickets) * 100, 1) : 0
                    ];
                })
                ->toArray();

            // Status distribution
            $statusDistribution = DB::connection('pgsql')->table('tickets')
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->get()
                ->map(function ($item) {
                    return [
                        'status' => $item->status,
                        'count' => $item->count
                    ];
                })
                ->toArray();


            $data = [
                'total_tickets' => $totalTickets,
                'open_tickets' => $openTickets,
                'resolved_tickets' => $resolvedTickets,
                'pending_tickets' => $pendingTickets,
                'active_customers' => $activeCustomers,
                'avg_response_time' => $avgResponseHours . ' hrs',
                'period' => 'today',

                // Week-over-week changes
                'open_tickets_change' => $openTicketsChange,
                'pending_tickets_change' => $pendingTicketsChange,
                'active_customers_change' => $activeCustomersChange,
                'avg_response_time_change' => $avgResponseTimeChange,

                // Additional stats for dashboard widgets
                'new_tickets_today' => $newTicketsToday,
                'tickets_resolved_today' => $resolvedToday,
                'average_resolution_time' => $avgResolutionHours . ' hrs',
                'customer_satisfaction' => 4.6, // Would need actual rating calculation
                'active_agents' => $activeAgents,

                // Priority distribution for pie chart
                'priority_distribution' => $priorityDistribution,

                // Status distribution
                'status_distribution' => $statusDistribution,

                // Response time metrics
                'response_metrics' => [
                    'first_response_time' => $avgResponseHours . ' hrs',
                    'avg_resolution_time' => $avgResolutionHours . ' hrs',
                    'sla_compliance' => 87.5 // Would need actual SLA calculation
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function ticketTrends(Request $request): JsonResponse
    {
        try {
            $days = $request->get('days', 7);
            $data = [];

            // Get real trend data for the last N days
            for ($i = $days - 1; $i >= 0; $i--) {
                $date = Carbon::now()->subDays($i);

                // Get actual ticket counts for this date
                $ticketCount = DB::connection('pgsql')->table('tickets')
                    ->whereDate('created_at', $date->format('Y-m-d'))
                    ->count();

                $resolvedCount = DB::connection('pgsql')->table('tickets')
                    ->whereDate('updated_at', $date->format('Y-m-d'))
                    ->where('status', 'resolved')
                    ->count();

                $newCount = DB::connection('pgsql')->table('tickets')
                    ->whereDate('created_at', $date->format('Y-m-d'))
                    ->where('status', 'new')
                    ->count();

                $openCount = DB::connection('pgsql')->table('tickets')
                    ->whereDate('created_at', $date->format('Y-m-d'))
                    ->where('status', 'open')
                    ->count();

                $data[] = [
                    'date' => $date->format('Y-m-d'),
                    'tickets' => $ticketCount,
                    'resolved' => $resolvedCount,
                    'new' => $newCount,
                    'open' => $openCount,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function activityFeed(Request $request): JsonResponse
    {
        $data = [
            ['id' => 1, 'activity' => 'Ticket created', 'time' => '2024-09-26 11:30:00']
        ];

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function slaCompliance(Request $request): JsonResponse
    {
        $data = [
            'compliance_rate' => 87.5,
            'total_tickets' => 100,
            'compliant_tickets' => 87
        ];

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function agentPerformance(Request $request): JsonResponse
    {
        $data = [
            ['agent_id' => 1, 'name' => 'Agent 1', 'tickets_resolved' => 25]
        ];

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }
}
