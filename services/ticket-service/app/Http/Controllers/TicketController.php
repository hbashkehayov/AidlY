<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketHistory;
use App\Models\Category;
use App\Services\TicketAssignmentService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TicketController extends Controller
{
    protected $assignmentService;
    protected $notificationService;

    public function __construct(TicketAssignmentService $assignmentService, NotificationService $notificationService)
    {
        $this->assignmentService = $assignmentService;
        $this->notificationService = $notificationService;
    }

    /**
     * Safely get the authenticated user ID
     */
    protected function getUserId(): ?string
    {
        try {
            // Try to get from request attributes (set by JwtMiddleware)
            $authUser = request()->attributes->get('auth_user');
            if ($authUser && isset($authUser['id'])) {
                return $authUser['id'];
            }

            // Fallback to request->user if available
            $user = request()->input('user');
            if ($user && isset($user['id'])) {
                return $user['id'];
            }

            // Last resort - try auth()->id() (might work in some contexts)
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
            'status' => 'string|in:open,pending,on_hold,resolved,closed,cancelled',
            'exclude_status' => 'string|in:open,pending,on_hold,resolved,closed,cancelled',
            'priority' => 'string|in:low,medium,high,urgent',
            'assigned_agent_id' => 'string|uuid',
            'client_id' => 'string|uuid',
            'category_id' => 'string|uuid',
            'search' => 'string|max:255',
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'sort' => 'string|in:default,created_at,updated_at,priority,status,subject',
            'direction' => 'string|in:asc,desc',
            'archived' => 'string|in:true,false',
            'unassigned' => 'string|in:true,false'
        ]);

        // Handle archived vs active tickets
        if ($request->get('archived') === 'true') {
            // Show only archived tickets
            $query = Ticket::where('is_deleted', false)
                          ->where('is_archived', true)
                          ->with(['category', 'attachments']);
        } else {
            // Show only active (non-archived) tickets
            $query = Ticket::active()->with(['category', 'attachments']);
        }

        // Visibility filter: Non-admin users can only see their own tickets or unassigned tickets
        $userId = $this->getUserId();
        $userRole = null;

        // Try to get user role from request attributes (set by JwtMiddleware)
        try {
            $authUser = $request->attributes->get('auth_user');
            if ($authUser && isset($authUser['role'])) {
                $userRole = $authUser['role'];
            }
        } catch (\Exception $e) {
            // Ignore if not available
        }

        // If not admin, restrict to assigned tickets or unassigned tickets only
        if ($userRole !== 'admin' && $userId) {
            $query->where(function ($q) use ($userId) {
                $q->where('assigned_agent_id', $userId)
                  ->orWhereNull('assigned_agent_id');
            });
        }

        // Apply filters
        if ($request->has('status')) {
            $query->byStatus($request->status);
        }

        // Exclude specific status if requested (e.g., exclude closed tickets)
        if ($request->has('exclude_status')) {
            $query->where('status', '!=', $request->exclude_status);
        }

        if ($request->has('priority')) {
            $query->byPriority($request->priority);
        }

        // Handle unassigned filter
        if ($request->get('unassigned') === 'true') {
            $query->unassigned();
        } elseif ($request->has('assigned_agent_id')) {
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

        // Sorting logic
        $sortField = $request->get('sort', 'default');
        $sortDirection = $request->get('direction', 'desc');

        // Default sorting: unread priority + most recent
        if ($sortField === 'default' && $userId) {
            // Sort by unread count first (tickets with unread appear first), then by updated_at
            $query->orderByRaw('
                CASE WHEN (
                    SELECT COUNT(*)
                    FROM ticket_comments
                    WHERE ticket_comments.ticket_id = tickets.id
                    AND (
                        ticket_comments.is_read = false
                        OR (ticket_comments.is_read = true AND ticket_comments.read_by != ?)
                        OR (ticket_comments.is_read = true AND ticket_comments.read_by IS NULL)
                    )
                ) > 0 THEN 0 ELSE 1 END
            ', [$userId])
            ->orderBy('updated_at', 'desc');
        } elseif ($sortField === 'priority') {
            // Custom priority order: urgent -> high -> medium -> low
            $query->orderByRaw("
                CASE priority
                    WHEN 'urgent' THEN 1
                    WHEN 'high' THEN 2
                    WHEN 'medium' THEN 3
                    WHEN 'low' THEN 4
                    ELSE 5
                END ASC");
        } elseif ($sortField === 'status') {
            // Custom status order: open -> pending -> on_hold -> resolved -> closed -> cancelled
            $query->orderByRaw("
                CASE status
                    WHEN 'open' THEN 1
                    WHEN 'pending' THEN 2
                    WHEN 'on_hold' THEN 3
                    WHEN 'resolved' THEN 4
                    WHEN 'closed' THEN 5
                    WHEN 'cancelled' THEN 6
                    ELSE 7
                END ASC");
        } else {
            // Regular sorting for other fields (updated_at, created_at, etc.)
            $query->orderBy($sortField, $sortDirection);
        }

        // Pagination
        $perPage = min($request->get('per_page', 20), 100);
        $tickets = $query->paginate($perPage);

        // Manually load assigned agent data and client data
        $ticketsData = $tickets->items();
        $agentIds = collect($ticketsData)->pluck('assigned_agent_id')->filter()->unique()->toArray();
        $clientIds = collect($ticketsData)->pluck('client_id')->filter()->unique()->toArray();

        // Get current user ID for unread comment tracking
        $currentUserId = $this->getUserId();

        // Load unread comment counts for each ticket (for current user)
        if ($currentUserId) {
            $ticketIds = collect($ticketsData)->pluck('id')->toArray();

            // Count unread regular comments (not internal notes)
            // A comment is unread if there's no entry in comment_reads for this user
            // AND the comment was not created by the current user
            $unreadCounts = DB::table('ticket_comments as tc')
                ->select('tc.ticket_id', DB::raw('COUNT(*) as unread_count'))
                ->whereIn('tc.ticket_id', $ticketIds)
                ->where('tc.is_internal_note', false)
                ->where(function($q) use ($currentUserId) {
                    // Don't count user's own comments as unread
                    $q->whereNull('tc.user_id')
                      ->orWhere('tc.user_id', '!=', $currentUserId);
                })
                ->whereNotExists(function($query) use ($currentUserId) {
                    $query->select(DB::raw(1))
                          ->from('comment_reads')
                          ->whereRaw('comment_reads.comment_id = tc.id')
                          ->where('comment_reads.user_id', $currentUserId);
                })
                ->groupBy('tc.ticket_id')
                ->get()
                ->keyBy('ticket_id');

            // Count unread internal notes separately
            // Internal notes created by the user themselves are NEVER counted as unread
            $unreadInternalCounts = DB::table('ticket_comments as tc')
                ->select('tc.ticket_id', DB::raw('COUNT(*) as unread_count'))
                ->whereIn('tc.ticket_id', $ticketIds)
                ->where('tc.is_internal_note', true)
                ->where('tc.user_id', '!=', $currentUserId) // Don't count user's own notes
                ->whereNotExists(function($query) use ($currentUserId) {
                    $query->select(DB::raw(1))
                          ->from('comment_reads')
                          ->whereRaw('comment_reads.comment_id = tc.id')
                          ->where('comment_reads.user_id', $currentUserId);
                })
                ->groupBy('tc.ticket_id')
                ->get()
                ->keyBy('ticket_id');

            // Add unread counts to each ticket
            foreach ($ticketsData as $ticket) {
                $ticket->unread_comments_count = isset($unreadCounts[$ticket->id])
                    ? (int) $unreadCounts[$ticket->id]->unread_count
                    : 0;
                $ticket->unread_internal_notes_count = isset($unreadInternalCounts[$ticket->id])
                    ? (int) $unreadInternalCounts[$ticket->id]->unread_count
                    : 0;
            }
        } else {
            // No user logged in, set all unread counts to 0
            foreach ($ticketsData as $ticket) {
                $ticket->unread_comments_count = 0;
                $ticket->unread_internal_notes_count = 0;
            }
        }

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
     * Create a new ticket (always unassigned - users must claim tickets)
     */
    public function store(Request $request): JsonResponse
    {
        $this->validate($request, [
            'subject' => 'required|string|max:500',
            'description' => 'required|string',
            'description_html' => 'nullable|string',
            'client_id' => 'required|string|uuid',
            'priority' => 'string|in:low,medium,high,urgent',
            'source' => 'required|string|in:email,web_form,chat,phone,social_media,api,internal',
            'category_id' => 'nullable|string|uuid|exists:categories,id',
            'assigned_department_id' => 'nullable|string|uuid|exists:departments,id',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'custom_fields' => 'nullable|array'
        ]);

        try {
            DB::beginTransaction();

            // Create ticket as unassigned - users must claim tickets
            $ticket = Ticket::create([
                'subject' => $request->subject,
                'description' => $request->description,
                'description_html' => $request->get('description_html'), // HTML version with embedded inline images
                'client_id' => $request->client_id,
                'priority' => $request->get('priority', Ticket::PRIORITY_MEDIUM),
                'source' => $request->source,
                'category_id' => $request->category_id,
                'assigned_agent_id' => null, // Always null - users claim tickets
                'assigned_department_id' => $request->assigned_department_id,
                'tags' => $request->get('tags') ? $request->get('tags') : null,
                'custom_fields' => $request->get('custom_fields') ? $request->get('custom_fields') : null,
                'status' => Ticket::STATUS_OPEN, // New tickets start with 'open' status
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
                ]
            ]);

            DB::commit();

            $ticket->load(['category']);

            // Load client information before sending notification
            if ($ticket->client_id) {
                $client = DB::table('clients')->where('id', $ticket->client_id)->first();
                $ticket->client = $client;
            }

            // Send notification to all agents about new ticket
            try {
                $this->notificationService->notifyTicketCreated($ticket);
            } catch (\Exception $e) {
                Log::warning('Failed to send ticket creation notification', ['error' => $e->getMessage()]);
            }

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
            ->with(['category', 'comments', 'comments.commentAttachments', 'history', 'attachments'])
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

        // Load user information for comments FIRST (agents who replied)
        if ($ticket->comments) {
            $userIds = $ticket->comments->pluck('user_id')->filter()->unique()->toArray();
            if (!empty($userIds)) {
                try {
                    $users = DB::table('users')
                        ->whereIn('id', $userIds)
                        ->get()
                        ->keyBy('id');

                    foreach ($ticket->comments as $comment) {
                        if ($comment->user_id && isset($users[$comment->user_id])) {
                            $comment->user = $users[$comment->user_id];
                        }
                    }
                } catch (\Exception $e) {
                    error_log("Failed to load user data for comments: " . $e->getMessage());
                }
            }
        }

        // Filter internal notes based on visibility AFTER loading user data
        // Get current user ID from request attributes (set by auth middleware)
        $currentUserId = null;
        if (request()->attributes->has('auth_user')) {
            $authUser = request()->attributes->get('auth_user');
            $currentUserId = $authUser['id'] ?? null;
        }

        // Filter comments based on visibility
        if ($ticket->comments) {
            $ticket->comments = $ticket->comments->filter(function ($comment) use ($currentUserId) {
                return $comment->canBeViewedBy($currentUserId);
            })->values();
        }

        // Load client information for comments from clients
        if ($ticket->comments) {
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

        // Mark all comments in this ticket as read by the current user
        // Excluding comments/notes created by the user themselves
        if ($currentUserId && $ticket->comments) {
            try {
                // Get all comment IDs from this ticket that are NOT created by current user
                $commentIds = DB::table('ticket_comments')
                    ->where('ticket_id', $id)
                    ->where(function($q) use ($currentUserId) {
                        // Either created by someone else, or created by client (user_id is null)
                        $q->whereNull('user_id')
                          ->orWhere('user_id', '!=', $currentUserId);
                    })
                    ->pluck('id')
                    ->toArray();

                // Insert read records for each comment (use insertOrIgnore to avoid duplicates)
                if (!empty($commentIds)) {
                    $readRecords = array_map(function($commentId) use ($currentUserId) {
                        return [
                            'comment_id' => $commentId,
                            'user_id' => $currentUserId,
                            'read_at' => DB::raw('NOW()')
                        ];
                    }, $commentIds);

                    // Insert in chunks to avoid large queries
                    foreach (array_chunk($readRecords, 100) as $chunk) {
                        DB::table('comment_reads')->insertOrIgnore($chunk);
                    }
                }
            } catch (\Exception $e) {
                // Log error but don't fail the request
                Log::warning("Failed to mark comments as read for ticket {$id}", [
                    'error' => $e->getMessage(),
                    'user_id' => $currentUserId
                ]);
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
     * Mark a ticket as viewed (for visual indicators)
     */
    public function markViewed(string $id): JsonResponse
    {
        try {
            $ticket = Ticket::active()->find($id);

            if (!$ticket) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Ticket not found']
                ], 404);
            }

            // Only mark as viewed if it hasn't been viewed yet
            if (!$ticket->first_viewed_at) {
                $ticket->first_viewed_at = now();
                $ticket->save();

                Log::info('Ticket marked as viewed', [
                    'ticket_id' => $id,
                    'ticket_number' => $ticket->ticket_number,
                    'viewed_at' => $ticket->first_viewed_at
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $ticket->id,
                    'first_viewed_at' => $ticket->first_viewed_at
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to mark ticket as viewed', [
                'ticket_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to mark ticket as viewed']
            ], 500);
        }
    }

    /**
     * Update a ticket
     */
    public function update(Request $request, string $id): JsonResponse
    {
        // If restoring from archive, don't use active() scope
        // Otherwise, use active() scope to prevent updating deleted/archived tickets
        if ($request->has('is_archived') && $request->is_archived === false) {
            // Restoring archived ticket - find without active scope
            $ticket = Ticket::where('is_deleted', false)->find($id);
        } else {
            // Normal update - use active scope
            $ticket = Ticket::active()->find($id);
        }

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
            'custom_fields' => 'array',
            'is_archived' => 'boolean'
        ]);

        try {
            DB::beginTransaction();

            // Track if ticket is being restored from archive
            $isRestoring = $request->has('is_archived') &&
                          $request->is_archived === false &&
                          $ticket->is_archived === true;

            // Track if agent is being changed
            $oldAgent = $ticket->assigned_agent_id;
            $agentChanged = $request->has('assigned_agent_id') &&
                           $oldAgent !== $request->assigned_agent_id;

            $ticket->update($request->only([
                'subject',
                'description',
                'status',
                'priority',
                'assigned_agent_id',
                'category_id',
                'tags',
                'custom_fields',
                'is_archived'
            ]));

            // Log restore action in history
            if ($isRestoring) {
                TicketHistory::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $this->getUserId(),
                    'action' => 'restored',
                    'metadata' => [
                        'user_agent' => $request->header('User-Agent'),
                        'ip_address' => $request->ip(),
                        'ticket_number' => $ticket->ticket_number,
                        'subject' => $ticket->subject,
                    ]
                ]);
            }

            // Send notification if agent was changed
            if ($agentChanged && $request->assigned_agent_id) {
                // Log assignment history
                TicketHistory::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $this->getUserId(),
                    'action' => 'assigned',
                    'old_value' => $oldAgent,
                    'new_value' => $request->assigned_agent_id,
                ]);

                // Send notification to newly assigned agent
                try {
                    // Manually load client data from database
                    if ($ticket->client_id) {
                        $client = DB::table('clients')->where('id', $ticket->client_id)->first();
                        $ticket->client = $client;
                    }

                    $agent = DB::table('users')->where('id', $request->assigned_agent_id)->first();
                    if ($agent) {
                        Log::info('Sending assignment notification (from update)', [
                            'ticket_id' => $ticket->id,
                            'agent_id' => $agent->id,
                            'assigned_by' => $this->getUserId()
                        ]);

                        $this->notificationService->notifyTicketAssigned($ticket, $agent, $this->getUserId());
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to send assignment notification from update', [
                        'error' => $e->getMessage(),
                        'ticket_id' => $ticket->id,
                        'agent_id' => $request->assigned_agent_id
                    ]);
                }
            }

            DB::commit();

            $ticket->load(['category']);

            return response()->json([
                'success' => true,
                'data' => $ticket,
                'message' => $isRestoring ? 'Ticket restored successfully' : 'Ticket updated successfully'
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
     * Archive a ticket (soft delete)
     */
    public function destroy(string $id): JsonResponse
    {
        // Find ticket without active scope to allow archiving any ticket
        $ticket = Ticket::find($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Ticket not found'
                ]
            ], 404);
        }

        try {
            DB::beginTransaction();

            // Soft delete by marking as archived
            $ticket->is_archived = true;
            $ticket->save();

            // Create history record
            TicketHistory::create([
                'ticket_id' => $ticket->id,
                'user_id' => $this->getUserId(),
                'action' => 'archived',
                'metadata' => [
                    'user_agent' => request()->header('User-Agent'),
                    'ip_address' => request()->ip(),
                    'ticket_number' => $ticket->ticket_number,
                    'subject' => $ticket->subject,
                ]
            ]);

            DB::commit();

            Log::info('Ticket archived (soft deleted)', [
                'ticket_id' => $id,
                'ticket_number' => $ticket->ticket_number,
                'archived_by' => $this->getUserId()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ticket archived successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to archive ticket', [
                'ticket_id' => $id,
                'error' => $e->getMessage()
            ]);

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

            // Send notification to assigned agent
            try {
                // Manually load client data from database
                if ($ticket->client_id) {
                    $client = DB::table('clients')->where('id', $ticket->client_id)->first();
                    $ticket->client = $client;
                }

                $agent = DB::table('users')->where('id', $request->assigned_agent_id)->first();
                if ($agent) {
                    Log::info('Sending assignment notification', [
                        'ticket_id' => $ticket->id,
                        'agent_id' => $agent->id,
                        'assigned_by' => $this->getUserId(),
                        'client_loaded' => isset($ticket->client)
                    ]);

                    $this->notificationService->notifyTicketAssigned($ticket, $agent, $this->getUserId());

                    Log::info('Assignment notification sent successfully', [
                        'ticket_id' => $ticket->id,
                        'agent_id' => $agent->id
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to send assignment notification', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'ticket_id' => $ticket->id ?? null,
                    'agent_id' => $request->assigned_agent_id
                ]);
            }

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
     * Claim a ticket (self-assign)
     * Regular users: Can only claim unassigned tickets
     * Admins: Can claim any ticket, even if already assigned
     */
    public function claim(Request $request, string $id): JsonResponse
    {
        $userId = $this->getUserId();

        if (!$userId) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'User not authenticated'
                ]
            ], 401);
        }

        // Get user role from request attributes (set by JwtMiddleware)
        $userRole = null;
        try {
            $authUser = $request->attributes->get('auth_user');
            if ($authUser && isset($authUser['role'])) {
                $userRole = $authUser['role'];
            }
        } catch (\Exception $e) {
            // Ignore if not available
        }

        // Admins can claim any active ticket, regular users can only claim unassigned tickets
        if ($userRole === 'admin') {
            $ticket = Ticket::active()->find($id);
        } else {
            $ticket = Ticket::active()->whereNull('assigned_agent_id')->find($id);
        }

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => $userRole === 'admin'
                        ? 'Ticket not found'
                        : 'Ticket not found or already assigned'
                ]
            ], 404);
        }

        try {
            $oldAgent = $ticket->assigned_agent_id;
            $wasAssigned = !is_null($oldAgent);

            // Assign ticket to current user
            $ticket->assign($userId);

            TicketHistory::create([
                'ticket_id' => $ticket->id,
                'user_id' => $userId,
                'action' => $wasAssigned ? 'reclaimed' : 'claimed',
                'old_value' => $oldAgent,
                'new_value' => $userId,
                'metadata' => [
                    'self_assigned' => true,
                    'admin_claim' => $userRole === 'admin',
                    'was_assigned' => $wasAssigned,
                    'previous_agent_id' => $oldAgent,
                    'user_agent' => $request->header('User-Agent'),
                    'ip_address' => $request->ip(),
                ]
            ]);

            return response()->json([
                'success' => true,
                'data' => $ticket,
                'message' => $wasAssigned
                    ? 'Ticket re-claimed successfully'
                    : 'Ticket claimed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Ticket claim failed', [
                'error' => $e->getMessage(),
                'ticket_id' => $id,
                'user_id' => $userId
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Failed to claim ticket',
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

        // Check if ticket is closed or cancelled - reject non-internal comments
        // Allow resolved tickets to receive replies (they will auto-reopen)
        if (in_array($ticket->status, [Ticket::STATUS_CLOSED, Ticket::STATUS_CANCELLED]) && !$request->get('is_internal_note', false)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'This ticket is closed and cannot accept new replies',
                    'ticket_status' => $ticket->status
                ]
            ], 403);
        }

        // Validate request - now accepts JSON with base64 attachments
        $this->validate($request, [
            'content' => 'nullable|string',
            'body_html' => 'nullable|string', // HTML version with embedded inline images
            'body_plain' => 'nullable|string', // Plain text version
            'is_internal_note' => 'nullable|boolean',
            'client_id' => 'nullable|string|uuid',
            'client_email' => 'nullable|email',
            'attachments' => 'nullable|array',  // Array of base64 attachments or file uploads
            'attachments.*.filename' => 'sometimes|string',
            'attachments.*.content_base64' => 'sometimes|string',
            // Email metadata for Gmail-style rendering
            'from_address' => 'nullable|string',
            'to_addresses' => 'nullable|array',
            'cc_addresses' => 'nullable|array',
            'subject' => 'nullable|string',
            'headers' => 'nullable|array',
            'metadata' => 'nullable|array',
        ]);

        // Get content and attachments
        $content = $request->input('content');
        $hasAttachments = !empty($request->input('attachments'));
        $hasAttachmentsInMetadata = isset($request->metadata['has_attachments']) && $request->metadata['has_attachments'];

        // Additional validation: ensure either content or attachments are present
        // Email comments might have attachments in metadata that will be uploaded separately
        if (empty($content) && !$hasAttachments && !$hasAttachmentsInMetadata) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Either content or attachments must be provided'
                ]
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Determine the comment author - NO AUTHENTICATION REQUIRED
            $userId = $this->getUserId();
            $clientId = null;
            $clientEmail = $request->client_email;

            // Debug logging
            \Log::info('Comment creation attempt', [
                'userId_from_getUserId' => $userId,
                'auth_user_attributes' => request()->attributes->get('auth_user'),
                'request_user' => request()->input('user'),
                'authorization_header' => request()->header('Authorization') ? substr(request()->header('Authorization'), 0, 30) . '...' : 'none',
                'request_path' => $request->path()
            ]);

            // Check if this is coming from the public endpoint
            $isPublicEndpoint = str_contains($request->path(), '/public/');

            // If user is authenticated (agent/admin), set user_id and leave client_id as null
            // If user is NOT authenticated, it's from a client
            if ($userId) {
                // Comment from authenticated user (agent/admin) - user_id is set, client_id is null
                \Log::info('Comment from authenticated user', ['user_id' => $userId]);
                $clientId = null;
            } else {
                // Comment from client - set client_id, user_id is null
                \Log::info('Comment from unauthenticated request, treating as client');
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

            // Process file attachments if any
            $attachmentData = [];

            // Handle file uploads (from frontend)
            if ($request->hasFile('attachments')) {
                $files = $request->file('attachments');

                // Ensure it's an array
                if (!is_array($files)) {
                    $files = [$files];
                }

                foreach ($files as $file) {
                    if ($file && $file->isValid()) {
                        // Generate unique filename
                        $filename = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
                        $path = 'attachments/' . $ticket->id . '/' . $filename;

                        // Store in public disk (or configure MinIO)
                        $storedPath = $file->storeAs('attachments/' . $ticket->id, $filename, 'public');

                        $attachmentData[] = [
                            'id' => (string) \Illuminate\Support\Str::uuid(),
                            'filename' => $file->getClientOriginalName(),
                            'path' => $storedPath,
                            'url' => '/storage/' . $storedPath,
                            'mime_type' => $file->getMimeType(),
                            'size' => $file->getSize(),
                        ];
                    }
                }
            }
            // Handle attachment metadata (from email service with base64 content)
            elseif ($request->has('attachments') && is_array($request->attachments)) {
                foreach ($request->attachments as $attachment) {
                    // Skip inline images (content_id present)
                    if (isset($attachment['is_inline']) && $attachment['is_inline']) {
                        continue;
                    }

                    if (isset($attachment['content_base64']) && isset($attachment['filename'])) {
                        // Decode base64 content and save to disk
                        $content = base64_decode($attachment['content_base64']);
                        $filename = time() . '_' . uniqid() . '_' . $attachment['filename'];

                        // Save file to storage
                        $storedPath = 'attachments/' . $ticket->id . '/' . $filename;
                        \Storage::disk('public')->put($storedPath, $content);

                        $attachmentData[] = [
                            'id' => (string) \Illuminate\Support\Str::uuid(),
                            'filename' => $attachment['filename'],
                            'path' => $storedPath,
                            'url' => '/storage/' . $storedPath,
                            'mime_type' => $attachment['mime_type'] ?? 'application/octet-stream',
                            'size' => $attachment['size'] ?? strlen($content),
                        ];
                    }
                }
            }

            // If content is empty but attachments exist, provide default message
            $content = $request->content;
            if (empty($content) || trim(strip_tags($content)) === '') {
                if (!empty($attachmentData)) {
                    $attachmentCount = count($attachmentData);
                    $content = "<p>[Sent {$attachmentCount} attachment" . ($attachmentCount > 1 ? 's' : '') . "]</p>";
                } else {
                    $content = $request->content; // Keep original even if empty
                }
            }

            $comment = TicketComment::create([
                'ticket_id' => $ticket->id,
                'user_id' => $userId,
                'client_id' => $clientId,
                'content' => $content,
                'body_html' => $request->get('body_html'), // HTML version with embedded inline images
                'body_plain' => $request->get('body_plain'), // Plain text version
                'is_internal_note' => $request->get('is_internal_note', false),
                'attachments' => !empty($attachmentData) ? $attachmentData : null,
                // Email metadata for Gmail-style rendering
                'from_address' => $request->get('from_address'),
                'to_addresses' => $request->get('to_addresses'),
                'cc_addresses' => $request->get('cc_addresses'),
                'subject' => $request->get('subject'),
                'headers' => $request->get('headers'),
            ]);

            // Create proper Attachment records for each file (relational database)
            // This allows proper tracking of which attachment belongs to which comment
            if (!empty($attachmentData)) {
                foreach ($attachmentData as $attachmentItem) {
                    \App\Models\Attachment::create([
                        'ticket_id' => $ticket->id,
                        'comment_id' => $comment->id,
                        'uploaded_by_user_id' => $userId,
                        'uploaded_by_client_id' => $clientId,
                        'file_name' => $attachmentItem['filename'],
                        'file_type' => pathinfo($attachmentItem['filename'], PATHINFO_EXTENSION),
                        'file_size' => $attachmentItem['size'] ?? null,
                        'storage_path' => $attachmentItem['path'],
                        'mime_type' => $attachmentItem['mime_type'] ?? 'application/octet-stream',
                        'is_inline' => false,
                    ]);
                }

                Log::info('Created Attachment records for comment', [
                    'comment_id' => $comment->id,
                    'ticket_id' => $ticket->id,
                    'attachment_count' => count($attachmentData),
                ]);
            }

            // Automated status management (only for non-internal notes)
            if (!$request->get('is_internal_note', false)) {
                $this->updateTicketStatusOnReply($ticket, $userId, $clientId);
            }

            DB::commit();

            // Send notification webhook (for both agents and clients)
            if (!$request->get('is_internal_note', false)) {
                try {
                    // Get the author object
                    $author = null;
                    if ($userId) {
                        $author = DB::table('users')->where('id', $userId)->first();
                    } elseif ($clientId) {
                        $author = DB::table('clients')->where('id', $clientId)->first();
                    }

                    if ($author) {
                        $this->notificationService->notifyCommentAdded($ticket, $comment, $author);
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to send comment notification', ['error' => $e->getMessage()]);
                }

                // Load comment attachments relationship before sending email
                $comment->load('commentAttachments');

                // Send email notification to client if this is not an internal note
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

                // Generate Message-ID for email threading
                $messageId = $this->generateMessageId($ticket->id, $comment->id);

                // Get original message ID for threading
                $originalMessageId = $ticket->custom_fields['email_message_id'] ?? null;

                // Send notification to email service
                $emailServiceUrl = env('EMAIL_SERVICE_URL', 'http://localhost:8005');
                $emailData = [
                    'account_id' => 'fa36fbe6-15ef-4064-990c-37ae79ad9ff6', // Use your Gmail account
                    'to' => [$clientEmail],  // Must be an array
                    'subject' => 'Re: ' . $ticket->subject . ' [Ticket #' . $ticket->ticket_number . ']',
                    'body_html' => $this->formatEmailBody($ticket, $comment),
                    'body_plain' => strip_tags($comment->content) . "\n\n" .
                                   "View ticket: " . env('APP_URL', 'http://localhost:3000') . '/tickets/' . $ticket->id,
                    'message_id' => $messageId,  // Add Message-ID for threading
                    'in_reply_to' => $originalMessageId,  // Add In-Reply-To header
                ];

                // Add attachments if present
                // Handle both Collection (from relationship) and array (from JSONB)
                \Log::info('Checking comment attachments for email', [
                    'comment_id' => $comment->id,
                    'has_attachments' => !empty($comment->attachments),
                    'attachments_type' => !empty($comment->attachments) ? gettype($comment->attachments) : null,
                    'attachments_count' => !empty($comment->attachments) && is_countable($comment->attachments) ? count($comment->attachments) : 0,
                ]);

                if (!empty($comment->attachments)) {
                    $attachments = $comment->attachments;

                    // If it's a Collection, check if not empty and convert
                    if (is_object($attachments) && method_exists($attachments, 'isNotEmpty')) {
                        if ($attachments->isNotEmpty()) {
                            $emailData['attachments'] = $attachments->map(function($attachment) {
                                $storagePath = $attachment->storage_path ?? $attachment['path'];
                                // Files are stored in 'public' disk, so construct proper path
                                $fullPath = storage_path('app/public/' . $storagePath);

                                \Log::info('Preparing email attachment from Collection', [
                                    'filename' => $attachment->file_name,
                                    'storage_path' => $storagePath,
                                    'full_path' => $fullPath,
                                    'exists' => file_exists($fullPath),
                                ]);

                                return [
                                    'filename' => $attachment->file_name ?? $attachment['filename'],
                                    'path' => $fullPath,
                                    'mime_type' => $attachment->mime_type ?? $attachment['mime_type'] ?? 'application/octet-stream',
                                ];
                            })->toArray();
                        }
                    }
                    // If it's an array (from JSONB), process directly
                    elseif (is_array($attachments) && count($attachments) > 0) {
                        $emailData['attachments'] = array_map(function($attachment) {
                            $storagePath = $attachment['storage_path'] ?? $attachment['path'] ?? '';
                            // Files are stored in 'public' disk, so construct proper path
                            $fullPath = storage_path('app/public/' . $storagePath);

                            \Log::info('Preparing email attachment from array', [
                                'filename' => $attachment['file_name'] ?? $attachment['filename'],
                                'storage_path' => $storagePath,
                                'full_path' => $fullPath,
                                'exists' => file_exists($fullPath),
                            ]);

                            return [
                                'filename' => $attachment['file_name'] ?? $attachment['filename'] ?? 'attachment',
                                'path' => $fullPath,
                                'mime_type' => $attachment['mime_type'] ?? 'application/octet-stream',
                            ];
                        }, $attachments);
                    }
                }

                \Log::info('DEBUGGING: Sending email request', [
                    'url' => $emailServiceUrl . '/api/v1/emails/send',
                    'data' => $emailData,
                    'message_id' => $messageId
                ]);

                $result = $this->makeHttpRequest(
                    $emailServiceUrl . '/api/v1/emails/send',
                    'POST',
                    $emailData
                );

                \Log::info('DEBUGGING: Email service response', ['result' => $result]);

                // Store the Message-ID in the ticket for threading
                if ($result && $result['status'] === 200) {
                    $this->storeMessageIdInTicket($ticket->id, $comment->id, $messageId);
                }
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

        // Make images clickable
        $processedContent = $this->makeImagesClickable($comment->content);

        return '
        <html>
        <body style="font-family: Arial, sans-serif; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2 style="color: #2c3e50;">Ticket Update: ' . htmlspecialchars($ticket->subject) . '</h2>
                <div style="background-color: #f8f9fa; border-left: 4px solid #007bff; padding: 15px; margin: 20px 0;">
                    <p style="margin: 0 0 10px 0;"><strong>From:</strong> ' . $authorName . '</p>
                    <div style="margin-top: 15px;">
                        ' . $processedContent . '
                    </div>
                </div>
            </div>
        </body>
        </html>';
    }

    /**
     * Make images in HTML content clickable to open in full size
     */
    private function makeImagesClickable(string $content): string
    {
        // Pattern to match img tags
        $pattern = '/<img\s+([^>]*?)src=["\']([^"\']+)["\']([^>]*?)>/i';

        $replacement = function($matches) {
            $beforeSrc = $matches[1];
            $src = $matches[2];
            $afterSrc = $matches[3];

            // Build the img tag with cursor pointer style
            $imgTag = '<img ' . $beforeSrc . 'src="' . $src . '"' . $afterSrc;

            // Add cursor pointer style if not already present
            if (stripos($imgTag, 'style=') !== false) {
                // Add cursor to existing style
                $imgTag = preg_replace('/style=["\']([^"\']*)["\']/', 'style="$1; cursor: pointer;"', $imgTag);
            } else {
                // Add new style attribute
                $imgTag = str_replace('<img ', '<img style="cursor: pointer;" ', $imgTag);
            }

            $imgTag .= '>';

            // Wrap in anchor tag
            return '<a href="' . htmlspecialchars($src) . '" target="_blank" rel="noopener noreferrer">' . $imgTag . '</a>';
        };

        return preg_replace_callback($pattern, $replacement, $content);
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
            'agent_id' => 'nullable|string',
        ]);

        $clientIds = explode(',', $request->input('client_ids'));
        $clientIds = array_filter(array_map('trim', $clientIds));
        $agentId = $request->input('agent_id');

        if (empty($clientIds)) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }

        // Query tickets grouped by client_id using parameter binding
        $placeholders = implode(',', array_fill(0, count($clientIds), '?'));

        // Build WHERE clause with optional agent filter (exclude archived tickets)
        $whereClause = "WHERE client_id IN ({$placeholders}) AND is_deleted = false AND is_archived = false";
        $params = $clientIds;

        if ($agentId) {
            $whereClause .= " AND assigned_agent_id = ?";
            $params[] = $agentId;
        }

        $stats = DB::select("
            SELECT
                client_id,
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'open' THEN 1 END) as open,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                COUNT(CASE WHEN status = 'on_hold' THEN 1 END) as on_hold,
                COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved,
                COUNT(CASE WHEN status = 'closed' THEN 1 END) as closed,
                COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled,
                COUNT(CASE WHEN status IN ('open', 'pending', 'on_hold') THEN 1 END) as active,
                COUNT(CASE WHEN status IN ('resolved', 'closed', 'cancelled') THEN 1 END) as inactive
            FROM tickets
            {$whereClause}
            GROUP BY client_id
        ", $params);

        // Format the response as a map
        $result = [];
        foreach ($stats as $stat) {
            $result[$stat->client_id] = [
                'total' => (int) $stat->total,
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
            ->where('is_archived', false)
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
                ->where('status', Ticket::STATUS_OPEN)
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

    /**
     * Get ticket by sent message ID (for email threading - agent replies)
     */
    public function getBySentMessageId(Request $request): JsonResponse
    {
        $this->validate($request, [
            'message_id' => 'required|string|max:500'
        ]);

        $messageId = $request->input('message_id');

        try {
            // Search for ticket containing this message ID in sent_message_ids array
            $ticket = Ticket::whereRaw('? = ANY(sent_message_ids)', [$messageId])
                ->with(['client', 'assignedAgent', 'category'])
                ->first();

            if (!$ticket) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket not found for the given sent message ID',
                    'data' => null
                ], 404);
            }

            Log::debug('Found ticket by sent message ID', [
                'message_id' => $messageId,
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number
            ]);

            return response()->json([
                'success' => true,
                'data' => $ticket
            ]);

        } catch (\Exception $e) {
            Log::error('Error finding ticket by sent message ID', [
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error searching for ticket',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store sent message ID for email threading
     */
    public function storeMessageId(string $id, Request $request): JsonResponse
    {
        $this->validate($request, [
            'message_id' => 'required|string|max:500',
            'comment_id' => 'nullable|string|uuid'
        ]);

        $messageId = $request->input('message_id');
        $commentId = $request->input('comment_id');

        try {
            $ticket = Ticket::findOrFail($id);

            // Use the database function to append message ID (avoids duplicates)
            DB::statement('SELECT append_sent_message_id_to_ticket(?, ?)', [$id, $messageId]);

            // If comment ID provided, also store it in the comment record
            if ($commentId) {
                $comment = TicketComment::find($commentId);
                if ($comment && $comment->ticket_id === $id) {
                    $comment->sent_message_id = $messageId;
                    $comment->save();

                    Log::debug('Stored message ID in comment', [
                        'comment_id' => $commentId,
                        'message_id' => $messageId
                    ]);
                }
            }

            Log::info('Stored sent message ID in ticket', [
                'ticket_id' => $id,
                'ticket_number' => $ticket->ticket_number,
                'message_id' => $messageId,
                'comment_id' => $commentId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Message ID stored successfully',
                'data' => [
                    'ticket_id' => $id,
                    'message_id' => $messageId
                ]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error storing message ID in ticket', [
                'ticket_id' => $id,
                'message_id' => $messageId,
                'comment_id' => $commentId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error storing message ID',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate a unique Message-ID for email threading
     */
    private function generateMessageId(string $ticketId, ?string $commentId = null): string
    {
        $domain = parse_url(env('APP_URL', 'localhost'), PHP_URL_HOST) ?: 'aidly.local';
        $timestamp = time();
        $randomPart = substr(md5(uniqid('', true)), 0, 8);

        if ($commentId) {
            return "<ticket-{$ticketId}-comment-{$commentId}-{$timestamp}-{$randomPart}@{$domain}>";
        }

        return "<ticket-{$ticketId}-{$timestamp}-{$randomPart}@{$domain}>";
    }

    /**
     * Store the sent Message-ID in the ticket for threading
     */
    private function storeMessageIdInTicket(string $ticketId, ?string $commentId, string $messageId): void
    {
        try {
            // Use database function to append message ID
            DB::statement('SELECT append_sent_message_id_to_ticket(?, ?)', [$ticketId, $messageId]);

            // If comment ID provided, also store it in the comment record
            if ($commentId) {
                DB::table('ticket_comments')
                    ->where('id', $commentId)
                    ->update(['sent_message_id' => $messageId]);

                \Log::debug('Stored message ID in comment', [
                    'comment_id' => $commentId,
                    'message_id' => $messageId
                ]);
            }

            \Log::info('Stored sent message ID in ticket', [
                'ticket_id' => $ticketId,
                'message_id' => $messageId,
                'comment_id' => $commentId
            ]);

        } catch (\Exception $e) {
            \Log::error('Error storing Message-ID in ticket', [
                'ticket_id' => $ticketId,
                'comment_id' => $commentId,
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Automated status management based on who replies
     * - Agent reply → Set to "Pending" (waiting for client)
     * - Client reply → Set to "Open" (agent needs to respond)
     *
     * Note: Closed tickets do not accept client replies (enforced in addComment method)
     */
    private function updateTicketStatusOnReply($ticket, ?string $userId, ?string $clientId): void
    {
        $oldStatus = $ticket->status;
        $newStatus = null;

        // Agent/User replied
        if ($userId) {
            // Agent replied - set to "Pending" (waiting for client response)
            // Only skip if ticket is closed or cancelled
            if (!in_array($ticket->status, [Ticket::STATUS_CLOSED, Ticket::STATUS_CANCELLED])) {
                $newStatus = Ticket::STATUS_PENDING;
            }
        }
        // Client replied
        elseif ($clientId) {
            // Client replied - set to "Open" (needs agent attention)
            // Note: Closed tickets are blocked from receiving client replies in addComment method
            // Reopen resolved tickets when client replies
            if (in_array($ticket->status, [Ticket::STATUS_OPEN, Ticket::STATUS_PENDING, Ticket::STATUS_ON_HOLD, Ticket::STATUS_RESOLVED])) {
                $newStatus = Ticket::STATUS_OPEN;
            }
        }

        // Update status if it changed
        if ($newStatus && $newStatus !== $oldStatus) {
            $ticket->status = $newStatus;
            $ticket->save();

            // Log the status change
            TicketHistory::create([
                'ticket_id' => $ticket->id,
                'user_id' => $userId ?? null,
                'action' => 'status_changed',
                'old_value' => $oldStatus,
                'new_value' => $newStatus,
                'metadata' => [
                    'auto_changed' => true,
                    'reason' => $userId ? 'agent_replied' : 'client_replied',
                    'reopened' => in_array($oldStatus, [Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED])
                ]
            ]);

            \Log::info('Ticket status auto-updated on reply', [
                'ticket_id' => $ticket->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'user_id' => $userId,
                'client_id' => $clientId,
                'reopened' => in_array($oldStatus, [Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED])
            ]);
        }
    }

    /**
     * Unassign all tickets for a specific user (called when user is deleted)
     * This ensures tickets are not lost when a user is deleted
     *
     * @param string $userId
     * @return JsonResponse
     */
    public function unassignUserTickets(string $userId): JsonResponse
    {
        try {
            // Find all tickets assigned to this user
            $tickets = Ticket::where('assigned_agent_id', $userId)->get();
            $ticketCount = $tickets->count();

            if ($ticketCount === 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'No tickets were assigned to this user',
                    'data' => [
                        'tickets_unassigned' => 0
                    ]
                ]);
            }

            // Unassign each ticket
            foreach ($tickets as $ticket) {
                $ticket->assigned_agent_id = null;
                $ticket->save();

                // Log the unassignment in ticket history
                TicketHistory::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => null,
                    'action' => 'unassigned',
                    'old_value' => $userId,
                    'new_value' => null,
                    'metadata' => [
                        'reason' => 'user_deleted',
                        'auto_unassigned' => true
                    ]
                ]);
            }

            Log::info('Unassigned all tickets for deleted user', [
                'user_id' => $userId,
                'tickets_count' => $ticketCount
            ]);

            return response()->json([
                'success' => true,
                'message' => "Successfully unassigned {$ticketCount} ticket(s)",
                'data' => [
                    'tickets_unassigned' => $ticketCount
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to unassign tickets for deleted user', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to unassign tickets',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}