<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    protected $emailServiceUrl;
    protected $timeout = 10;

    public function __construct()
    {
        $this->emailServiceUrl = env('EMAIL_SERVICE_URL', 'http://localhost:8005');
    }

    /**
     * Send webhook when comment is created
     */
    public function notifyCommentCreated($comment, $ticket, $clientData = null, $userData = null): void
    {
        try {
            // Get client data if not provided
            if (!$clientData && $ticket->client_id) {
                $clientData = $this->getClientData($ticket->client_id);
            }

            // Get user data if not provided
            if (!$userData && $comment->user_id) {
                $userData = $this->getUserData($comment->user_id);
            }

            $payload = [
                'event_type' => 'comment.created',
                'ticket_id' => $ticket->id,
                'comment_id' => $comment->id,
                'comment_data' => [
                    'id' => $comment->id,
                    'content' => $comment->content,
                    'user_id' => $comment->user_id,
                    'client_id' => $comment->client_id,
                    'is_internal_note' => $comment->is_internal_note,
                    'is_ai_generated' => $comment->is_ai_generated,
                    'created_at' => $comment->created_at,
                ],
                'ticket_data' => [
                    'id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'subject' => $ticket->subject,
                    'status' => $ticket->status,
                    'priority' => $ticket->priority,
                    'client_id' => $ticket->client_id,
                    'assigned_agent_id' => $ticket->assigned_agent_id,
                    'metadata' => $ticket->custom_fields ?? [],
                ],
                'client_data' => $clientData,
                'user_data' => $userData,
            ];

            // Send webhook to email service
            $response = Http::timeout($this->timeout)
                ->post("{$this->emailServiceUrl}/api/v1/webhooks/ticket/comment", $payload);

            if ($response->successful()) {
                Log::info('Comment webhook sent successfully', [
                    'ticket_id' => $ticket->id,
                    'comment_id' => $comment->id,
                    'response_status' => $response->status(),
                ]);
            } else {
                Log::warning('Comment webhook failed', [
                    'ticket_id' => $ticket->id,
                    'comment_id' => $comment->id,
                    'response_status' => $response->status(),
                    'response_body' => $response->body(),
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Comment webhook exception', [
                'ticket_id' => $ticket->id,
                'comment_id' => $comment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Send webhook when ticket status changes
     */
    public function notifyStatusChanged($ticket, $previousStatus, $clientData = null): void
    {
        try {
            // Get client data if not provided
            if (!$clientData && $ticket->client_id) {
                $clientData = $this->getClientData($ticket->client_id);
            }

            $payload = [
                'event_type' => 'ticket.status_changed',
                'ticket_id' => $ticket->id,
                'ticket_data' => [
                    'id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'subject' => $ticket->subject,
                    'status' => $ticket->status,
                    'previous_status' => $previousStatus,
                    'priority' => $ticket->priority,
                    'client_id' => $ticket->client_id,
                    'assigned_agent_id' => $ticket->assigned_agent_id,
                    'metadata' => $ticket->custom_fields ?? [],
                ],
                'client_data' => $clientData,
            ];

            // Send webhook to email service
            $response = Http::timeout($this->timeout)
                ->post("{$this->emailServiceUrl}/api/v1/webhooks/ticket/status-change", $payload);

            if ($response->successful()) {
                Log::info('Status change webhook sent successfully', [
                    'ticket_id' => $ticket->id,
                    'status' => $ticket->status,
                    'previous_status' => $previousStatus,
                ]);
            } else {
                Log::warning('Status change webhook failed', [
                    'ticket_id' => $ticket->id,
                    'response_status' => $response->status(),
                    'response_body' => $response->body(),
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Status change webhook exception', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get client data from client service
     */
    protected function getClientData($clientId): ?array
    {
        try {
            $clientServiceUrl = env('CLIENT_SERVICE_URL', 'http://localhost:8003');
            $response = Http::timeout($this->timeout)
                ->get("{$clientServiceUrl}/api/v1/clients/{$clientId}");

            if ($response->successful() && $response->json('success')) {
                $client = $response->json('data');
                return [
                    'id' => $client['id'],
                    'email' => $client['email'],
                    'name' => $client['name'] ?? 'Customer',
                    'company' => $client['company'] ?? null,
                ];
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch client data', [
                'client_id' => $clientId,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Get user data from auth service
     */
    protected function getUserData($userId): ?array
    {
        try {
            $authServiceUrl = env('AUTH_SERVICE_URL', 'http://localhost:8001');
            $response = Http::timeout($this->timeout)
                ->get("{$authServiceUrl}/api/v1/users/{$userId}");

            if ($response->successful() && $response->json('success')) {
                $user = $response->json('data');
                return [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                ];
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch user data', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }
}