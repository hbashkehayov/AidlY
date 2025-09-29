<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class StatsController extends Controller
{
    /**
     * Get dashboard statistics
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDashboardStats()
    {
        try {
            // Basic ticket counts by status
            $ticketStats = DB::table('tickets')
                ->select('status', DB::raw('COUNT(*) as count'))
                ->where('is_deleted', false)
                ->groupBy('status')
                ->pluck('count', 'status');

            // Recent activity (last 24 hours)
            $recentActivity = DB::table('tickets')
                ->where('created_at', '>=', Carbon::now()->subDay())
                ->where('is_deleted', false)
                ->count();

            // Resolution statistics
            $resolvedToday = DB::table('tickets')
                ->where('resolved_at', '>=', Carbon::now()->startOfDay())
                ->where('is_deleted', false)
                ->count();

            // Average response time (in hours)
            $avgResponseTime = DB::table('tickets')
                ->whereNotNull('first_response_at')
                ->where('is_deleted', false)
                ->selectRaw('AVG(EXTRACT(EPOCH FROM (first_response_at - created_at))/3600) as avg_hours')
                ->first();

            // Active clients count (clients that are not deleted)
            $activeClients = DB::table('clients')
                ->where('is_deleted', false)
                ->count();

            // Priority distribution
            $priorityStats = DB::table('tickets')
                ->select('priority', DB::raw('COUNT(*) as count'))
                ->where('is_deleted', false)
                ->whereIn('status', ['new', 'open', 'pending'])
                ->groupBy('priority')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'open_tickets' => $ticketStats->get('open', 0),
                    'pending_tickets' => $ticketStats->get('pending', 0),
                    'new_tickets' => $ticketStats->get('new', 0),
                    'resolved_today' => $resolvedToday,
                    'avg_response_time' => round($avgResponseTime->avg_hours ?? 0, 1) . ' hrs',
                    'active_customers' => $activeClients,
                    'total_tickets' => array_sum($ticketStats->toArray()),
                    'priority_distribution' => $priorityStats,
                    'status_distribution' => $ticketStats
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get ticket trends for the past week
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTicketTrends()
    {
        try {
            $trends = [];

            for ($i = 6; $i >= 0; $i--) {
                $date = Carbon::now()->subDays($i);

                $total = DB::table('tickets')
                    ->whereDate('created_at', $date->format('Y-m-d'))
                    ->where('is_deleted', false)
                    ->count();

                $resolved = DB::table('tickets')
                    ->whereDate('resolved_at', $date->format('Y-m-d'))
                    ->where('is_deleted', false)
                    ->count();

                $trends[] = [
                    'name' => $date->format('D'),
                    'date' => $date->format('Y-m-d'),
                    'total' => $total,
                    'resolved' => $resolved
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $trends
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch ticket trends',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get recent tickets for dashboard
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRecentTickets()
    {
        try {
            $recentTickets = DB::table('tickets')
                ->join('clients', 'tickets.client_id', '=', 'clients.id')
                ->leftJoin('users', 'tickets.assigned_agent_id', '=', 'users.id')
                ->select([
                    'tickets.id',
                    'tickets.ticket_number',
                    'tickets.subject',
                    'tickets.status',
                    'tickets.priority',
                    'tickets.created_at',
                    'clients.name as client_name',
                    'clients.email as client_email',
                    'users.name as agent_name'
                ])
                ->where('tickets.is_deleted', false)
                ->orderBy('tickets.created_at', 'desc')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $recentTickets->map(function($ticket) {
                    return [
                        'id' => $ticket->id,
                        'ticket_number' => $ticket->ticket_number,
                        'subject' => $ticket->subject,
                        'status' => $ticket->status,
                        'priority' => $ticket->priority,
                        'created_at' => $ticket->created_at,
                        'client' => [
                            'name' => $ticket->client_name,
                            'email' => $ticket->client_email
                        ],
                        'assigned_agent' => $ticket->agent_name ? [
                            'name' => $ticket->agent_name
                        ] : null
                    ];
                })
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch recent tickets',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get notification counts for sidebar badges
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getNotificationCounts()
    {
        try {
            // Count new tickets (not yet assigned or opened)
            $newTickets = DB::table('tickets')
                ->where('status', 'new')
                ->where('is_deleted', false)
                ->count();

            // Count unread messages from ticket_comments
            $unreadMessages = DB::table('ticket_comments')
                ->where('is_read', false)
                ->count();

            // Overdue tickets (past resolution due date)
            $overdueTickets = DB::table('tickets')
                ->where('resolution_due_at', '<', Carbon::now())
                ->whereNull('resolved_at')
                ->where('is_deleted', false)
                ->count();

            // High priority tickets that need attention
            $urgentTickets = DB::table('tickets')
                ->where('priority', 'urgent')
                ->whereIn('status', ['new', 'open'])
                ->where('is_deleted', false)
                ->count();

            return response()->json([
                'success' => true,
                'new_tickets' => $newTickets,
                'unread_messages' => $unreadMessages,
                'overdue_tickets' => $overdueTickets,
                'urgent_tickets' => $urgentTickets,
                'timestamp' => Carbon::now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notification counts',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}