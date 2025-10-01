<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    private $notificationServiceUrl;

    public function __construct()
    {
        $this->notificationServiceUrl = env('NOTIFICATION_SERVICE_URL', 'http://notification-service:8004/api/v1');
    }

    /**
     * Notify when a ticket is assigned to an agent
     */
    public function notifyTicketAssigned($ticket, $agent, $assignedBy = null)
    {
        try {
            $payload = [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'subject' => $ticket->subject,
                'priority' => $ticket->priority,
                'customer_name' => $ticket->client->name ?? 'Unknown Customer',
                'assigned_to_id' => $agent->id,
                'assigned_to_name' => $agent->name,
                'assigned_to_email' => $agent->email,
                'assigned_by' => $assignedBy
            ];

            return $this->sendWebhook('/webhooks/ticket-assigned', $payload);
        } catch (\Exception $e) {
            Log::error('Failed to send ticket assigned notification', [
                'error' => $e->getMessage(),
                'ticket_id' => $ticket->id
            ]);
            return false;
        }
    }

    /**
     * Notify when a comment is added to a ticket
     */
    public function notifyCommentAdded($ticket, $comment, $author)
    {
        try {
            // Determine author type and get customer info
            $authorType = $comment->user_id ? 'agent' : 'customer';
            $customerEmail = $ticket->client->email ?? 'unknown@example.com';
            $customerName = $ticket->client->name ?? 'Unknown Customer';

            $payload = [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'ticket_subject' => $ticket->subject,
                'comment_id' => $comment->id,
                'comment_text' => $comment->content,
                'author_name' => $author->name ?? $customerName,
                'author_type' => $authorType,
                'customer_id' => $ticket->client_id,
                'customer_name' => $customerName,
                'customer_email' => $customerEmail,
            ];

            // Include assigned agent info if ticket is assigned
            if ($ticket->assigned_agent_id) {
                $payload['assigned_agent_id'] = $ticket->assigned_agent_id;

                // Get agent email from database directly
                $agent = \Illuminate\Support\Facades\DB::table('users')
                    ->where('id', $ticket->assigned_agent_id)
                    ->first();
                $payload['assigned_agent_email'] = $agent->email ?? null;
            }

            return $this->sendWebhook('/webhooks/comment-added', $payload);
        } catch (\Exception $e) {
            Log::error('Failed to send comment added notification', [
                'error' => $e->getMessage(),
                'ticket_id' => $ticket->id,
                'comment_id' => $comment->id
            ]);
            return false;
        }
    }

    /**
     * Notify when a ticket is created
     */
    public function notifyTicketCreated($ticket)
    {
        try {
            $payload = [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'subject' => $ticket->subject,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'customer_id' => $ticket->client_id,
                'customer_name' => $ticket->client->name ?? 'Unknown Customer',
                'customer_email' => $ticket->client->email ?? 'unknown@example.com'
            ];

            return $this->sendWebhook('/webhooks/ticket-created', $payload);
        } catch (\Exception $e) {
            Log::error('Failed to send ticket created notification', [
                'error' => $e->getMessage(),
                'ticket_id' => $ticket->id
            ]);
            return false;
        }
    }

    /**
     * Notify when a ticket status is updated
     */
    public function notifyTicketUpdated($ticket, $oldStatus, $updatedBy = null)
    {
        try {
            $payload = [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'subject' => $ticket->subject,
                'old_status' => $oldStatus,
                'new_status' => $ticket->status,
                'customer_id' => $ticket->client_id,
                'customer_name' => $ticket->client->name ?? 'Unknown Customer',
                'customer_email' => $ticket->client->email ?? 'unknown@example.com',
                'updated_by' => $updatedBy
            ];

            return $this->sendWebhook('/webhooks/ticket-updated', $payload);
        } catch (\Exception $e) {
            Log::error('Failed to send ticket updated notification', [
                'error' => $e->getMessage(),
                'ticket_id' => $ticket->id
            ]);
            return false;
        }
    }

    /**
     * Send webhook request to notification service
     */
    private function sendWebhook($endpoint, $payload)
    {
        try {
            $response = Http::timeout(5)
                ->post($this->notificationServiceUrl . $endpoint, $payload);

            if ($response->successful()) {
                Log::info('Notification webhook sent successfully', [
                    'endpoint' => $endpoint,
                    'payload' => $payload
                ]);
                return true;
            }

            Log::warning('Notification webhook failed', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Notification webhook exception', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
