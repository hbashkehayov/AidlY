<?php

namespace App\Services;

use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Smart Ticket Assignment Service
 *
 * Automatically assigns tickets to agents based on:
 * - Current workload (number of open tickets)
 * - Agent availability status
 * - Department assignment
 * - Agent capacity limits
 * - Priority-based routing
 */
class TicketAssignmentService
{
    // Assignment strategies
    const STRATEGY_LEAST_BUSY = 'least_busy';
    const STRATEGY_ROUND_ROBIN = 'round_robin';
    const STRATEGY_SKILL_BASED = 'skill_based';
    const STRATEGY_PRIORITY_BASED = 'priority_based';

    // Default configuration
    private $defaultStrategy = self::STRATEGY_LEAST_BUSY;
    private $defaultCapacity = 20; // Maximum tickets per agent
    private $considerDepartment = true;
    private $considerPriority = true;

    /**
     * Automatically assign a ticket to the best available agent
     */
    public function autoAssign(Ticket $ticket, array $options = []): ?string
    {
        $strategy = $options['strategy'] ?? $this->defaultStrategy;

        Log::info('Auto-assigning ticket', [
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'priority' => $ticket->priority,
            'department_id' => $ticket->assigned_department_id,
            'strategy' => $strategy
        ]);

        try {
            $agentId = null;

            switch ($strategy) {
                case self::STRATEGY_LEAST_BUSY:
                    $agentId = $this->assignToLeastBusy($ticket);
                    break;

                case self::STRATEGY_ROUND_ROBIN:
                    $agentId = $this->assignRoundRobin($ticket);
                    break;

                case self::STRATEGY_PRIORITY_BASED:
                    $agentId = $this->assignByPriority($ticket);
                    break;

                case self::STRATEGY_SKILL_BASED:
                    $agentId = $this->assignBySkill($ticket);
                    break;

                default:
                    $agentId = $this->assignToLeastBusy($ticket);
            }

            if ($agentId) {
                $ticket->assign($agentId);

                Log::info('Ticket auto-assigned successfully', [
                    'ticket_id' => $ticket->id,
                    'agent_id' => $agentId,
                    'strategy' => $strategy
                ]);

                // Send notification to assigned agent
                $this->notifyAgentAssignment($ticket, $agentId);

                return $agentId;
            }

            Log::warning('No available agent found for ticket', [
                'ticket_id' => $ticket->id,
                'department_id' => $ticket->assigned_department_id
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Auto-assignment failed', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Assign to agent with least workload
     * This is the primary smart assignment method
     */
    private function assignToLeastBusy(Ticket $ticket): ?string
    {
        // Only use department filtering if ticket explicitly has a department assigned
        $departmentId = $ticket->assigned_department_id;

        // Get available agents with their current workload
        $subquery = DB::table('tickets')
            ->select('assigned_agent_id', DB::raw('COUNT(*) as ticket_count'))
            ->whereIn('status', ['new', 'open', 'pending', 'on_hold'])
            ->where('is_deleted', false)
            ->whereNotNull('assigned_agent_id')
            ->groupBy('assigned_agent_id');

        $query = DB::table('users')
            ->leftJoinSub($subquery, 'agent_tickets', function($join) {
                $join->on('users.id', '=', 'agent_tickets.assigned_agent_id');
            })
            ->select(
                'users.id',
                'users.name',
                'users.email',
                'users.role',
                DB::raw('COALESCE(agent_tickets.ticket_count, 0) as open_ticket_count')
            )
            ->where('users.is_active', true)
            ->whereIn('users.role', ['agent', 'manager', 'admin']);

        // Filter by department if specified
        if ($this->considerDepartment && $departmentId) {
            $query->where('users.department_id', $departmentId);
        }

        $agents = $query
            ->groupBy('users.id', 'users.name', 'users.email', 'users.role', 'agent_tickets.ticket_count')
            ->having(DB::raw('COALESCE(agent_tickets.ticket_count, 0)'), '<', $this->defaultCapacity)
            ->orderBy(DB::raw('COALESCE(agent_tickets.ticket_count, 0)'), 'asc')
            ->orderBy('users.last_login_at', 'desc') // Prefer recently active agents
            ->limit(5)
            ->get();

        if ($agents->isEmpty()) {
            // No agents available in department, try without department filter
            if ($this->considerDepartment && $departmentId) {
                Log::info('No agents in department, trying without department filter');
                $this->considerDepartment = false;
                $result = $this->assignToLeastBusy($ticket);
                $this->considerDepartment = true; // Reset
                return $result;
            }
            return null;
        }

        // Get the agent with minimum workload
        $selectedAgent = $agents->first();

        Log::info('Selected agent with least workload', [
            'agent_id' => $selectedAgent->id,
            'agent_name' => $selectedAgent->name,
            'current_workload' => $selectedAgent->open_ticket_count,
            'total_candidates' => $agents->count()
        ]);

        return $selectedAgent->id;
    }

    /**
     * Round-robin assignment across agents
     */
    private function assignRoundRobin(Ticket $ticket): ?string
    {
        $departmentId = $ticket->assigned_department_id ?? $this->getDefaultDepartment();

        // Get last assigned agent ID from cache
        $cacheKey = "round_robin_last_agent:{$departmentId}";
        $lastAgentId = Cache::get($cacheKey);

        // Get available agents
        $query = DB::table('users')
            ->select(
                'users.id',
                'users.name',
                DB::raw('COUNT(tickets.id) as open_ticket_count')
            )
            ->leftJoin('tickets', function($join) {
                $join->on('users.id', '=', 'tickets.assigned_agent_id')
                    ->whereIn('tickets.status', ['new', 'open', 'pending', 'on_hold'])
                    ->where('tickets.is_deleted', false);
            })
            ->where('users.is_active', true)
            ->whereIn('users.role', ['agent', 'manager', 'admin']);

        if ($departmentId) {
            $query->where('users.department_id', $departmentId);
        }

        $agents = $query
            ->groupBy('users.id', 'users.name')
            ->having('open_ticket_count', '<', $this->defaultCapacity)
            ->orderBy('users.id', 'asc')
            ->get();

        if ($agents->isEmpty()) {
            return null;
        }

        // Find next agent after last assigned
        $selectedAgent = null;
        $found = false;

        foreach ($agents as $agent) {
            if ($found || !$lastAgentId) {
                $selectedAgent = $agent;
                break;
            }
            if ($agent->id === $lastAgentId) {
                $found = true;
            }
        }

        // If we reached the end, wrap to first agent
        if (!$selectedAgent) {
            $selectedAgent = $agents->first();
        }

        // Cache the selected agent for next round-robin
        Cache::put($cacheKey, $selectedAgent->id, 3600); // Cache for 1 hour

        Log::info('Round-robin assignment', [
            'agent_id' => $selectedAgent->id,
            'agent_name' => $selectedAgent->name,
            'last_agent_id' => $lastAgentId
        ]);

        return $selectedAgent->id;
    }

    /**
     * Assign based on ticket priority
     * High priority tickets go to senior agents or less busy ones
     */
    private function assignByPriority(Ticket $ticket): ?string
    {
        $isHighPriority = in_array($ticket->priority, [Ticket::PRIORITY_URGENT, Ticket::PRIORITY_HIGH]);
        $departmentId = $ticket->assigned_department_id ?? $this->getDefaultDepartment();

        $query = DB::table('users')
            ->select(
                'users.id',
                'users.name',
                'users.role',
                DB::raw('COUNT(tickets.id) as open_ticket_count'),
                DB::raw('COUNT(CASE WHEN tickets.priority IN (\'urgent\', \'high\') THEN 1 END) as high_priority_count')
            )
            ->leftJoin('tickets', function($join) {
                $join->on('users.id', '=', 'tickets.assigned_agent_id')
                    ->whereIn('tickets.status', ['new', 'open', 'pending', 'on_hold'])
                    ->where('tickets.is_deleted', false);
            })
            ->where('users.is_active', true)
            ->whereIn('users.role', ['agent', 'manager', 'admin']);

        if ($departmentId) {
            $query->where('users.department_id', $departmentId);
        }

        $agents = $query
            ->groupBy('users.id', 'users.name', 'users.role')
            ->having('open_ticket_count', '<', $this->defaultCapacity)
            ->get();

        if ($agents->isEmpty()) {
            return null;
        }

        if ($isHighPriority) {
            // For high priority, prefer managers/admins or agents with fewer high-priority tickets
            $selectedAgent = $agents
                ->sortBy('high_priority_count')
                ->sortByDesc(function($agent) {
                    return $agent->role === 'manager' ? 2 : ($agent->role === 'admin' ? 3 : 1);
                })
                ->first();
        } else {
            // For normal priority, assign to least busy
            $selectedAgent = $agents->sortBy('open_ticket_count')->first();
        }

        Log::info('Priority-based assignment', [
            'agent_id' => $selectedAgent->id,
            'agent_name' => $selectedAgent->name,
            'ticket_priority' => $ticket->priority,
            'agent_role' => $selectedAgent->role,
            'agent_workload' => $selectedAgent->open_ticket_count
        ]);

        return $selectedAgent->id;
    }

    /**
     * Assign based on agent skills/category expertise
     * This is a placeholder for future skill-based routing
     */
    private function assignBySkill(Ticket $ticket): ?string
    {
        // For now, fall back to least busy
        // TODO: Implement skill matrix in database
        // - Store agent skills/expertise areas
        // - Match ticket category to agent skills
        // - Consider agent's resolution rate by category

        Log::info('Skill-based assignment not implemented, falling back to least busy');
        return $this->assignToLeastBusy($ticket);
    }

    /**
     * Get agent workload statistics
     */
    public function getAgentWorkloads(?string $departmentId = null): array
    {
        $query = DB::table('users')
            ->select(
                'users.id',
                'users.name',
                'users.email',
                'users.role',
                'users.department_id',
                DB::raw('COUNT(CASE WHEN tickets.status IN (\'new\', \'open\', \'pending\', \'on_hold\') THEN 1 END) as open_tickets'),
                DB::raw('COUNT(CASE WHEN tickets.status = \'resolved\' AND DATE(tickets.resolved_at) = CURRENT_DATE THEN 1 END) as resolved_today'),
                DB::raw('COUNT(tickets.id) as total_tickets'),
                DB::raw('ROUND(AVG(CASE WHEN tickets.status = \'resolved\' THEN EXTRACT(EPOCH FROM (tickets.resolved_at - tickets.created_at))/3600 END), 2) as avg_resolution_hours')
            )
            ->leftJoin('tickets', 'users.id', '=', 'tickets.assigned_agent_id')
            ->where('users.is_active', true)
            ->whereIn('users.role', ['agent', 'manager', 'admin']);

        if ($departmentId) {
            $query->where('users.department_id', $departmentId);
        }

        return $query
            ->groupBy('users.id', 'users.name', 'users.email', 'users.role', 'users.department_id')
            ->orderBy('open_tickets', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get available agents for manual assignment
     */
    public function getAvailableAgents(?string $departmentId = null, ?string $priority = null): array
    {
        $query = DB::table('users')
            ->select(
                'users.id',
                'users.name',
                'users.email',
                'users.role',
                DB::raw('COUNT(tickets.id) as open_ticket_count'),
                DB::raw('CASE WHEN COUNT(tickets.id) >= ' . $this->defaultCapacity . ' THEN false ELSE true END as is_available')
            )
            ->leftJoin('tickets', function($join) {
                $join->on('users.id', '=', 'tickets.assigned_agent_id')
                    ->whereIn('tickets.status', ['new', 'open', 'pending', 'on_hold'])
                    ->where('tickets.is_deleted', false);
            })
            ->where('users.is_active', true)
            ->whereIn('users.role', ['agent', 'manager', 'admin']);

        if ($departmentId) {
            $query->where('users.department_id', $departmentId);
        }

        return $query
            ->groupBy('users.id', 'users.name', 'users.email', 'users.role')
            ->orderBy('open_ticket_count', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * Check if an agent can accept more tickets
     */
    public function canAcceptTicket(string $agentId): bool
    {
        $openTicketCount = DB::table('tickets')
            ->where('assigned_agent_id', $agentId)
            ->whereIn('status', ['new', 'open', 'pending', 'on_hold'])
            ->where('is_deleted', false)
            ->count();

        return $openTicketCount < $this->defaultCapacity;
    }

    /**
     * Reassign overloaded tickets
     * Useful for load balancing
     */
    public function rebalanceWorkload(?string $departmentId = null): array
    {
        $results = [
            'reassigned' => 0,
            'agents_balanced' => 0,
            'errors' => []
        ];

        try {
            // Find overloaded agents
            $overloadedAgents = DB::table('users')
                ->select(
                    'users.id',
                    'users.name',
                    DB::raw('COUNT(tickets.id) as open_ticket_count')
                )
                ->join('tickets', function($join) {
                    $join->on('users.id', '=', 'tickets.assigned_agent_id')
                        ->whereIn('tickets.status', ['new', 'open', 'pending', 'on_hold'])
                        ->where('tickets.is_deleted', false);
                })
                ->where('users.is_active', true)
                ->whereIn('users.role', ['agent', 'manager', 'admin']);

            if ($departmentId) {
                $overloadedAgents->where('users.department_id', $departmentId);
            }

            $overloadedAgents = $overloadedAgents
                ->groupBy('users.id', 'users.name')
                ->having('open_ticket_count', '>', $this->defaultCapacity)
                ->get();

            foreach ($overloadedAgents as $agent) {
                // Get some tickets to reassign (oldest, lowest priority)
                $ticketsToReassign = Ticket::where('assigned_agent_id', $agent->id)
                    ->whereIn('status', ['new', 'open', 'pending'])
                    ->orderBy('priority', 'asc') // Low priority first
                    ->orderBy('created_at', 'asc')
                    ->limit($agent->open_ticket_count - $this->defaultCapacity)
                    ->get();

                foreach ($ticketsToReassign as $ticket) {
                    $newAgentId = $this->assignToLeastBusy($ticket);
                    if ($newAgentId && $newAgentId !== $agent->id) {
                        $results['reassigned']++;
                    }
                }

                $results['agents_balanced']++;
            }

            Log::info('Workload rebalanced', $results);

        } catch (\Exception $e) {
            $results['errors'][] = $e->getMessage();
            Log::error('Workload rebalancing failed', ['error' => $e->getMessage()]);
        }

        return $results;
    }

    /**
     * Get default department ID
     */
    private function getDefaultDepartment(): ?string
    {
        return Cache::remember('default_department_id', 3600, function() {
            return DB::table('departments')
                ->where('name', 'ILIKE', '%support%')
                ->orWhere('name', 'ILIKE', '%general%')
                ->value('id');
        });
    }

    /**
     * Send notification to assigned agent
     */
    private function notifyAgentAssignment(Ticket $ticket, string $agentId): void
    {
        try {
            // Get agent info
            $agent = DB::table('users')->where('id', $agentId)->first();
            if (!$agent) {
                return;
            }

            $notificationServiceUrl = env('NOTIFICATION_SERVICE_URL', 'http://localhost:8004');

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "{$notificationServiceUrl}/api/v1/notifications",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode([
                    'user_id' => $agentId,
                    'type' => 'ticket_assigned',
                    'title' => 'New Ticket Assigned',
                    'message' => "Ticket #{$ticket->ticket_number} has been automatically assigned to you: {$ticket->subject}",
                    'data' => [
                        'ticket_id' => $ticket->id,
                        'ticket_number' => $ticket->ticket_number,
                        'priority' => $ticket->priority,
                        'status' => $ticket->status,
                        'subject' => $ticket->subject
                    ],
                    'channels' => ['in_app', 'email'],
                    'priority' => $ticket->priority === Ticket::PRIORITY_URGENT ? 'high' : 'normal'
                ]),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json'
                ]
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 || $httpCode === 201) {
                Log::info('Assignment notification sent', [
                    'ticket_id' => $ticket->id,
                    'agent_id' => $agentId,
                    'agent_email' => $agent->email
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to send assignment notification', [
                'ticket_id' => $ticket->id,
                'agent_id' => $agentId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Set assignment configuration
     */
    public function setConfiguration(array $config): void
    {
        if (isset($config['strategy'])) {
            $this->defaultStrategy = $config['strategy'];
        }
        if (isset($config['capacity'])) {
            $this->defaultCapacity = $config['capacity'];
        }
        if (isset($config['consider_department'])) {
            $this->considerDepartment = $config['consider_department'];
        }
        if (isset($config['consider_priority'])) {
            $this->considerPriority = $config['consider_priority'];
        }
    }
}