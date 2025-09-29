<?php

namespace App\Http\Controllers;

use App\Services\TicketReplyEmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class WebhookController extends Controller
{
    protected $ticketReplyEmailService;

    public function __construct(TicketReplyEmailService $ticketReplyEmailService)
    {
        $this->ticketReplyEmailService = $ticketReplyEmailService;
    }

    /**
     * Handle ticket comment webhook - automatically send email when agent replies to ticket
     */
    public function handleTicketComment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'event_type' => 'required|string|in:comment.created,comment.updated',
            'ticket_id' => 'required|uuid',
            'comment_id' => 'required|uuid',
            'comment_data' => 'required|array',
            'comment_data.content' => 'required|string',
            'comment_data.user_id' => 'required|uuid',
            'comment_data.is_internal_note' => 'required|boolean',
            'ticket_data' => 'required|array',
            'ticket_data.subject' => 'required|string',
            'ticket_data.client_id' => 'required|uuid',
            'client_data' => 'required|array',
            'client_data.email' => 'required|email',
            'client_data.name' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Only process non-internal comments (external replies to clients)
        if ($request->input('comment_data.is_internal_note')) {
            return response()->json([
                'success' => true,
                'message' => 'Internal note ignored - no email sent'
            ]);
        }

        // Only process new comments, not updates
        if ($request->input('event_type') !== 'comment.created') {
            return response()->json([
                'success' => true,
                'message' => 'Comment update ignored - no email sent'
            ]);
        }

        try {
            $result = $this->ticketReplyEmailService->sendReplyEmail(
                $request->input('ticket_data'),
                $request->input('comment_data'),
                $request->input('client_data')
            );

            return response()->json([
                'success' => true,
                'message' => 'Reply email sent successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send ticket reply email', [
                'ticket_id' => $request->input('ticket_id'),
                'comment_id' => $request->input('comment_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send reply email',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle ticket status change webhook - send status update emails
     */
    public function handleTicketStatusChange(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'event_type' => 'required|string|in:ticket.status_changed',
            'ticket_id' => 'required|uuid',
            'ticket_data' => 'required|array',
            'ticket_data.status' => 'required|string',
            'ticket_data.previous_status' => 'required|string',
            'ticket_data.subject' => 'required|string',
            'client_data' => 'required|array',
            'client_data.email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->ticketReplyEmailService->sendStatusChangeEmail(
                $request->input('ticket_data'),
                $request->input('client_data')
            );

            return response()->json([
                'success' => true,
                'message' => 'Status change email sent successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send ticket status change email', [
                'ticket_id' => $request->input('ticket_id'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send status change email',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Health check for webhooks
     */
    public function health()
    {
        return response()->json([
            'success' => true,
            'service' => 'email-service-webhooks',
            'status' => 'healthy',
            'timestamp' => now()->toISOString()
        ]);
    }
}