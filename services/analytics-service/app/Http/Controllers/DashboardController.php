<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsEvent;
use App\Models\TicketMetrics;
use App\Models\AgentMetrics;
use App\Services\BusinessHoursService;
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
            $businessHours = new BusinessHoursService();

            // Define time periods for better, actionable metrics
            $now = Carbon::now();
            $last30Days = $now->copy()->subDays(30);
            $last60Days = $now->copy()->subDays(60);
            $last30To60Days = $now->copy()->subDays(60);

            // CURRENT STATUS - All time totals for context
            $totalTickets = DB::connection('pgsql')->table('tickets')->where('is_archived', false)->count();
            $openTickets = DB::connection('pgsql')->table('tickets')->where('status', 'open')->where('is_archived', false)->count();
            $resolvedTickets = DB::connection('pgsql')->table('tickets')->where('status', 'resolved')->where('is_archived', false)->count();
            $pendingTickets = DB::connection('pgsql')->table('tickets')->where('status', 'pending')->where('is_archived', false)->count();

            // TODAY'S ACTIVITY
            $newTicketsToday = DB::connection('pgsql')->table('tickets')
                ->whereDate('created_at', Carbon::today())
                ->where('is_archived', false)
                ->count();
            $resolvedToday = DB::connection('pgsql')->table('tickets')
                ->where('status', 'resolved')
                ->whereDate('updated_at', Carbon::today())
                ->where('is_archived', false)
                ->count();

            // Active customers and agents
            $activeCustomers = DB::connection('pgsql')->table('clients')->count();
            $activeAgents = DB::connection('pgsql')->table('users')
                ->whereIn('role', ['agent', 'admin', 'manager'])
                ->where('is_active', true)
                ->count();

            // ==========================================
            // IMPROVED: Average Response Time (Last 30 Days)
            // ==========================================

            // Get tickets from last 30 days with response times
            $recentTicketsWithResponse = DB::connection('pgsql')->table('tickets')
                ->whereNotNull('first_response_at')
                ->whereNotNull('created_at')
                ->where('created_at', '>=', $last30Days)
                ->where('is_archived', false)
                ->select('created_at', 'first_response_at')
                ->get();

            // Calculate business-hours-aware response time
            $totalBusinessHours = 0;
            $ticketCount = 0;

            foreach ($recentTicketsWithResponse as $ticket) {
                $businessHoursElapsed = $businessHours->calculateResponseTime(
                    $ticket->created_at,
                    $ticket->first_response_at
                );
                $totalBusinessHours += $businessHoursElapsed;
                $ticketCount++;
            }

            $avgResponseHours = $ticketCount > 0 ? round($totalBusinessHours / $ticketCount, 1) : 0;

            // ==========================================
            // COMPARISON: Previous 30 Days (30-60 days ago)
            // ==========================================

            $previousTicketsWithResponse = DB::connection('pgsql')->table('tickets')
                ->whereNotNull('first_response_at')
                ->whereNotNull('created_at')
                ->whereBetween('created_at', [$last60Days, $last30Days])
                ->where('is_archived', false)
                ->select('created_at', 'first_response_at')
                ->get();

            $previousTotalBusinessHours = 0;
            $previousTicketCount = 0;

            foreach ($previousTicketsWithResponse as $ticket) {
                $businessHoursElapsed = $businessHours->calculateResponseTime(
                    $ticket->created_at,
                    $ticket->first_response_at
                );
                $previousTotalBusinessHours += $businessHoursElapsed;
                $previousTicketCount++;
            }

            $avgResponseHoursPrevious = $previousTicketCount > 0
                ? round($previousTotalBusinessHours / $previousTicketCount, 1)
                : 0;

            // Calculate percentage change
            $avgResponseTimeChange = ($avgResponseHoursPrevious > 0 && $avgResponseHours > 0)
                ? round((($avgResponseHours - $avgResponseHoursPrevious) / $avgResponseHoursPrevious) * 100, 1)
                : 0;

            // ==========================================
            // Resolution Time (Last 30 Days)
            // ==========================================

            $recentResolvedTickets = DB::connection('pgsql')->table('tickets')
                ->whereNotNull('resolved_at')
                ->whereNotNull('created_at')
                ->where('created_at', '>=', $last30Days)
                ->where('is_archived', false)
                ->select('created_at', 'resolved_at')
                ->get();

            $totalResolutionHours = 0;
            $resolvedCount = 0;

            foreach ($recentResolvedTickets as $ticket) {
                $hoursElapsed = $businessHours->calculateResponseTime(
                    $ticket->created_at,
                    $ticket->resolved_at
                );
                $totalResolutionHours += $hoursElapsed;
                $resolvedCount++;
            }

            $avgResolutionHours = $resolvedCount > 0 ? round($totalResolutionHours / $resolvedCount, 1) : 0;

            // ==========================================
            // TICKET TRENDS - Last 30 days comparison
            // ==========================================

            $openTicketsLast30 = DB::connection('pgsql')->table('tickets')
                ->where('status', 'open')
                ->where('created_at', '>=', $last30Days)
                ->where('is_archived', false)
                ->count();

            $openTicketsPrevious30 = DB::connection('pgsql')->table('tickets')
                ->where('status', 'open')
                ->whereBetween('created_at', [$last60Days, $last30Days])
                ->where('is_archived', false)
                ->count();

            $openTicketsChange = $openTicketsPrevious30 > 0
                ? round((($openTicketsLast30 - $openTicketsPrevious30) / $openTicketsPrevious30) * 100, 1)
                : 0;

            $pendingTicketsLast30 = DB::connection('pgsql')->table('tickets')
                ->where('status', 'pending')
                ->where('created_at', '>=', $last30Days)
                ->where('is_archived', false)
                ->count();

            $pendingTicketsPrevious30 = DB::connection('pgsql')->table('tickets')
                ->where('status', 'pending')
                ->whereBetween('created_at', [$last60Days, $last30Days])
                ->where('is_archived', false)
                ->count();

            $pendingTicketsChange = $pendingTicketsPrevious30 > 0
                ? round((($pendingTicketsLast30 - $pendingTicketsPrevious30) / $pendingTicketsPrevious30) * 100, 1)
                : 0;

            $resolvedTicketsLast30 = DB::connection('pgsql')->table('tickets')
                ->where('status', 'resolved')
                ->where('updated_at', '>=', $last30Days)
                ->where('is_archived', false)
                ->count();

            $resolvedTicketsPrevious30 = DB::connection('pgsql')->table('tickets')
                ->where('status', 'resolved')
                ->whereBetween('updated_at', [$last60Days, $last30Days])
                ->where('is_archived', false)
                ->count();

            $resolvedTicketsChange = $resolvedTicketsPrevious30 > 0
                ? round((($resolvedTicketsLast30 - $resolvedTicketsPrevious30) / $resolvedTicketsPrevious30) * 100, 1)
                : 0;

            // Priority distribution (last 30 days for actionable data)
            $priorityDistribution = DB::connection('pgsql')->table('tickets')
                ->where('created_at', '>=', $last30Days)
                ->where('is_archived', false)
                ->select('priority', DB::raw('count(*) as count'))
                ->groupBy('priority')
                ->get()
                ->map(function ($item) use ($ticketCount) {
                    $total = $ticketCount > 0 ? $ticketCount : 1;
                    return [
                        'priority' => $item->priority,
                        'count' => $item->count,
                        'percentage' => round(($item->count / $total) * 100, 1)
                    ];
                })
                ->toArray();

            // Status distribution (current)
            $statusDistribution = DB::connection('pgsql')->table('tickets')
                ->where('is_archived', false)
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
                // Current totals
                'total_tickets' => $totalTickets,
                'open_tickets' => $openTickets,
                'resolved_tickets' => $resolvedTickets,
                'pending_tickets' => $pendingTickets,
                'active_customers' => $activeCustomers,
                'active_agents' => $activeAgents,

                // IMPROVED: Response time with business hours (last 30 days)
                'avg_response_time' => $avgResponseHours . ' hrs',
                'avg_response_time_raw' => $avgResponseHours,
                'avg_response_time_calculation' => 'business_hours',
                'avg_response_time_period' => 'last_30_days',
                'avg_response_time_change' => $avgResponseTimeChange,

                // Resolution time
                'average_resolution_time' => $avgResolutionHours . ' hrs',
                'average_resolution_time_raw' => $avgResolutionHours,

                // Trend changes (30-day comparison)
                'open_tickets_change' => $openTicketsChange,
                'pending_tickets_change' => $pendingTicketsChange,
                'resolved_tickets_change' => $resolvedTicketsChange,

                // Today's activity
                'new_tickets_today' => $newTicketsToday,
                'tickets_resolved_today' => $resolvedToday,

                // Distributions
                'priority_distribution' => $priorityDistribution,
                'status_distribution' => $statusDistribution,

                // Response time metrics
                'response_metrics' => [
                    'first_response_time' => $avgResponseHours . ' hrs',
                    'avg_resolution_time' => $avgResolutionHours . ' hrs',
                    'tickets_with_response' => $ticketCount,
                    'tickets_resolved' => $resolvedCount,
                    'calculation_method' => 'business_hours_aware',
                    'period' => 'last_30_days'
                ],

                // Metadata
                'period' => 'last_30_days',
                'comparison_period' => '30_to_60_days_ago',
                'business_hours_enabled' => true,
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
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
                    ->where('is_archived', false)
                    ->count();

                $resolvedCount = DB::connection('pgsql')->table('tickets')
                    ->whereDate('updated_at', $date->format('Y-m-d'))
                    ->where('status', 'resolved')
                    ->where('is_archived', false)
                    ->count();

                $newCount = DB::connection('pgsql')->table('tickets')
                    ->whereDate('created_at', $date->format('Y-m-d'))
                    ->where('status', 'new')
                    ->where('is_archived', false)
                    ->count();

                $openCount = DB::connection('pgsql')->table('tickets')
                    ->whereDate('created_at', $date->format('Y-m-d'))
                    ->where('status', 'open')
                    ->where('is_archived', false)
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
        try {
            $businessHours = new BusinessHoursService();
            $agentId = $request->get('agent_id'); // Optional: filter by specific agent
            $days = $request->get('days', 30); // Default to last 30 days
            $startDate = Carbon::now()->subDays($days);

            // Build query for agent performance
            $query = DB::connection('pgsql')
                ->table('tickets')
                ->join('users', 'tickets.assigned_agent_id', '=', 'users.id')
                ->where('tickets.created_at', '>=', $startDate)
                ->where('tickets.is_archived', false)
                ->whereNotNull('tickets.assigned_agent_id');

            if ($agentId) {
                $query->where('tickets.assigned_agent_id', $agentId);
            }

            // Get tickets per agent
            $agentTickets = $query
                ->select(
                    'users.id as agent_id',
                    'users.name as agent_name',
                    'users.email as agent_email',
                    'tickets.id as ticket_id',
                    'tickets.created_at',
                    'tickets.first_response_at',
                    'tickets.resolved_at',
                    'tickets.status'
                )
                ->get()
                ->groupBy('agent_id');

            $agentPerformance = [];

            foreach ($agentTickets as $agentId => $tickets) {
                $totalResponseTime = 0;
                $responseCount = 0;
                $totalResolutionTime = 0;
                $resolutionCount = 0;
                $ticketsResolved = 0;
                $ticketsOpen = 0;
                $ticketsPending = 0;

                foreach ($tickets as $ticket) {
                    // Calculate response time (business hours)
                    if ($ticket->first_response_at && $ticket->created_at) {
                        $responseTime = $businessHours->calculateResponseTime(
                            $ticket->created_at,
                            $ticket->first_response_at
                        );
                        $totalResponseTime += $responseTime;
                        $responseCount++;
                    }

                    // Calculate resolution time (business hours)
                    if ($ticket->resolved_at && $ticket->created_at) {
                        $resolutionTime = $businessHours->calculateResponseTime(
                            $ticket->created_at,
                            $ticket->resolved_at
                        );
                        $totalResolutionTime += $resolutionTime;
                        $resolutionCount++;
                        $ticketsResolved++;
                    }

                    // Count by status
                    if ($ticket->status === 'open') $ticketsOpen++;
                    if ($ticket->status === 'pending') $ticketsPending++;
                }

                $avgResponseTime = $responseCount > 0 ? round($totalResponseTime / $responseCount, 1) : 0;
                $avgResolutionTime = $resolutionCount > 0 ? round($totalResolutionTime / $resolutionCount, 1) : 0;
                $totalAssigned = count($tickets);

                $agentPerformance[] = [
                    'agent_id' => $agentId,
                    'agent_name' => $tickets->first()->agent_name,
                    'agent_email' => $tickets->first()->agent_email,
                    'total_tickets_assigned' => $totalAssigned,
                    'tickets_resolved' => $ticketsResolved,
                    'tickets_open' => $ticketsOpen,
                    'tickets_pending' => $ticketsPending,
                    'avg_response_time_hours' => $avgResponseTime,
                    'avg_resolution_time_hours' => $avgResolutionTime,
                    'resolution_rate' => $totalAssigned > 0 ? round(($ticketsResolved / $totalAssigned) * 100, 1) : 0,
                    'tickets_with_response' => $responseCount,
                    'period_days' => $days
                ];
            }

            // Sort by resolution rate (best performers first)
            usort($agentPerformance, function ($a, $b) {
                return $b['tickets_resolved'] <=> $a['tickets_resolved'];
            });

            return response()->json([
                'success' => true,
                'data' => $agentPerformance,
                'period' => "last_{$days}_days",
                'calculation_method' => 'business_hours_aware'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    /**
     * Get individual agent's detailed performance metrics
     *
     * @param Request $request
     * @param string $agentId
     * @return JsonResponse
     */
    public function agentDetailedMetrics(Request $request, string $agentId): JsonResponse
    {
        try {
            $businessHours = new BusinessHoursService();
            $days = $request->get('days', 30);
            $startDate = Carbon::now()->subDays($days);

            // Get agent info
            $agent = DB::connection('pgsql')->table('users')
                ->where('id', $agentId)
                ->first();

            if (!$agent) {
                return response()->json([
                    'success' => false,
                    'error' => 'Agent not found'
                ], 404);
            }

            // Get agent's tickets
            $tickets = DB::connection('pgsql')->table('tickets')
                ->where('assigned_agent_id', $agentId)
                ->where('created_at', '>=', $startDate)
                ->where('is_archived', false)
                ->select('id', 'ticket_number', 'subject', 'status', 'priority', 'created_at', 'first_response_at', 'resolved_at')
                ->get();

            // Calculate metrics
            $totalResponseTime = 0;
            $responseCount = 0;
            $totalResolutionTime = 0;
            $resolutionCount = 0;
            $statusBreakdown = [
                'new' => 0,
                'open' => 0,
                'pending' => 0,
                'resolved' => 0,
                'closed' => 0,
                'cancelled' => 0
            ];
            $priorityBreakdown = [
                'low' => 0,
                'medium' => 0,
                'high' => 0,
                'urgent' => 0
            ];

            foreach ($tickets as $ticket) {
                // Response time
                if ($ticket->first_response_at && $ticket->created_at) {
                    $responseTime = $businessHours->calculateResponseTime(
                        $ticket->created_at,
                        $ticket->first_response_at
                    );
                    $totalResponseTime += $responseTime;
                    $responseCount++;
                }

                // Resolution time
                if ($ticket->resolved_at && $ticket->created_at) {
                    $resolutionTime = $businessHours->calculateResponseTime(
                        $ticket->created_at,
                        $ticket->resolved_at
                    );
                    $totalResolutionTime += $resolutionTime;
                    $resolutionCount++;
                }

                // Status breakdown
                if (isset($statusBreakdown[$ticket->status])) {
                    $statusBreakdown[$ticket->status]++;
                }

                // Priority breakdown
                if (isset($priorityBreakdown[$ticket->priority])) {
                    $priorityBreakdown[$ticket->priority]++;
                }
            }

            $avgResponseTime = $responseCount > 0 ? round($totalResponseTime / $responseCount, 1) : 0;
            $avgResolutionTime = $resolutionCount > 0 ? round($totalResolutionTime / $resolutionCount, 1) : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'agent' => [
                        'id' => $agent->id,
                        'name' => $agent->name,
                        'email' => $agent->email,
                        'role' => $agent->role
                    ],
                    'metrics' => [
                        'total_tickets' => count($tickets),
                        'avg_response_time_hours' => $avgResponseTime,
                        'avg_resolution_time_hours' => $avgResolutionTime,
                        'tickets_resolved' => $statusBreakdown['resolved'],
                        'resolution_rate' => count($tickets) > 0 ? round(($statusBreakdown['resolved'] / count($tickets)) * 100, 1) : 0,
                        'tickets_with_response' => $responseCount
                    ],
                    'status_breakdown' => $statusBreakdown,
                    'priority_breakdown' => $priorityBreakdown,
                    'period_days' => $days,
                    'calculation_method' => 'business_hours_aware'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get agent's personal work queue
     * Returns tickets categorized by urgency/status for the logged-in agent
     */
    public function agentQueue(Request $request): JsonResponse
    {
        try {
            // In production, get from authenticated user
            $agentId = $request->get('agent_id') ?? $request->header('X-User-Id');

            if (!$agentId) {
                return response()->json([
                    'success' => false,
                    'error' => 'Agent ID required'
                ], 400);
            }

            $now = Carbon::now();

            // Get only open tickets assigned to this agent
            $tickets = DB::connection('pgsql')->table('tickets')
                ->where('assigned_agent_id', $agentId)
                ->where('status', 'open')
                ->where('is_archived', false)
                ->select('id', 'ticket_number', 'subject', 'status', 'priority', 'source',
                         'client_id', 'created_at', 'first_response_at', 'resolution_due_at',
                         'updated_at', 'tags')
                ->get();

            // Categorize tickets
            $urgent = [];
            $overdue = [];
            $unread = [];
            $active = [];

            foreach ($tickets as $ticket) {
                // Check if urgent priority
                if ($ticket->priority === 'urgent') {
                    $urgent[] = $ticket;
                }

                // Check if overdue (past SLA)
                if ($ticket->resolution_due_at && Carbon::parse($ticket->resolution_due_at)->isPast()) {
                    $overdue[] = $ticket;
                }

                // Check for unread client responses
                $unreadCount = DB::connection('pgsql')->table('ticket_comments')
                    ->where('ticket_id', $ticket->id)
                    ->whereNotNull('client_id')
                    ->where('is_internal_note', false)
                    ->where(function($query) use ($ticket) {
                        $query->whereNull('is_read')
                              ->orWhere('is_read', false);
                    })
                    ->where('created_at', '>', $ticket->first_response_at ?? $ticket->created_at)
                    ->count();

                if ($unreadCount > 0) {
                    $ticket->unread_count = $unreadCount;
                    $unread[] = $ticket;
                }

                // All active tickets
                $active[] = $ticket;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'urgent' => array_values(array_slice($urgent, 0, 20)),
                    'overdue' => array_values(array_slice($overdue, 0, 20)),
                    'unread' => array_values(array_slice($unread, 0, 20)),
                    'active' => array_values(array_slice($active, 0, 50)),
                    'counts' => [
                        'urgent' => count($urgent),
                        'overdue' => count($overdue),
                        'unread' => count($unread),
                        'active' => count($active)
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get agent's personal statistics (today + current metrics)
     */
    public function agentStats(Request $request): JsonResponse
    {
        try {
            $agentId = $request->get('agent_id') ?? $request->header('X-User-Id');

            if (!$agentId) {
                return response()->json([
                    'success' => false,
                    'error' => 'Agent ID required'
                ], 400);
            }

            $businessHours = new BusinessHoursService();
            $today = Carbon::today();
            $now = Carbon::now();

            // Current assigned tickets (open only)
            $assignedCount = DB::connection('pgsql')->table('tickets')
                ->where('assigned_agent_id', $agentId)
                ->where('status', 'open')
                ->where('is_archived', false)
                ->count();

            // Resolved today
            $resolvedToday = DB::connection('pgsql')->table('tickets')
                ->where('assigned_agent_id', $agentId)
                ->whereDate('resolved_at', $today)
                ->where('is_archived', false)
                ->count();

            // Comments sent today (public replies only)
            $commentsSentToday = DB::connection('pgsql')->table('ticket_comments')
                ->where('user_id', $agentId)
                ->where('is_internal_note', false)
                ->whereDate('created_at', $today)
                ->count();

            // Average response time (last 30 days)
            $responseTimeTickets = DB::connection('pgsql')->table('tickets')
                ->where('assigned_agent_id', $agentId)
                ->whereNotNull('first_response_at')
                ->whereNotNull('created_at')
                ->where('created_at', '>=', $now->copy()->subDays(30))
                ->where('is_archived', false)
                ->select('created_at', 'first_response_at')
                ->get();

            $totalResponseTime = 0;
            $responseCount = 0;

            foreach ($responseTimeTickets as $ticket) {
                $responseTime = $businessHours->calculateResponseTime(
                    $ticket->created_at,
                    $ticket->first_response_at
                );
                $totalResponseTime += $responseTime;
                $responseCount++;
            }

            $avgResponseHours = $responseCount > 0 ? round($totalResponseTime / $responseCount, 1) : 0;

            // Format as hours:minutes
            $hours = floor($avgResponseHours);
            $minutes = round(($avgResponseHours - $hours) * 60);
            $avgResponseFormatted = sprintf('%dh %dm', $hours, $minutes);

            return response()->json([
                'success' => true,
                'data' => [
                    'assigned_to_me' => $assignedCount,
                    'resolved_today' => $resolvedToday,
                    'avg_response_time' => $avgResponseFormatted,
                    'avg_response_hours' => $avgResponseHours,
                    'active_replies' => $commentsSentToday
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get agent's recent activity feed
     */
    public function agentActivity(Request $request): JsonResponse
    {
        try {
            $agentId = $request->get('agent_id') ?? $request->header('X-User-Id');
            $limit = $request->get('limit', 20);

            if (!$agentId) {
                return response()->json([
                    'success' => false,
                    'error' => 'Agent ID required'
                ], 400);
            }

            // Get recent actions from ticket history
            $activities = DB::connection('pgsql')->table('ticket_history')
                ->where('user_id', $agentId)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->select('id', 'ticket_id', 'user_id', 'action', 'field_name', 'old_value', 'new_value', 'metadata', 'created_at')
                ->get();

            // Get user info
            $user = DB::connection('pgsql')->table('users')
                ->where('id', $agentId)
                ->select('name', 'email')
                ->first();

            // Format activities with ticket info and user details
            $formattedActivities = [];
            foreach ($activities as $activity) {
                $ticket = DB::connection('pgsql')->table('tickets')
                    ->where('id', $activity->ticket_id)
                    ->select('ticket_number', 'subject')
                    ->first();

                // Parse metadata if it's JSON
                $metadata = null;
                if ($activity->metadata) {
                    $metadata = json_decode($activity->metadata, true);
                }

                // Extract note content if this is a note/comment action
                $noteContent = null;
                if ($metadata && isset($metadata['content_preview'])) {
                    $noteContent = $metadata['content_preview'];
                }

                $formattedActivities[] = [
                    'id' => $activity->id,
                    'action' => $activity->action,
                    'ticket_number' => $ticket->ticket_number ?? 'Unknown',
                    'ticket_subject' => $ticket->subject ?? 'Unknown',
                    'field_name' => $activity->field_name,
                    'old_value' => $activity->old_value,
                    'new_value' => $activity->new_value,
                    'user_name' => $user->name ?? 'You',
                    'note_content' => $noteContent,
                    'metadata' => $metadata,
                    'created_at' => $activity->created_at,
                    'timestamp' => Carbon::parse($activity->created_at)->diffForHumans()
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $formattedActivities
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get agent's productivity metrics for the week
     */
    public function agentProductivity(Request $request): JsonResponse
    {
        try {
            $agentId = $request->get('agent_id') ?? $request->header('X-User-Id');

            if (!$agentId) {
                return response()->json([
                    'success' => false,
                    'error' => 'Agent ID required'
                ], 400);
            }

            $now = Carbon::now();
            $weekStart = $now->copy()->startOfWeek();

            // Get daily resolved counts for this week
            $dailyResolved = DB::connection('pgsql')->table('tickets')
                ->where('assigned_agent_id', $agentId)
                ->whereBetween('resolved_at', [$weekStart, $now])
                ->where('is_archived', false)
                ->select(DB::raw('DATE(resolved_at) as date'), DB::raw('COUNT(*) as count'))
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->keyBy('date');

            // Build 7-day array
            $weekData = [];
            for ($i = 0; $i < 7; $i++) {
                $date = $weekStart->copy()->addDays($i);
                $dateStr = $date->format('Y-m-d');
                $weekData[] = [
                    'day' => $date->format('D'),
                    'date' => $dateStr,
                    'count' => $dailyResolved[$dateStr]->count ?? 0,
                    'is_today' => $date->isToday()
                ];
            }

            // Calculate streak
            $streak = 0;
            $checkDate = Carbon::yesterday();
            while (true) {
                $dateStr = $checkDate->format('Y-m-d');
                $count = DB::connection('pgsql')->table('tickets')
                    ->where('assigned_agent_id', $agentId)
                    ->whereDate('resolved_at', $dateStr)
                    ->where('is_archived', false)
                    ->count();

                if ($count >= 1) {
                    $streak++;
                    $checkDate->subDay();
                } else {
                    break;
                }

                // Safety limit
                if ($streak > 30) break;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'week_data' => $weekData,
                    'streak_days' => $streak,
                    'total_this_week' => array_sum(array_column($weekData, 'count'))
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get latest client replies from agent's assigned tickets (one per ticket)
     * Excludes tickets where agent has already replied or ticket is closed/resolved
     */
    public function agentReplies(Request $request): JsonResponse
    {
        try {
            $agentId = $request->get('agent_id') ?? $request->header('X-User-Id');
            $limit = $request->get('limit', 20);

            if (!$agentId) {
                return response()->json([
                    'success' => false,
                    'error' => 'Agent ID required'
                ], 400);
            }

            // Get agent's assigned tickets (excluding closed/resolved)
            $assignedTickets = DB::connection('pgsql')->table('tickets')
                ->where('assigned_agent_id', $agentId)
                ->whereIn('status', ['open', 'pending', 'new'])
                ->where('is_archived', false)
                ->pluck('id')
                ->toArray();

            if (empty($assignedTickets)) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ]);
            }

            // Get tickets where client has replied AND agent hasn't replied after client's last reply
            $latestReplies = DB::connection('pgsql')
                ->select("
                    WITH latest_client_replies AS (
                        SELECT DISTINCT ON (ticket_id)
                            id,
                            ticket_id,
                            content,
                            created_at
                        FROM ticket_comments
                        WHERE ticket_id = ANY(?)
                            AND client_id IS NOT NULL
                            AND user_id IS NULL
                        ORDER BY ticket_id, created_at DESC
                    ),
                    latest_agent_replies AS (
                        SELECT DISTINCT ON (ticket_id)
                            ticket_id,
                            created_at
                        FROM ticket_comments
                        WHERE ticket_id = ANY(?)
                            AND user_id = ?
                            AND client_id IS NULL
                        ORDER BY ticket_id, created_at DESC
                    )
                    SELECT
                        lcr.id,
                        lcr.content,
                        lcr.created_at,
                        lcr.ticket_id,
                        t.ticket_number,
                        t.subject,
                        t.priority,
                        t.status,
                        c.name as client_name,
                        c.email as client_email
                    FROM latest_client_replies lcr
                    INNER JOIN tickets t ON lcr.ticket_id = t.id
                    INNER JOIN clients c ON t.client_id = c.id
                    LEFT JOIN latest_agent_replies lar ON lcr.ticket_id = lar.ticket_id
                    WHERE lar.ticket_id IS NULL OR lar.created_at < lcr.created_at
                    ORDER BY lcr.created_at DESC
                    LIMIT ?
                ", [
                    '{' . implode(',', $assignedTickets) . '}',
                    '{' . implode(',', $assignedTickets) . '}',
                    $agentId,
                    $limit
                ]);

            // Format the replies
            $formattedReplies = [];
            foreach ($latestReplies as $reply) {
                $formattedReplies[] = [
                    'id' => $reply->id,
                    'content' => $reply->content,
                    'content_preview' => strlen($reply->content) > 150
                        ? substr($reply->content, 0, 150) . '...'
                        : $reply->content,
                    'ticket_id' => $reply->ticket_id,
                    'ticket_number' => $reply->ticket_number,
                    'ticket_subject' => $reply->subject,
                    'ticket_priority' => $reply->priority,
                    'ticket_status' => $reply->status,
                    'client_name' => $reply->client_name ?? 'Unknown',
                    'client_email' => $reply->client_email ?? 'Unknown',
                    'created_at' => $reply->created_at,
                    'timestamp' => Carbon::parse($reply->created_at)->diffForHumans()
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $formattedReplies
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
