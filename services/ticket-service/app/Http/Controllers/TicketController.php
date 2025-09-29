<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketHistory;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
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

        return response()->json([
            'success' => true,
            'data' => $tickets->items(),
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
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'custom_fields' => 'nullable|array'
        ]);

        try {
            DB::beginTransaction();

            $ticket = Ticket::create([
                'subject' => $request->subject,
                'description' => $request->description,
                'client_id' => $request->client_id,
                'priority' => $request->get('priority', Ticket::PRIORITY_MEDIUM),
                'source' => $request->source,
                'category_id' => $request->category_id,
                'assigned_agent_id' => $request->assigned_agent_id,
                'tags' => $request->get('tags') ? $request->get('tags') : null,
                'custom_fields' => $request->get('custom_fields') ? $request->get('custom_fields') : null,
                'status' => $request->assigned_agent_id ? Ticket::STATUS_OPEN : Ticket::STATUS_NEW,
            ]);

            // Log ticket creation
            TicketHistory::create([
                'ticket_id' => $ticket->id,
                'user_id' => $this->getUserId(), // Handle unauthenticated requests safely
                'action' => 'created',
                'metadata' => [
                    'source' => $request->source,
                    'user_agent' => $request->header('User-Agent'),
                    'ip_address' => $request->ip(),
                ]
            ]);

            // If assigned, log assignment
            if ($request->assigned_agent_id) {
                TicketHistory::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $this->getUserId(), // Handle unauthenticated requests safely
                    'action' => 'assigned',
                    'new_value' => $request->assigned_agent_id,
                ]);
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
            $clientId = $request->client_id ?? $ticket->client_id;
            $clientEmail = $request->client_email;

            // Check if this is coming from the public endpoint
            $isPublicEndpoint = str_contains($request->path(), '/public/');

            // For public endpoints and non-authenticated users, just allow it
            // We don't enforce authentication - anyone can comment
            if ($isPublicEndpoint) {
                // For public comments, use the ticket's client as author if no client specified
                if (!$clientId) {
                    $clientId = $ticket->client_id;
                }
                // Allow internal notes to be false for public, but don't allow setting them to true
                if ($request->get('is_internal_note', false) && !$userId) {
                    // Silently convert to regular comment instead of blocking
                    $request->merge(['is_internal_note' => false]);
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
}