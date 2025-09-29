<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class TicketAssignmentService
{
    protected $ticketServiceUrl;
    protected $authServiceUrl;

    public function __construct()
    {
        $this->ticketServiceUrl = env('TICKET_SERVICE_URL', 'http://localhost:8002');
        $this->authServiceUrl = env('AUTH_SERVICE_URL', 'http://localhost:8001');
    }

    /**
     * Automatically assign ticket based on rules and availability
     */
    public function assignTicket(string $ticketId, array $ticketData): array
    {
        try {
            // Step 1: Determine assignment strategy
            $strategy = $this->determineAssignmentStrategy($ticketData);

            // Step 2: Get eligible agents based on strategy
            $eligibleAgents = $this->getEligibleAgents($strategy);

            if (empty($eligibleAgents)) {
                Log::warning("No eligible agents found for ticket assignment", [
                    'ticket_id' => $ticketId,
                    'strategy' => $strategy,
                ]);
                return [
                    'success' => false,
                    'message' => 'No eligible agents available',
                ];
            }

            // Step 3: Select best agent
            $selectedAgent = $this->selectBestAgent($eligibleAgents, $strategy);

            // Step 4: Assign ticket
            $result = $this->performAssignment($ticketId, $selectedAgent['id'], $strategy['department_id'] ?? null);

            if ($result['success']) {
                Log::info("Ticket automatically assigned", [
                    'ticket_id' => $ticketId,
                    'agent_id' => $selectedAgent['id'],
                    'agent_name' => $selectedAgent['name'],
                    'strategy' => $strategy['type'],
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            Log::error("Failed to auto-assign ticket", [
                'ticket_id' => $ticketId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Assignment failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Determine assignment strategy based on ticket data
     */
    protected function determineAssignmentStrategy(array $ticketData): array
    {
        $strategy = [
            'type' => 'round_robin', // default
            'priority_weight' => 1,
            'filters' => [],
        ];

        // Priority-based routing
        if (isset($ticketData['priority'])) {
            switch ($ticketData['priority']) {
                case 'urgent':
                    $strategy['type'] = 'priority';
                    $strategy['priority_weight'] = 3;
                    $strategy['filters']['skill_level'] = 'senior';
                    break;
                case 'high':
                    $strategy['type'] = 'priority';
                    $strategy['priority_weight'] = 2;
                    $strategy['filters']['skill_level'] = 'intermediate';
                    break;
            }
        }

        // Category-based routing
        if (isset($ticketData['category_id'])) {
            $categoryRules = $this->getCategoryAssignmentRules($ticketData['category_id']);
            if ($categoryRules) {
                $strategy = array_merge($strategy, $categoryRules);
            }
        }

        // Department-based routing
        if (isset($ticketData['department_id'])) {
            $strategy['department_id'] = $ticketData['department_id'];
        }

        // Client VIP routing
        if (isset($ticketData['client']['is_vip']) && $ticketData['client']['is_vip']) {
            $strategy['type'] = 'vip';
            $strategy['filters']['handles_vip'] = true;
        }

        // Subject keyword-based routing
        if (isset($ticketData['subject'])) {
            $keywordRules = $this->getKeywordAssignmentRules($ticketData['subject']);
            if ($keywordRules) {
                $strategy['filters'] = array_merge($strategy['filters'], $keywordRules);
            }
        }

        // Language-based routing (if AI detected language)
        if (isset($ticketData['detected_language'])) {
            $strategy['filters']['languages'] = [$ticketData['detected_language']];
        }

        return $strategy;
    }

    /**
     * Get eligible agents based on strategy
     */
    protected function getEligibleAgents(array $strategy): array
    {
        $cacheKey = 'eligible_agents_' . md5(json_encode($strategy));

        // Cache agent list for 5 minutes
        return Cache::remember($cacheKey, 300, function () use ($strategy) {
            try {
                $params = [
                    'role' => 'agent',
                    'is_active' => true,
                    'is_available' => true,
                ];

                // Add department filter
                if (isset($strategy['department_id'])) {
                    $params['department_id'] = $strategy['department_id'];
                }

                // Get agents from auth service
                $response = Http::get("{$this->authServiceUrl}/api/v1/users", $params);

                if (!$response->successful()) {
                    return [];
                }

                $agents = $response->json('data', []);

                // Apply additional filters
                if (!empty($strategy['filters'])) {
                    $agents = $this->applyAgentFilters($agents, $strategy['filters']);
                }

                // Get agent workload
                foreach ($agents as &$agent) {
                    $agent['workload'] = $this->getAgentWorkload($agent['id']);
                }

                return $agents;

            } catch (\Exception $e) {
                Log::error("Failed to get eligible agents", [
                    'error' => $e->getMessage(),
                ]);
                return [];
            }
        });
    }

    /**
     * Apply filters to agent list
     */
    protected function applyAgentFilters(array $agents, array $filters): array
    {
        return array_filter($agents, function ($agent) use ($filters) {
            foreach ($filters as $key => $value) {
                switch ($key) {
                    case 'skill_level':
                        if (!isset($agent['skill_level']) || $agent['skill_level'] !== $value) {
                            return false;
                        }
                        break;

                    case 'handles_vip':
                        if (!isset($agent['handles_vip']) || !$agent['handles_vip']) {
                            return false;
                        }
                        break;

                    case 'languages':
                        if (!isset($agent['languages']) ||
                            !array_intersect($value, $agent['languages'])) {
                            return false;
                        }
                        break;

                    case 'specializations':
                        if (!isset($agent['specializations']) ||
                            !array_intersect($value, $agent['specializations'])) {
                            return false;
                        }
                        break;
                }
            }
            return true;
        });
    }

    /**
     * Get agent's current workload
     */
    protected function getAgentWorkload(string $agentId): array
    {
        $cacheKey = "agent_workload_{$agentId}";

        return Cache::remember($cacheKey, 60, function () use ($agentId) {
            try {
                $response = Http::get("{$this->ticketServiceUrl}/api/v1/agents/{$agentId}/workload");

                if (!$response->successful()) {
                    return [
                        'open_tickets' => 0,
                        'today_tickets' => 0,
                        'workload_score' => 0,
                    ];
                }

                return $response->json('data', [
                    'open_tickets' => 0,
                    'today_tickets' => 0,
                    'workload_score' => 0,
                ]);

            } catch (\Exception $e) {
                Log::warning("Failed to get agent workload", [
                    'agent_id' => $agentId,
                    'error' => $e->getMessage(),
                ]);
                return [
                    'open_tickets' => 0,
                    'today_tickets' => 0,
                    'workload_score' => 0,
                ];
            }
        });
    }

    /**
     * Select best agent from eligible agents
     */
    protected function selectBestAgent(array $eligibleAgents, array $strategy): array
    {
        if (empty($eligibleAgents)) {
            throw new \Exception("No eligible agents available");
        }

        switch ($strategy['type']) {
            case 'round_robin':
                return $this->selectByRoundRobin($eligibleAgents);

            case 'least_loaded':
                return $this->selectByLeastLoaded($eligibleAgents);

            case 'priority':
                return $this->selectByPriority($eligibleAgents, $strategy['priority_weight']);

            case 'vip':
                return $this->selectForVip($eligibleAgents);

            case 'random':
                return $eligibleAgents[array_rand($eligibleAgents)];

            default:
                return $this->selectByLeastLoaded($eligibleAgents);
        }
    }

    /**
     * Select agent using round-robin algorithm
     */
    protected function selectByRoundRobin(array $agents): array
    {
        // Get last assigned agent from cache
        $lastAssignedId = Cache::get('last_assigned_agent_id');

        if (!$lastAssignedId) {
            $selected = $agents[0];
        } else {
            // Find next agent in rotation
            $currentIndex = array_search($lastAssignedId, array_column($agents, 'id'));
            $nextIndex = ($currentIndex === false || $currentIndex >= count($agents) - 1) ? 0 : $currentIndex + 1;
            $selected = $agents[$nextIndex];
        }

        // Update last assigned agent
        Cache::put('last_assigned_agent_id', $selected['id'], 3600);

        return $selected;
    }

    /**
     * Select agent with least workload
     */
    protected function selectByLeastLoaded(array $agents): array
    {
        usort($agents, function ($a, $b) {
            $scoreA = $a['workload']['workload_score'] ?? 0;
            $scoreB = $b['workload']['workload_score'] ?? 0;
            return $scoreA <=> $scoreB;
        });

        return $agents[0];
    }

    /**
     * Select agent based on priority handling
     */
    protected function selectByPriority(array $agents, int $priorityWeight): array
    {
        // Filter agents who can handle priority tickets
        $priorityAgents = array_filter($agents, function ($agent) use ($priorityWeight) {
            $maxPriority = $agent['max_priority_tickets'] ?? 10;
            $currentPriority = $agent['workload']['priority_tickets'] ?? 0;
            return $currentPriority < $maxPriority;
        });

        if (empty($priorityAgents)) {
            $priorityAgents = $agents;
        }

        // Select least loaded among priority agents
        return $this->selectByLeastLoaded($priorityAgents);
    }

    /**
     * Select agent for VIP client
     */
    protected function selectForVip(array $agents): array
    {
        // Filter VIP-capable agents
        $vipAgents = array_filter($agents, function ($agent) {
            return $agent['handles_vip'] ?? false;
        });

        if (empty($vipAgents)) {
            $vipAgents = $agents;
        }

        // Among VIP agents, select the one with best performance
        usort($vipAgents, function ($a, $b) {
            $ratingA = $a['performance_rating'] ?? 3;
            $ratingB = $b['performance_rating'] ?? 3;
            return $ratingB <=> $ratingA; // Higher rating first
        });

        return $vipAgents[0];
    }

    /**
     * Perform the actual ticket assignment
     */
    protected function performAssignment(string $ticketId, string $agentId, ?string $departmentId = null): array
    {
        try {
            $updateData = [
                'assigned_agent_id' => $agentId,
                'status' => 'open', // Change from 'new' to 'open'
            ];

            if ($departmentId) {
                $updateData['assigned_department_id'] = $departmentId;
            }

            $response = Http::put("{$this->ticketServiceUrl}/api/v1/tickets/{$ticketId}/assign", $updateData);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => 'Failed to assign ticket',
                ];
            }

            // Clear agent workload cache
            Cache::forget("agent_workload_{$agentId}");

            return [
                'success' => true,
                'agent_id' => $agentId,
                'ticket_id' => $ticketId,
            ];

        } catch (\Exception $e) {
            Log::error("Failed to perform ticket assignment", [
                'ticket_id' => $ticketId,
                'agent_id' => $agentId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Reassign ticket to another agent
     */
    public function reassignTicket(string $ticketId, string $fromAgentId, string $toAgentId, string $reason = null): array
    {
        try {
            // Validate target agent exists and is available
            $targetAgent = $this->validateAgent($toAgentId);
            if (!$targetAgent) {
                return [
                    'success' => false,
                    'message' => 'Target agent not found or unavailable',
                ];
            }

            // Perform reassignment
            $response = Http::put("{$this->ticketServiceUrl}/api/v1/tickets/{$ticketId}/reassign", [
                'from_agent_id' => $fromAgentId,
                'to_agent_id' => $toAgentId,
                'reason' => $reason,
                'reassigned_at' => now(),
            ]);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => 'Failed to reassign ticket',
                ];
            }

            // Clear workload cache for both agents
            Cache::forget("agent_workload_{$fromAgentId}");
            Cache::forget("agent_workload_{$toAgentId}");

            Log::info("Ticket reassigned", [
                'ticket_id' => $ticketId,
                'from_agent_id' => $fromAgentId,
                'to_agent_id' => $toAgentId,
                'reason' => $reason,
            ]);

            return [
                'success' => true,
                'message' => 'Ticket successfully reassigned',
            ];

        } catch (\Exception $e) {
            Log::error("Failed to reassign ticket", [
                'ticket_id' => $ticketId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Validate agent exists and is available
     */
    protected function validateAgent(string $agentId): ?array
    {
        try {
            $response = Http::get("{$this->authServiceUrl}/api/v1/users/{$agentId}");

            if (!$response->successful()) {
                return null;
            }

            $agent = $response->json('data');

            if ($agent['role'] !== 'agent' || !$agent['is_active']) {
                return null;
            }

            return $agent;

        } catch (\Exception $e) {
            Log::error("Failed to validate agent", [
                'agent_id' => $agentId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get category-specific assignment rules
     */
    protected function getCategoryAssignmentRules(string $categoryId): ?array
    {
        // This would typically be fetched from database
        // For now, using hardcoded rules
        $rules = [
            'billing' => [
                'department_id' => 'billing_dept',
                'filters' => ['specializations' => ['billing', 'finance']],
            ],
            'technical' => [
                'department_id' => 'tech_dept',
                'filters' => ['specializations' => ['technical', 'development']],
            ],
            'sales' => [
                'department_id' => 'sales_dept',
                'filters' => ['specializations' => ['sales', 'business']],
            ],
        ];

        return $rules[$categoryId] ?? null;
    }

    /**
     * Get keyword-based assignment rules
     */
    protected function getKeywordAssignmentRules(string $subject): array
    {
        $rules = [];

        // Technical keywords
        if (preg_match('/\b(bug|error|crash|issue|problem)\b/i', $subject)) {
            $rules['specializations'] = ['technical_support'];
        }

        // Billing keywords
        if (preg_match('/\b(payment|invoice|billing|refund|charge)\b/i', $subject)) {
            $rules['specializations'] = ['billing'];
        }

        // Urgent keywords
        if (preg_match('/\b(urgent|emergency|critical|asap)\b/i', $subject)) {
            $rules['skill_level'] = 'senior';
        }

        return $rules;
    }
}