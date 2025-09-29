<?php

namespace App\Http\Controllers;

use App\Models\TicketComment;
use App\Models\Ticket;
use App\Services\WebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

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
        $comment = TicketComment::with('ticket')->find($id);

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
            'is_internal_note' => 'boolean',
            'is_ai_generated' => 'boolean',
            'ai_suggestion_used' => 'nullable|string',
            'attachments' => 'nullable|array'
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

        try {
            DB::beginTransaction();

            $comment = TicketComment::create([
                'ticket_id' => $request->ticket_id,
                'user_id' => $request->user()->id ?? null, // Will be null for client comments
                'client_id' => $request->get('client_id'), // For client comments
                'content' => $request->content,
                'is_internal_note' => $request->get('is_internal_note', false),
                'is_ai_generated' => $request->get('is_ai_generated', false),
                'ai_suggestion_used' => $request->get('ai_suggestion_used'),
                'attachments' => $request->get('attachments', [])
            ]);

            // Load the comment with ticket relationship
            $comment->load('ticket');

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

        // Don't mark as read if already read
        if ($comment->is_read) {
            return response()->json([
                'success' => true,
                'message' => 'Comment already marked as read'
            ]);
        }

        try {
            // Get user from request if available, otherwise use a default
            $userId = null;
            if ($request->attributes->has('auth_user')) {
                $authUser = $request->attributes->get('auth_user');
                $userId = $authUser['id'] ?? null;
            }

            $comment->update([
                'is_read' => true,
                'read_at' => \Carbon\Carbon::now(),
                'read_by' => $userId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Comment marked as read',
                'data' => $comment
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