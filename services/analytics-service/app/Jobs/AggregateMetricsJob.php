<?php

namespace App\Jobs;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\TicketMetrics;
use App\Models\AgentMetrics;
use Carbon\Carbon;
use Exception;

class AggregateMetricsJob extends Job
{
    protected $date;
    protected $type;

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
    public $timeout = 180;

    /**
     * Create a new job instance.
     *
     * @param string $date
     * @param string $type
     * @return void
     */
    public function __construct($date = null, $type = 'daily')
    {
        $this->date = $date ?: Carbon::now()->format('Y-m-d');
        $this->type = $type;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            DB::beginTransaction();

            switch ($this->type) {
                case 'daily':
                    $this->aggregateDailyMetrics();
                    break;
                case 'hourly':
                    $this->aggregateHourlyMetrics();
                    break;
                case 'agents':
                    $this->aggregateAgentMetrics();
                    break;
                case 'clients':
                    $this->aggregateClientMetrics();
                    break;
                case 'sla':
                    $this->aggregateSLAMetrics();
                    break;
                default:
                    $this->aggregateDailyMetrics();
            }

            // Clear relevant caches
            $this->clearCaches();

            DB::commit();

            \Log::info('Metrics aggregation completed', [
                'date' => $this->date,
                'type' => $this->type
            ]);

        } catch (Exception $e) {
            DB::rollBack();

            \Log::error('Metrics aggregation failed', [
                'date' => $this->date,
                'type' => $this->type,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Aggregate daily ticket metrics
     */
    protected function aggregateDailyMetrics()
    {
        $date = $this->date;

        // Aggregate ticket statistics
        $ticketStats = DB::table('tickets')
            ->whereDate('created_at', $date)
            ->where('is_archived', false)
            ->selectRaw('
                COUNT(*) as total_tickets,
                COUNT(CASE WHEN status = ? THEN 1 END) as new_tickets,
                COUNT(CASE WHEN status = ? THEN 1 END) as open_tickets,
                COUNT(CASE WHEN status = ? THEN 1 END) as resolved_tickets,
                COUNT(CASE WHEN status = ? THEN 1 END) as closed_tickets,
                AVG(CASE
                    WHEN resolved_at IS NOT NULL
                    THEN EXTRACT(EPOCH FROM (resolved_at - created_at))/3600
                    ELSE NULL
                END) as avg_resolution_hours,
                AVG(CASE
                    WHEN first_response_at IS NOT NULL
                    THEN EXTRACT(EPOCH FROM (first_response_at - created_at))/3600
                    ELSE NULL
                END) as avg_first_response_hours,
                COUNT(CASE WHEN priority = ? THEN 1 END) as urgent_tickets,
                COUNT(CASE WHEN priority = ? THEN 1 END) as high_priority_tickets
            ', ['new', 'open', 'resolved', 'closed', 'urgent', 'high'])
            ->first();

        // Get category breakdown
        $categoryBreakdown = DB::table('tickets')
            ->leftJoin('categories', 'tickets.category_id', '=', 'categories.id')
            ->whereDate('tickets.created_at', $date)
            ->where('tickets.is_archived', false)
            ->select('categories.name', DB::raw('COUNT(*) as count'))
            ->groupBy('categories.name')
            ->get()
            ->pluck('count', 'name')
            ->toArray();

        // Get source breakdown
        $sourceBreakdown = DB::table('tickets')
            ->whereDate('created_at', $date)
            ->where('is_archived', false)
            ->select('source', DB::raw('COUNT(*) as count'))
            ->groupBy('source')
            ->get()
            ->pluck('count', 'source')
            ->toArray();

        // Store or update metrics
        TicketMetrics::updateOrCreate(
            ['date' => $date],
            [
                'total_tickets' => $ticketStats->total_tickets ?? 0,
                'new_tickets' => $ticketStats->new_tickets ?? 0,
                'open_tickets' => $ticketStats->open_tickets ?? 0,
                'resolved_tickets' => $ticketStats->resolved_tickets ?? 0,
                'closed_tickets' => $ticketStats->closed_tickets ?? 0,
                'avg_resolution_time' => $ticketStats->avg_resolution_hours ?? 0,
                'avg_first_response_time' => $ticketStats->avg_first_response_hours ?? 0,
                'urgent_tickets' => $ticketStats->urgent_tickets ?? 0,
                'high_priority_tickets' => $ticketStats->high_priority_tickets ?? 0,
                'category_breakdown' => json_encode($categoryBreakdown),
                'source_breakdown' => json_encode($sourceBreakdown),
                'aggregated_at' => Carbon::now()
            ]
        );

        // Aggregate category-specific metrics
        $this->aggregateCategoryMetrics($date);
    }

    /**
     * Aggregate hourly metrics (for real-time dashboards)
     */
    protected function aggregateHourlyMetrics()
    {
        $hour = Carbon::now()->subHour()->format('Y-m-d H:00:00');

        // Store hourly data in cache for quick access
        $hourlyStats = DB::table('tickets')
            ->where('created_at', '>=', $hour)
            ->where('created_at', '<', Carbon::parse($hour)->addHour())
            ->where('is_archived', false)
            ->selectRaw('
                COUNT(*) as total_tickets,
                AVG(CASE
                    WHEN first_response_at IS NOT NULL
                    THEN EXTRACT(EPOCH FROM (first_response_at - created_at))/60
                    ELSE NULL
                END) as avg_response_minutes
            ')
            ->first();

        $cacheKey = 'metrics:hourly:' . Carbon::parse($hour)->format('Y-m-d-H');
        Cache::put($cacheKey, $hourlyStats, 7200); // Keep for 2 hours
    }

    /**
     * Aggregate agent metrics
     */
    protected function aggregateAgentMetrics()
    {
        $endDate = Carbon::parse($this->date);
        $startDate = $endDate->copy()->subDays(30); // 30-day rolling window

        // Get all active agents
        $agents = DB::table('users')
            ->whereIn('role', ['agent', 'supervisor', 'admin'])
            ->where('is_active', true)
            ->pluck('id');

        foreach ($agents as $agentId) {
            $agentStats = DB::table('tickets')
                ->where('assigned_to', $agentId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where('is_archived', false)
                ->selectRaw('
                    COUNT(*) as total_assigned,
                    COUNT(CASE WHEN status = ? THEN 1 END) as resolved_tickets,
                    COUNT(CASE WHEN status = ? THEN 1 END) as closed_tickets,
                    AVG(CASE
                        WHEN resolved_at IS NOT NULL
                        THEN EXTRACT(EPOCH FROM (resolved_at - created_at))/3600
                        ELSE NULL
                    END) as avg_resolution_hours,
                    AVG(CASE
                        WHEN first_response_at IS NOT NULL
                        THEN EXTRACT(EPOCH FROM (first_response_at - created_at))/3600
                        ELSE NULL
                    END) as avg_first_response_hours,
                    MIN(CASE
                        WHEN first_response_at IS NOT NULL
                        THEN EXTRACT(EPOCH FROM (first_response_at - created_at))/60
                        ELSE NULL
                    END) as fastest_response_minutes,
                    COUNT(DISTINCT DATE(created_at)) as active_days
                ', ['resolved', 'closed'])
                ->first();

            $responseCount = DB::table('ticket_comments')
                ->where('user_id', $agentId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();

            $satisfactionStats = DB::table('ticket_feedback')
                ->join('tickets', 'tickets.id', '=', 'ticket_feedback.ticket_id')
                ->where('tickets.assigned_to', $agentId)
                ->whereBetween('ticket_feedback.created_at', [$startDate, $endDate])
                ->selectRaw('
                    AVG(rating) as avg_rating,
                    COUNT(*) as total_ratings
                ')
                ->first();

            AgentMetrics::updateOrCreate(
                [
                    'agent_id' => $agentId,
                    'period_start' => $startDate->format('Y-m-d'),
                    'period_end' => $endDate->format('Y-m-d')
                ],
                [
                    'tickets_assigned' => $agentStats->total_assigned ?? 0,
                    'tickets_resolved' => $agentStats->resolved_tickets ?? 0,
                    'tickets_closed' => $agentStats->closed_tickets ?? 0,
                    'avg_resolution_time' => $agentStats->avg_resolution_hours ?? 0,
                    'avg_first_response_time' => $agentStats->avg_first_response_hours ?? 0,
                    'fastest_response_time' => $agentStats->fastest_response_minutes ?? 0,
                    'total_responses' => $responseCount,
                    'satisfaction_score' => $satisfactionStats->avg_rating ?? 0,
                    'total_ratings' => $satisfactionStats->total_ratings ?? 0,
                    'active_days' => $agentStats->active_days ?? 0,
                    'aggregated_at' => Carbon::now()
                ]
            );
        }
    }

    /**
     * Aggregate client metrics
     */
    protected function aggregateClientMetrics()
    {
        $endDate = Carbon::parse($this->date);
        $startDate = $endDate->copy()->subDays(30);

        // Get top 100 clients by ticket volume
        $topClients = DB::table('tickets')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('is_archived', false)
            ->select('client_id', DB::raw('COUNT(*) as ticket_count'))
            ->groupBy('client_id')
            ->orderBy('ticket_count', 'desc')
            ->limit(100)
            ->pluck('client_id');

        foreach ($topClients as $clientId) {
            $clientStats = DB::table('tickets')
                ->where('client_id', $clientId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where('is_archived', false)
                ->selectRaw('
                    COUNT(*) as total_tickets,
                    COUNT(CASE WHEN status IN (?, ?, ?) THEN 1 END) as open_tickets,
                    COUNT(CASE WHEN status = ? THEN 1 END) as resolved_tickets,
                    AVG(CASE
                        WHEN resolved_at IS NOT NULL
                        THEN EXTRACT(EPOCH FROM (resolved_at - created_at))/3600
                        ELSE NULL
                    END) as avg_resolution_hours
                ', ['new', 'open', 'pending', 'resolved'])
                ->first();

            $satisfactionStats = DB::table('ticket_feedback')
                ->join('tickets', 'tickets.id', '=', 'ticket_feedback.ticket_id')
                ->where('tickets.client_id', $clientId)
                ->whereBetween('ticket_feedback.created_at', [$startDate, $endDate])
                ->selectRaw('
                    AVG(rating) as avg_rating,
                    COUNT(*) as total_feedback
                ')
                ->first();

            $issueCategories = DB::table('tickets')
                ->leftJoin('categories', 'tickets.category_id', '=', 'categories.id')
                ->where('tickets.client_id', $clientId)
                ->whereBetween('tickets.created_at', [$startDate, $endDate])
                ->where('tickets.is_archived', false)
                ->select('categories.name', DB::raw('COUNT(*) as count'))
                ->groupBy('categories.name')
                ->get()
                ->pluck('count', 'name')
                ->toArray();

            DB::table('client_metrics')->updateOrInsert(
                [
                    'client_id' => $clientId,
                    'period_start' => $startDate->format('Y-m-d'),
                    'period_end' => $endDate->format('Y-m-d')
                ],
                [
                    'total_tickets' => $clientStats->total_tickets ?? 0,
                    'open_tickets' => $clientStats->open_tickets ?? 0,
                    'resolved_tickets' => $clientStats->resolved_tickets ?? 0,
                    'avg_resolution_time' => $clientStats->avg_resolution_hours ?? 0,
                    'satisfaction_score' => $satisfactionStats->avg_rating ?? 0,
                    'total_feedback' => $satisfactionStats->total_feedback ?? 0,
                    'issue_categories' => json_encode($issueCategories),
                    'aggregated_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]
            );
        }
    }

    /**
     * Aggregate SLA metrics
     */
    protected function aggregateSLAMetrics()
    {
        $date = $this->date;

        $slaStats = DB::table('tickets')
            ->whereDate('created_at', $date)
            ->whereNotNull('sla_deadline')
            ->where('is_archived', false)
            ->selectRaw('
                COUNT(*) as total_tickets_with_sla,
                COUNT(CASE
                    WHEN first_response_at IS NOT NULL AND first_response_at <= sla_deadline
                    THEN 1
                END) as sla_met_count,
                COUNT(CASE
                    WHEN first_response_at IS NOT NULL AND first_response_at > sla_deadline
                    THEN 1
                END) as sla_breached_count
            ')
            ->first();

        $complianceRate = $slaStats->total_tickets_with_sla > 0
            ? round(($slaStats->sla_met_count / $slaStats->total_tickets_with_sla) * 100, 2)
            : 100;

        // Get breakdown by priority
        $priorityBreakdown = DB::table('tickets')
            ->whereDate('created_at', $date)
            ->whereNotNull('sla_deadline')
            ->where('is_archived', false)
            ->select('priority', DB::raw('
                COUNT(*) as total,
                COUNT(CASE
                    WHEN first_response_at IS NOT NULL AND first_response_at <= sla_deadline
                    THEN 1
                END) as met
            '))
            ->groupBy('priority')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->priority => [
                    'total' => $item->total,
                    'met' => $item->met,
                    'compliance' => $item->total > 0 ? round(($item->met / $item->total) * 100, 2) : 100
                ]];
            })
            ->toArray();

        DB::table('sla_metrics')->updateOrInsert(
            ['date' => $date],
            [
                'total_tickets_with_sla' => $slaStats->total_tickets_with_sla ?? 0,
                'sla_met_count' => $slaStats->sla_met_count ?? 0,
                'sla_breached_count' => $slaStats->sla_breached_count ?? 0,
                'compliance_rate' => $complianceRate,
                'priority_breakdown' => json_encode($priorityBreakdown),
                'updated_at' => Carbon::now()
            ]
        );
    }

    /**
     * Aggregate category-specific metrics
     */
    protected function aggregateCategoryMetrics($date)
    {
        $categoryStats = DB::table('tickets')
            ->whereDate('created_at', $date)
            ->where('is_archived', false)
            ->select('category_id', DB::raw('
                COUNT(*) as ticket_count,
                AVG(CASE
                    WHEN resolved_at IS NOT NULL
                    THEN EXTRACT(EPOCH FROM (resolved_at - created_at))/3600
                    ELSE NULL
                END) as avg_resolution_hours,
                AVG(CASE
                    WHEN first_response_at IS NOT NULL
                    THEN EXTRACT(EPOCH FROM (first_response_at - created_at))/3600
                    ELSE NULL
                END) as avg_response_hours
            '))
            ->groupBy('category_id')
            ->get();

        foreach ($categoryStats as $stat) {
            DB::table('ticket_category_metrics')->updateOrInsert(
                [
                    'date' => $date,
                    'category_id' => $stat->category_id
                ],
                [
                    'ticket_count' => $stat->ticket_count,
                    'avg_resolution_time' => $stat->avg_resolution_hours,
                    'avg_response_time' => $stat->avg_response_hours,
                    'updated_at' => Carbon::now()
                ]
            );
        }
    }

    /**
     * Clear relevant caches
     */
    protected function clearCaches()
    {
        Cache::tags(['metrics', 'dashboard'])->flush();
        Cache::forget('realtime:open_tickets');
        Cache::forget('realtime:unassigned_tickets');
        Cache::forget('realtime:overdue_tickets');
        Cache::forget('realtime:avg_response_time');
    }

    /**
     * Handle a job failure
     *
     * @param Exception $exception
     * @return void
     */
    public function failed(Exception $exception)
    {
        \Log::error('Metrics aggregation job failed', [
            'date' => $this->date,
            'type' => $this->type,
            'error' => $exception->getMessage()
        ]);
    }
}