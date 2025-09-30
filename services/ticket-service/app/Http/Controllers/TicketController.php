<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketHistory;
use App\Models\Category;
use App\Services\TicketAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TicketController extends Controller
{
    protected $assignmentService;

    public function __construct(TicketAssignmentService $assignmentService)
    {
        $this->assignmentService = $assignmentService;
    }

    /**
     * Safely get the authenticated user ID
     */
    protected function getUserId(): ?string
    {
        try {
            return auth()->id();
        } catch (\Exception $e) {
            return null;
        }
    }
    /**
     * Get all tickets with filtering and pagination
     */
    public function index(Request $request): JsonResponse
    {
        $this->validate($request, [
            'status' => 'string|in:new,open,pending,on_hold,resolved,closed,cancelled',
            'priority' => 'string|in:low,medium,high,urgent',
            'assigned_agent_id' => 'string|uuid',
            'client_id' => 'string|uuid',
            'category_id' => 'string|uuid',
            'search' => 'string|max:255',
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'sort' => 'string|in:created_at,updated_at,priority,status,subject',
            'direction' => 'string|in:asc,desc'
        ]);

        $query = Ticket::active()->with(['category']);

        // Apply filters
        if ($request->has('status')) {
            $query->byStatus($request->status);
        }

        if ($request->has('priority')) {
            $query->byPriority($request->priority);
        }

        if ($request->has('assigned_agent_id')) {
            if ($request->assigned_agent_id === 'unassigned') {
                $query->unassigned();
            } else {
                $query->assignedTo($request->assigned_agent_id);
            }
        }

        if ($request->has('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Search functionality
        if ($request->has('search') && $request->search) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('subject', 'ILIKE', "%{$searchTerm}%")
                  ->orWhere('description', 'ILIKE', "%{$searchTerm}%")
                  ->orWhere('ticket_number', 'ILIKE', "%{$searchTerm}%");
            });
        }

        // Sorting
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        // Pagination
        $perPage = min($request->get('per_page', 20), 100);
        $tickets = $query->paginate($perPage);

        // Manually load assigned agent data and client data
        $ticketsData = $tickets->items();
        $agentIds = collect($ticketsData)->pluck('assigned_agent_id')->filter()->unique()->toArray();
        $clientIds = collect($ticketsData)->pluck('client_id')->filter()->unique()->toArray();

        // Load agents
        if (!empty($agentIds)) {
            $agents = DB::table('users')
                ->whereIn('id', $agentIds)
                ->get()
                ->keyBy('id');

            foreach ($ticketsData as $ticket) {
                if ($ticket->assigned_agent_id && isset($agents[$ticket->assigned_agent_id])) {
                    $ticket->assigned_agent = $agents[$ticket->assigned_agent_id];
                } else {
                    $ticket->assigned_agent = null;
                }
            }
        } else {
            foreach ($ticketsData as $ticket) {
                $ticket->assigned_agent = null;
            }
        }

        // Load clients
        if (!empty($clientIds)) {
            $clients = DB::table('clients')
                ->whereIn('id', $clientIds)
                ->get()
                ->keyBy('id');

            foreach ($ticketsData as $ticket) {
                if ($ticket->client_id && isset($clients[$ticket->client_id])) {
                    $ticket->client = $clients[$ticket->client_id];
                } else {
                    $ticket->client = null;
                }
            }
        } else {
            foreach ($ticketsData as $ticket) {
                $ticket->client = null;
            }
        }

        return response()->json([
            'success' => true,
            'data' => $ticketsData,
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
                'last_page' => $tickets->lastPage(),
                'from' => $tickets->firstItem(),
                'to' => $tickets->lastItem(),
            ]
        ]);
    }

    /**
     * Create a new ticket
     */
    public function store(Request $request): JsonResponse
    {
        $this->validate($request, [
            'subject' => 'required|string|max:500',
            'description' => 'required|string',
            'client_id' => 'required|string|uuid',
            'priority' => 'string|in:low,medium,high,urgent',
            'source' => 'required|string|in:email,web_form,chat,phone,social_media,api,internal',
            'category_id' => 'nullable|string|uuid|exists:categories,id',
            'assigned_agent_id' => 'nullable|string|uuid',
            'assigned_department_id' => 'nullable|string|uuid|exists:departments,id',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'custom_fields' => 'nullable|array',
            'auto_assign' => 'nullable|boolean', // New parameter to control auto-assignment
            'assignment_strategy' => 'nullable|string|in:least_busy,round_robin,priority_based,skill_based'
        ]);

        try {
            DB::beginTransaction();

            // Check if auto-assignment is enabled (default: true if no agent specified)
            $autoAssign = $request->get('auto_assign', !$request->has('assigned_agent_id'));
            $assignmentStrategy = $request->get('assignment_strategy', 'least_busy');

            $ticket = Ticket::create([
                'subject' => $request->subject,
                'description' => $request->description,
                'client_id' => $request->client_id,
                'priority' => $request->get('priority', Ticket::PRIORITY_MEDIUM),
                'source' => $request->source,
                'category_id' => $request->category_id,
                'assigned_agent_id' => $request->assigned_agent_id,
                'assigned_department_id' => $request->assigned_department_id,
                'tags' => $request->get('tags') ? $request->get('tags') : null,
                'custom_fields' => $request->get('custom_fields') ? $request->get('custom_fields') : null,
                'status' => Ticket::STATUS_NEW,
            ]);

            // Log ticket creation
            TicketHistory::create([
                'ticket_id' => $ticket->id,
                'user_id' => $this->getUserId(),
                'action' => 'created',
                'metadata' => [
                    'source' => $request->source,
                    'user_agent' => $request->header('User-Agent'),
                    'ip_address' => $request->ip(),
                    'auto_assign' => $autoAssign,
                ]
            ]);

            // Auto-assign if enabled and no agent was manually specified
            if ($autoAssign && !$request->assigned_agent_id) {
                Log::info('Attempting auto-assignment', [
                    'ticket_id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'strategy' => $assignmentStrategy
                ]);

                $assignedAgentId = $this->assignmentService->autoAssign($ticket, [
                    'strategy' => $assignmentStrategy
                ]);

                if ($assignedAgentId) {
                    // Log auto-assignment
                    TicketHistory::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => null, // System assignment
                        'action' => 'auto_assigned',
                        'new_value' => $assignedAgentId,
                        'metadata' => [
                            'strategy' => $assignmentStrategy,
                            'auto_assigned' => true
                        ]
                    ]);

                    // Refresh ticket to get updated assigned_agent_id
                    $ticket->refresh();

                    Log::info('Ticket auto-assigned successfully', [
                        'ticket_id' => $ticket->id,
                        'assigned_agent_id' => $assignedAgentId
                    ]);
                } else {
                    Log::warning('Auto-assignment failed - no available agent', [
                        'ticket_id' => $ticket->id,
                        'department_id' => $ticket->assigned_department_id
                    ]);
                }
            } elseif ($request->assigned_agent_id) {
                // Manual assignment - log it
                TicketHistory::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $this->getUserId(),
                    'action' => 'assigned',
                    'new_value' => $request->assigned_agent_id,
                ]);

                // Update status to open
                $ticket->update(['status' => Ticket::STATUS_OPEN]);
            }

            DB::commit();

            $ticket->load(['category']);

            return response()->json([
                'success' => true,
                'data' => $ticket,
                'message' => 'Ticket created successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();

            Log::error('Ticket creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Failed to create ticket',
                    'details' => config('app.debug') ? $e->getMessage() : 'Internal server error'
                ]
            ], 500);
        }
    }

    /**
     * Get ticket by message ID (for email threading)
     */
    public function getByMessageId(Request $request): JsonResponse
    {
        $this->validate($request, [
            'message_id' => 'required|string'
        ]);

        $messageId = $request->get('message_id');

        try {
            // Search in custom_fields->email_message_id
            $ticket = Ticket::active()
                ->whereRaw("custom_fields->>'email_message_id' = ?", [$messageId])
                ->with(['category'])
                ->first();

            if (!$ticket) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Ticket not found']
                ], 404);
            }

            // Manually load assigned agent and client data
            if ($ticket->assigned_agent_id) {
                $agent = DB::table('users')->where('id', $ticket->assigned_agent_id)->first();
                $ticket->assigned_agent = $agent;
            }

            if ($ticket->client_id) {
                $client = DB::table('clients')->where('id', $ticket->client_id)->first();
                $ticket->client = $client;
            }

            return response()->json([
                'success' => true,
                'data' => $ticket
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Failed to fetch ticket',
                    'details' => config('app.debug') ? $e->getMessage() : null
                ]
            ], 500);
        }
    }

    /**
     * Get ticket by ticket number
     */
    public function getByNumber(string $ticketNumber): JsonResponse
    {
        try {
            $ticket = Ticket::active()
                ->where('ticket_number', $ticketNumber)
                ->with(['category'])
                ->first();

            if (!$ticket) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Ticket not found']
                ], 404);
            }

            // Manually load assigned agent and client data
            if ($ticket->assigned_agent_id) {
                $agent = DB::table('users')->where('id', $ticket->assigned_agent_id)->first();
                $ticket->assigned_agent = $agent;
            }

            if ($ticket->client_id) {
                $client = DB::table('clients')->where('id', $ticket->client_id)->first();
                $ticket->client = $client;
            }

            return response()->json([
                'success' => true,
                'data' => $ticket
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Failed to fetch ticket',
                    'details' => config('app.debug') ? $e->getMessage() : null
                ]
            ], 500);
        }
    }

    /**
     * Get a specific ticket
     */
    public function show(string $id): JsonResponse
    {
        $ticket = Ticket::active()
            ->with(['category', 'comments', 'history'])
            ->find($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Ticket not found'
                ]
            ], 404);
        }

        // Fetch client information from client service
        if ($ticket->client_id) {
            try {
                $clientResponse = $this->fetchClientData($ticket->client_id);
                if ($clientResponse) {
                    $ticket->client = $clientResponse;
                }
            } catch (\Exception $e) {
                // Log error but don't fail the request
                error_log("Failed to fetch client data for ticket {$id}: " . $e->getMessage());
            }
        }

        // Load user information for comments (agents who replied)
        if ($ticket->comments) {
            $userIds = $ticket->comments->pluck('user_id')->filter()->unique()->toArray();
            if (!empty($userIds)) {
                $users = DB::table('users')
                    ->whereIn('id', $userIds)
                    ->get()
                    ->keyBy('id');

                foreach ($ticket->comments as $comment) {
                    if ($comment->user_id && isset($users[$comment->user_id])) {
                        $comment->user = $users[$comment->user_id];
                    }
                }
            }

            // Load client information for comments from clients
            $clientIds = $ticket->comments->pluck('client_id')->filter()->unique()->toArray();
            if (!empty($clientIds)) {
                try {
                    $clients = DB::table('clients')
                        ->whereIn('id', $clientIds)
                        ->get()
                        ->keyBy('id');

                    foreach ($ticket->comments as $comment) {
                        if ($comment->client_id && isset($clients[$comment->client_id])) {
                            $comment->client = $clients[$comment->client_id];
                        }
                    }
                } catch (\Exception $e) {
                    // Log error but don't fail the request
                    error_log("Failed to fetch client data for comments: " . $e->getMessage());
                }
            }
        }

        // Load assigned agent information
        if ($ticket->assigned_agent_id) {
            $agent = DB::table('users')->where('id', $ticket->assigned_agent_id)->first();
            $ticket->assigned_agent = $agent;
        }

        return response()->json([
            'success' => true,
            'data' => $ticket
        ]);
    }

    /**
     * Fetch client data from client service
     */
    private function fetchClientData(string $clientId)
    {
        $clientServiceUrl = env('CLIENT_SERVICE_URL', 'http://localhost:8003');

        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => "{$clientServiceUrl}/api/v1/clients/{$clientId}",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'Content-Type: application/json'
                ]
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                return $data['data'] ?? null;
            }
        } catch (\Exception $e) {
            error_log("Error fetching client data: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Update a ticket
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $ticket = Ticket::active()->find($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Ticket not found'
                ]
            ], 404);
        }

        $this->validate($request, [
            'subject' => 'string|max:500',
            'description' => 'string',
            'status' => 'string|in:new,open,pending,on_hold,resolved,closed,cancelled',
            'priority' => 'string|in:low,medium,high,urgent',
            'assigned_agent_id' => 'nullable|string|uuid',
            'category_id' => 'nullable|string|uuid|exists:categories,id',
            'tags' => 'array',
            'tags.*' => 'string|max:50',
            'custom_fields' => 'array'
        ]);

        try {
            DB::beginTransaction();

            $ticket->update($request->only([
                'subject',
                'description',
                'status',
                'priority',
                'assigned_agent_id',
                'category_id',
                'tags',
                'custom_fields'
            ]));

            DB::commit();

            $ticket->load(['category']);

            return response()->json([
                'success' => true,
                'data' => $ticket,
                'message' => 'Ticket updated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Failed to update ticket',
                    'details' => config('app.debug') ? $e->getMessage() : 'Internal server error'
                ]
            ], 500);
        }
    }

    /**
     * Delete (soft delete) a ticket
     */
    public function destroy(string $id): JsonResponse
    {
        $ticket = Ticket::active()->find($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Ticket not found'
                ]
            ], 404);
        }

        try {
            $ticket->update(['is_deleted' => true]);

            TicketHistory::create([
                'ticket_id' => $ticket->id,
                'user_id' => $this->getUserId(),
                'action' => 'deleted',
                'metadata' => [
                    'user_agent' => request()->header('User-Agent'),
                    'ip_address' => request()->ip(),
                ]
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ticket deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Failed to delete ticket',
                    'details' => config('app.debug') ? $e->getMessage() : 'Internal server error'
                ]
            ], 500);
        }
    }

    /**
     * Assign a ticket to an agent
     */
    public function assign(Request $request, string $id): JsonResponse
    {
        $ticket = Ticket::active()->find($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Ticket not found'
                ]
            ], 404);
        }

        $this->validate($request, [
            'assigned_agent_id' => 'required|string|uuid'
        ]);

        try {
            $oldAgent = $ticket->assigned_agent_id;
            $ticket->assign($request->assigned_agent_id);

            TicketHistory::create([
                'ticket_id' => $ticket->id,
                'user_id' => $this->getUserId(),
                'action' => 'assigned',
                'old_value' => $oldAgent,
                'new_value' => $request->assigned_agent_id,
            ]);

            return response()->json([
                'success' => true,
                'data' => $ticket,
                'message' => 'Ticket assigned successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Failed to assign ticket',
                    'details' => config('app.debug') ? $e->getMessage() : 'Internal server error'
                ]
            ], 500);
        }
    }

    /**
     * Add a comment to a ticket
     */
    public function addComment(Request $request, string $id): JsonResponse
    {
        $ticket = Ticket::active()->find($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Ticket not found'
                ]
            ], 404);
        }

        $this->validate($request, [
            'content' => 'required|string',
            'is_internal_note' => 'boolean',
            'client_id' => 'string|uuid',
            'client_email' => 'email',  // Allow client email for public comments
            'attachments' => 'array',
            'attachments.*.name' => 'string',
            'attachments.*.url' => 'string|url',
            'attachments.*.size' => 'integer',
            'attachments.*.type' => 'string'
        ]);

        try {
            DB::beginTransaction();

            // Determine the comment author - NO AUTHENTICATION REQUIRED
            $userId = $this->getUserId();
            $clientId = null;
            $clientEmail = $request->client_email;

            // Check if this is coming from the public endpoint
            $isPublicEndpoint = str_contains($request->path(), '/public/');

            // If user is authenticated (agent/admin), set user_id and leave client_id as null
            // If user is NOT authenticated, it's from a client
            if ($userId) {
                // Comment from authenticated user (agent/admin) - user_id is set, client_id is null
                $clientId = null;
            } else {
                // Comment from client - set client_id, user_id is null
                $clientId = $request->client_id ?? $ticket->client_id;

                // For public endpoints and non-authenticated users, just allow it
                if ($isPublicEndpoint) {
                    // For public comments, use the ticket's client as author if no client specified
                    if (!$clientId) {
                        $clientId = $ticket->client_id;
                    }
                    // Allow internal notes to be false for public, but don't allow setting them to true
                    if ($request->get('is_internal_note', false)) {
                        // Silently convert to regular comment instead of blocking
                        $request->merge(['is_internal_note' => false]);
                    }
                }
            }

            // Prepare comment metadata
            $metadata = [];
            if ($clientEmail) {
                $metadata['client_email'] = $clientEmail;
            }

            $comment = TicketComment::create([
                'ticket_id' => $ticket->id,
                'user_id' => $userId,
                'client_id' => $clientId,
                'content' => $request->content,
                'is_internal_note' => $request->get('is_internal_note', false),
                'attachments' => $request->get('attachments', []),
                'metadata' => !empty($metadata) ? $metadata : null
            ]);

            DB::commit();

            // Send email notification to client if this is not an internal note
            if (!$request->get('is_internal_note', false)) {
                $this->sendCommentNotificationEmail($ticket, $comment);
            }

            return response()->json([
                'success' => true,
                'data' => $comment,
                'message' => 'Comment added successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Failed to add comment',
                    'details' => config('app.debug') ? $e->getMessage() : 'Internal server error'
                ]
            ], 500);
        }
    }

    /**
     * Get ticket history
     */
    public function history(string $id): JsonResponse
    {
        $ticket = Ticket::active()->find($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Ticket not found'
                ]
            ], 404);
        }

        $history = $ticket->history()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $history
        ]);
    }

    /**
     * Get dashboard statistics
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = [
                'total_tickets' => Ticket::active()->count(),
                'open_tickets' => Ticket::active()->whereIn('status', [
                    Ticket::STATUS_NEW,
                    Ticket::STATUS_OPEN,
                    Ticket::STATUS_PENDING,
                    Ticket::STATUS_ON_HOLD
                ])->count(),
                'resolved_today' => Ticket::active()
                    ->where('status', Ticket::STATUS_RESOLVED)
                    ->whereDate('resolved_at', today())
                    ->count(),
                'unassigned_tickets' => Ticket::active()->unassigned()->count(),
                'overdue_tickets' => Ticket::active()->overdue()->count(),
                'by_priority' => [
                    'urgent' => Ticket::active()->byPriority(Ticket::PRIORITY_URGENT)->count(),
                    'high' => Ticket::active()->byPriority(Ticket::PRIORITY_HIGH)->count(),
                    'medium' => Ticket::active()->byPriority(Ticket::PRIORITY_MEDIUM)->count(),
                    'low' => Ticket::active()->byPriority(Ticket::PRIORITY_LOW)->count(),
                ],
                'by_status' => [
                    'new' => Ticket::active()->byStatus(Ticket::STATUS_NEW)->count(),
                    'open' => Ticket::active()->byStatus(Ticket::STATUS_OPEN)->count(),
                    'pending' => Ticket::active()->byStatus(Ticket::STATUS_PENDING)->count(),
                    'on_hold' => Ticket::active()->byStatus(Ticket::STATUS_ON_HOLD)->count(),
                    'resolved' => Ticket::active()->byStatus(Ticket::STATUS_RESOLVED)->count(),
                    'closed' => Ticket::active()->byStatus(Ticket::STATUS_CLOSED)->count(),
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Failed to retrieve statistics',
                    'details' => config('app.debug') ? $e->getMessage() : 'Internal server error'
                ]
            ], 500);
        }
    }

    /**
     * Send email notification for new comment
     */
    private function sendCommentNotificationEmail($ticket, $comment): void
    {
        try {
            \Log::info('DEBUGGING: sendCommentNotificationEmail called', [
                'ticket_id' => $ticket->id,
                'ticket_custom_fields' => $ticket->custom_fields,
                'comment_id' => $comment->id
            ]);

            // Get client email
            $clientEmail = null;
            if ($ticket->client_id) {
                // Make a request to client service to get client info
                $clientServiceUrl = env('CLIENT_SERVICE_URL', 'http://localhost:8003');
                \Log::info('DEBUGGING: Trying client service', ['url' => $clientServiceUrl . '/api/v1/clients/' . $ticket->client_id]);

                $response = $this->makeHttpRequest($clientServiceUrl . '/api/v1/clients/' . $ticket->client_id);
                if ($response && $response['status'] === 200) {
                    $clientData = json_decode($response['body'], true);
                    $clientEmail = $clientData['data']['email'] ?? null;
                    \Log::info('DEBUGGING: Got client email from service', ['email' => $clientEmail]);
                }
            }

            // If we don't have a client email from service, try custom fields
            if (!$clientEmail && isset($ticket->custom_fields['original_from'])) {
                $clientEmail = $ticket->custom_fields['original_from'];
                \Log::info('DEBUGGING: Got client email from custom fields', ['email' => $clientEmail]);
            }

            if ($clientEmail) {
                \Log::info('DEBUGGING: Preparing to send email', ['to' => $clientEmail]);

                // Send notification to email service
                $emailServiceUrl = env('EMAIL_SERVICE_URL', 'http://localhost:8005');
                $emailData = [
                    'account_id' => 'fa36fbe6-15ef-4064-990c-37ae79ad9ff6', // Use your Gmail account
                    'to' => [$clientEmail],  // Must be an array
                    'subject' => 'Re: ' . $ticket->subject . ' [Ticket #' . $ticket->ticket_number . ']',
                    'body_html' => $this->formatEmailBody($ticket, $comment),
                    'body_plain' => strip_tags($comment->content) . "\n\n" .
                                   "View ticket: " . env('APP_URL', 'http://localhost:3000') . '/tickets/' . $ticket->id
                ];

                \Log::info('DEBUGGING: Sending email request', [
                    'url' => $emailServiceUrl . '/api/v1/emails/send',
                    'data' => $emailData
                ]);

                $result = $this->makeHttpRequest(
                    $emailServiceUrl . '/api/v1/emails/send',
                    'POST',
                    $emailData
                );

                \Log::info('DEBUGGING: Email service response', ['result' => $result]);
            } else {
                \Log::error('DEBUGGING: No client email found', [
                    'ticket_id' => $ticket->id,
                    'client_id' => $ticket->client_id,
                    'custom_fields' => $ticket->custom_fields
                ]);
            }
        } catch (\Exception $e) {
            // Log error but don't fail the comment creation
            \Log::error('Failed to send comment notification email', [
                'ticket_id' => $ticket->id,
                'comment_id' => $comment->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Format email body for ticket reply
     */
    private function formatEmailBody($ticket, $comment): string
    {
        $authorName = $this->getCommentAuthorName($comment);
        $ticketUrl = env('APP_URL', 'http://localhost:3000') . '/tickets/' . $ticket->id;

        return '
        <html>
        <body style="font-family: Arial, sans-serif; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2 style="color: #2c3e50;">Ticket Update: #' . $ticket->ticket_number . '</h2>
                <div style="background-color: #f8f9fa; border-left: 4px solid #007bff; padding: 15px; margin: 20px 0;">
                    <p style="margin: 0 0 10px 0;"><strong>From:</strong> ' . $authorName . '</p>
                    <div style="margin-top: 15px;">
                        ' . $comment->content . '
                    </div>
                </div>
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6;">
                    <p style="color: #6c757d; font-size: 14px;">
                        <a href="' . $ticketUrl . '" style="color: #007bff; text-decoration: none;">View Full Ticket</a> |
                        Ticket #' . $ticket->ticket_number . ' - ' . htmlspecialchars($ticket->subject) . '
                    </p>
                    <p style="color: #6c757d; font-size: 12px; margin-top: 10px;">
                        This is an automated message from AidlY Support System.
                    </p>
                </div>
            </div>
        </body>
        </html>';
    }

    /**
     * Get comment author name
     */
    private function getCommentAuthorName($comment): string
    {
        if ($comment->user_id) {
            // Try to get user info from request or database
            $user = request()->attributes->get('auth_user');
            if ($user && isset($user['name'])) {
                return $user['name'];
            }
            // Fallback: query database
            $userModel = \App\Models\User::find($comment->user_id);
            return $userModel ? $userModel->name : 'Support Agent';
        }
        return 'Customer';
    }

    /**
     * Make HTTP request helper
     */
    private function makeHttpRequest(string $url, string $method = 'GET', array $data = null): ?array
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CUSTOMREQUEST => $method,
        ]);

        if ($method === 'POST' && $data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json'
            ]);
        }

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception('cURL Error: ' . $error);
        }

        return [
            'status' => $status,
            'body' => $body
        ];
    }

    /**
     * Get ticket statistics grouped by client IDs
     * Used by client service to enrich client data with ticket counts
     */
    public function getTicketStatsByClients(Request $request): JsonResponse
    {
        $this->validate($request, [
            'client_ids' => 'required|string',
        ]);

        $clientIds = explode(',', $request->input('client_ids'));
        $clientIds = array_filter(array_map('trim', $clientIds));

        if (empty($clientIds)) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }

        // Query tickets grouped by client_id using parameter binding
        $placeholders = implode(',', array_fill(0, count($clientIds), '?'));

        $stats = DB::select("
            SELECT
                client_id,
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'new' THEN 1 END) as new,
                COUNT(CASE WHEN status = 'open' THEN 1 END) as open,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                COUNT(CASE WHEN status = 'on_hold' THEN 1 END) as on_hold,
                COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved,
                COUNT(CASE WHEN status = 'closed' THEN 1 END) as closed,
                COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled,
                COUNT(CASE WHEN status IN ('new', 'open', 'pending', 'on_hold') THEN 1 END) as active,
                COUNT(CASE WHEN status IN ('resolved', 'closed', 'cancelled') THEN 1 END) as inactive
            FROM tickets
            WHERE client_id IN ({$placeholders})
              AND is_deleted = false
            GROUP BY client_id
        ", $clientIds);

        // Format the response as a map
        $result = [];
        foreach ($stats as $stat) {
            $result[$stat->client_id] = [
                'total' => (int) $stat->total,
                'new' => (int) $stat->new,
                'open' => (int) $stat->open,
                'pending' => (int) $stat->pending,
                'on_hold' => (int) $stat->on_hold,
                'resolved' => (int) $stat->resolved,
                'closed' => (int) $stat->closed,
                'cancelled' => (int) $stat->cancelled,
                'active' => (int) $stat->active,
                'inactive' => (int) $stat->inactive,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }

    /**
     * Get total ticket count across all clients
     */
    public function getTotalTicketCount(Request $request): JsonResponse
    {
        $totalCount = DB::table('tickets')
            ->where('is_deleted', false)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => (int) $totalCount
            ]
        ]);
    }

    /**
     * Get agent workload statistics
     */
    public function getAgentWorkloads(Request $request): JsonResponse
    {
        $this->validate($request, [
            'department_id' => 'nullable|string|uuid'
        ]);

        try {
            $departmentId = $request->get('department_id');
            $workloads = $this->assignmentService->getAgentWorkloads($departmentId);

            return response()->json([
                'success' => true,
                'data' => $workloads
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to fetch agent workloads']
            ], 500);
        }
    }

    /**
     * Get available agents for assignment
     */
    public function getAvailableAgents(Request $request): JsonResponse
    {
        $this->validate($request, [
            'department_id' => 'nullable|string|uuid',
            'priority' => 'nullable|string|in:low,medium,high,urgent'
        ]);

        try {
            $agents = $this->assignmentService->getAvailableAgents(
                $request->get('department_id'),
                $request->get('priority')
            );

            return response()->json([
                'success' => true,
                'data' => $agents
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to fetch available agents']
            ], 500);
        }
    }

    /**
     * Manually trigger auto-assignment for unassigned tickets
     */
    public function bulkAutoAssign(Request $request): JsonResponse
    {
        $this->validate($request, [
            'department_id' => 'nullable|string|uuid',
            'strategy' => 'nullable|string|in:least_busy,round_robin,priority_based,skill_based',
            'limit' => 'nullable|integer|min:1|max:100'
        ]);

        try {
            $limit = $request->get('limit', 10);
            $strategy = $request->get('strategy', 'least_busy');
            $departmentId = $request->get('department_id');

            // Get unassigned tickets
            $query = Ticket::whereNull('assigned_agent_id')
                ->whereIn('status', [Ticket::STATUS_NEW, Ticket::STATUS_OPEN])
                ->where('is_deleted', false);

            if ($departmentId) {
                $query->where('assigned_department_id', $departmentId);
            }

            $tickets = $query->limit($limit)->get();

            $results = [
                'total_processed' => 0,
                'assigned' => 0,
                'failed' => 0,
                'tickets' => []
            ];

            foreach ($tickets as $ticket) {
                $results['total_processed']++;

                $assignedAgentId = $this->assignmentService->autoAssign($ticket, [
                    'strategy' => $strategy
                ]);

                if ($assignedAgentId) {
                    $results['assigned']++;
                    $results['tickets'][] = [
                        'ticket_id' => $ticket->id,
                        'ticket_number' => $ticket->ticket_number,
                        'assigned_agent_id' => $assignedAgentId,
                        'status' => 'assigned'
                    ];

                    // Log the assignment
                    TicketHistory::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $this->getUserId(),
                        'action' => 'bulk_auto_assigned',
                        'new_value' => $assignedAgentId,
                        'metadata' => ['strategy' => $strategy]
                    ]);
                } else {
                    $results['failed']++;
                    $results['tickets'][] = [
                        'ticket_id' => $ticket->id,
                        'ticket_number' => $ticket->ticket_number,
                        'status' => 'failed',
                        'reason' => 'No available agent'
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $results,
                'message' => "Assigned {$results['assigned']} out of {$results['total_processed']} tickets"
            ]);

        } catch (\Exception $e) {
            Log::error('Bulk auto-assignment failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => ['message' => 'Bulk auto-assignment failed']
            ], 500);
        }
    }

    /**
     * Rebalance workload across agents
     */
    public function rebalanceWorkload(Request $request): JsonResponse
    {
        $this->validate($request, [
            'department_id' => 'nullable|string|uuid'
        ]);

        try {
            $departmentId = $request->get('department_id');
            $results = $this->assignmentService->rebalanceWorkload($departmentId);

            return response()->json([
                'success' => true,
                'data' => $results,
                'message' => "Rebalanced {$results['reassigned']} tickets across {$results['agents_balanced']} agents"
            ]);

        } catch (\Exception $e) {
            Log::error('Workload rebalancing failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => ['message' => 'Workload rebalancing failed']
            ], 500);
        }
    }

    /**
     * Delete all tickets for a specific client
     * This is called when a client is deleted from the client service
     */
    public function deleteClientTickets(string $clientId): JsonResponse
    {
        try {
            // Get all tickets for this client BEFORE starting transaction
            $tickets = Ticket::where('client_id', $clientId)->get();

            if ($tickets->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No tickets found for this client',
                    'deleted_count' => 0
                ]);
            }

            $deletedCount = 0;
            $ticketIds = $tickets->pluck('id')->toArray();

            DB::beginTransaction();

            // Bulk delete related data for better performance
            // 1. Delete email_queue references (cross-service FK constraint)
            DB::table('email_queue')->whereIn('ticket_id', $ticketIds)->delete();

            // 2. Delete all ticket comments for these tickets
            TicketComment::whereIn('ticket_id', $ticketIds)->delete();

            // 3. Delete all ticket history for these tickets
            TicketHistory::whereIn('ticket_id', $ticketIds)->delete();

            // 4. Delete attachments if table exists
            $tableExists = DB::select("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'ticket_attachments')");
            if ($tableExists[0]->exists) {
                DB::table('ticket_attachments')->whereIn('ticket_id', $ticketIds)->delete();
            }

            // 5. Hard delete all tickets
            $deletedCount = Ticket::whereIn('id', $ticketIds)->delete();

            DB::commit();

            \Illuminate\Support\Facades\Log::info('Client tickets deleted', [
                'client_id' => $clientId,
                'tickets_deleted' => $deletedCount
            ]);

            return response()->json([
                'success' => true,
                'message' => "Successfully deleted {$deletedCount} tickets for client",
                'deleted_count' => $deletedCount
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            \Illuminate\Support\Facades\Log::error('Failed to delete client tickets', [
                'client_id' => $clientId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DELETE_FAILED',
                    'message' => 'Failed to delete client tickets: ' . $e->getMessage()
                ]
            ], 500);
        }
    }
}