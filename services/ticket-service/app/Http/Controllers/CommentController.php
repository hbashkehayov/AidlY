<?php

namespace App\Http\Controllers;

use App\Models\TicketComment;
use App\Models\Ticket;
use App\Models\Attachment;
use App\Services\WebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CommentController extends Controller
{
    protected $webhookService;

    public function __construct(WebhookService $webhookService)
    {
        $this->webhookService = $webhookService;
    }
    /**
     * Get all comments with filtering and pagination
     */
    public function index(Request $request): JsonResponse
    {
        $this->validate($request, [
            'ticket_id' => 'string|uuid',
            'user_id' => 'string|uuid',
            'client_id' => 'string|uuid',
            'internal_only' => 'boolean',
            'public_only' => 'boolean',
            'ai_generated' => 'boolean',
            'search' => 'string|max:255',
            'page' => 'integer|min:1',
            'limit' => 'integer|min:1|max:100',
            'sort' => 'string|in:created_at,updated_at',
            'direction' => 'string|in:asc,desc',
            'include' => 'string' // ticket,user,client
        ]);

        $query = TicketComment::query();

        // Apply filters
        if ($request->has('ticket_id')) {
            $query->where('ticket_id', $request->ticket_id);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->has('internal_only') && $request->internal_only) {
            $query->internal();
        }

        if ($request->has('public_only') && $request->public_only) {
            $query->public();
        }

        if ($request->has('ai_generated') && $request->ai_generated) {
            $query->where('is_ai_generated', true);
        }

        // Search functionality
        if ($request->has('search')) {
            $searchTerm = $request->search;
            $query->where('content', 'ILIKE', "%{$searchTerm}%");
        }

        // Include related data
        $includes = [];
        if ($request->has('include')) {
            $includes = explode(',', $request->include);
        }

        if (in_array('ticket', $includes)) {
            $query->with('ticket');
        }

        // ALWAYS load attachments relationship for comments
        $query->with('commentAttachments');

        // Sorting
        $sort = $request->get('sort', 'created_at');
        $direction = $request->get('direction', 'desc');
        $query->orderBy($sort, $direction);

        // Pagination
        $page = (int) $request->get('page', 1);
        $limit = (int) $request->get('limit', 20);
        $offset = ($page - 1) * $limit;

        $total = $query->count();
        $comments = $query->offset($offset)->limit($limit)->get();

        // Add computed fields
        $comments->each(function ($comment) {
            $comment->author_type = $comment->user_id ? 'agent' : 'customer';

            // Add mock user/client data for display (in real implementation, this would be proper relationships)
            if ($comment->user_id) {
                $comment->user = (object) [
                    'id' => $comment->user_id,
                    'name' => 'Agent ' . substr($comment->user_id, -8),
                    'email' => 'agent@aidly.com'
                ];
            }

            if ($comment->client_id) {
                $comment->client = (object) [
                    'id' => $comment->client_id,
                    'name' => 'Client ' . substr($comment->client_id, -8),
                    'email' => 'client@example.com'
                ];
            }
        });

        return response()->json([
            'success' => true,
            'data' => $comments,
            'meta' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit),
                'from' => $offset + 1,
                'to' => min($offset + $limit, $total),
                'limit' => $limit
            ]
        ]);
    }

    /**
     * Get a specific comment
     */
    public function show(string $id): JsonResponse
    {
        $comment = TicketComment::with(['ticket', 'commentAttachments'])->find($id);

        if (!$comment) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Comment not found'
                ]
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $comment
        ]);
    }

    /**
     * Create a new comment
     */
    public function store(Request $request): JsonResponse
    {
        $this->validate($request, [
            'ticket_id' => 'required|string|uuid',
            'content' => 'required|string|max:5000',
            'body_html' => 'nullable|string', // HTML version with embedded inline images
            'body_plain' => 'nullable|string', // Plain text version
            'is_internal_note' => 'boolean',
            'is_ai_generated' => 'boolean',
            'ai_suggestion_used' => 'nullable|string',
            'visible_to_agents' => 'nullable|array',
            'visible_to_agents.*' => 'string',
            'attachments.*' => 'nullable|file|max:10240', // Max 10MB per file
            // Email metadata for Gmail-style rendering
            'from_address' => 'nullable|string',
            'to_addresses' => 'nullable|array',
            'cc_addresses' => 'nullable|array',
            'subject' => 'nullable|string',
            'headers' => 'nullable|array',
            'metadata' => 'nullable|array',
        ]);

        // Verify ticket exists
        $ticket = Ticket::find($request->ticket_id);
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

        try {
            DB::beginTransaction();

            $comment = TicketComment::create([
                'ticket_id' => $request->ticket_id,
                'user_id' => $request->user()->id ?? null, // Will be null for client comments
                'client_id' => $request->get('client_id'), // For client comments
                'content' => $request->content,
                'body_html' => $request->get('body_html'), // HTML version with embedded inline images
                'body_plain' => $request->get('body_plain'), // Plain text version
                'is_internal_note' => $request->get('is_internal_note', false),
                'is_ai_generated' => $request->get('is_ai_generated', false),
                'ai_suggestion_used' => $request->get('ai_suggestion_used'),
                'visible_to_agents' => $request->get('visible_to_agents'),
                // Email metadata for Gmail-style rendering
                'from_address' => $request->get('from_address'),
                'to_addresses' => $request->get('to_addresses'),
                'cc_addresses' => $request->get('cc_addresses'),
                'subject' => $request->get('subject'),
                'headers' => $request->get('headers'),
            ]);

            // Handle file attachments if provided
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    // Generate unique file path
                    $fileName = $file->getClientOriginalName();
                    $sanitizedFileName = \Str::slug(pathinfo($fileName, PATHINFO_FILENAME));
                    $extension = $file->getClientOriginalExtension();
                    $uniqueFileName = $sanitizedFileName . '_' . time() . '_' . \Str::random(8) . '.' . $extension;
                    $storagePath = "tickets/{$request->ticket_id}/attachments/{$uniqueFileName}";

                    // Store file
                    Storage::putFileAs(
                        "tickets/{$request->ticket_id}/attachments",
                        $file,
                        $uniqueFileName
                    );

                    // Create attachment record
                    Attachment::create([
                        'ticket_id' => $request->ticket_id,
                        'comment_id' => $comment->id,
                        'uploaded_by_user_id' => $request->user()->id ?? null,
                        'uploaded_by_client_id' => $request->get('client_id'),
                        'file_name' => $fileName,
                        'file_type' => $extension,
                        'file_size' => $file->getSize(),
                        'storage_path' => $storagePath,
                        'mime_type' => $file->getMimeType(),
                        'is_inline' => false,
                    ]);
                }
            }

            // Load the comment with ticket and attachments relationships
            $comment->load(['ticket', 'commentAttachments']);

            DB::commit();

            // Send webhook notification for email integration (async in background)
            if (!$comment->is_internal_note) {
                $this->webhookService->notifyCommentCreated($comment, $ticket);
            }

            return response()->json([
                'success' => true,
                'data' => $comment,
                'message' => 'Comment created successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Failed to create comment',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Update a comment
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $this->validate($request, [
            'content' => 'required|string|max:5000',
            'is_internal_note' => 'boolean',
            'attachments' => 'nullable|array'
        ]);

        $comment = TicketComment::find($id);

        if (!$comment) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Comment not found'
                ]
            ], 404);
        }

        // Only allow users to edit their own comments (add proper authorization later)
        // if ($comment->user_id !== $request->user()->id) {
        //     return response()->json([
        //         'success' => false,
        //         'error' => ['message' => 'Unauthorized']
        //     ], 403);
        // }

        try {
            $comment->update([
                'content' => $request->content,
                'is_internal_note' => $request->get('is_internal_note', $comment->is_internal_note),
                'attachments' => $request->get('attachments', $comment->attachments)
            ]);

            return response()->json([
                'success' => true,
                'data' => $comment,
                'message' => 'Comment updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Failed to update comment',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Delete a comment
     */
    public function destroy(string $id): JsonResponse
    {
        $comment = TicketComment::find($id);

        if (!$comment) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Comment not found'
                ]
            ], 404);
        }

        try {
            $comment->delete();

            return response()->json([
                'success' => true,
                'message' => 'Comment deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Failed to delete comment',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Mark a comment as read
     */
    public function markRead(Request $request, string $id): JsonResponse
    {
        $comment = TicketComment::find($id);

        if (!$comment) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Comment not found'
                ]
            ], 404);
        }

        try {
            // Get user from request
            $userId = null;
            if ($request->attributes->has('auth_user')) {
                $authUser = $request->attributes->get('auth_user');
                $userId = $authUser['id'] ?? null;
            }

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'message' => 'User not authenticated'
                    ]
                ], 401);
            }

            // Don't mark user's own comments as read (they created it)
            if ($comment->user_id === $userId) {
                return response()->json([
                    'success' => true,
                    'message' => 'Cannot mark your own comment as read'
                ]);
            }

            // Check if already marked as read
            $alreadyRead = DB::table('comment_reads')
                ->where('comment_id', $id)
                ->where('user_id', $userId)
                ->exists();

            if ($alreadyRead) {
                return response()->json([
                    'success' => true,
                    'message' => 'Comment already marked as read'
                ]);
            }

            // Insert read record
            DB::table('comment_reads')->insert([
                'comment_id' => $id,
                'user_id' => $userId,
                'read_at' => DB::raw('NOW()')
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Comment marked as read'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Failed to mark comment as read',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }
}