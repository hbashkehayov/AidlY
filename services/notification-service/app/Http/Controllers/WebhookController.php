<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class WebhookController extends Controller
{
    /**
     * Handle ticket created webhook
     */
    public function ticketCreated(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'ticket_id' => 'required|uuid',
                'ticket_number' => 'required|string',
                'subject' => 'required|string',
                'status' => 'required|string',
                'priority' => 'required|string',
                'customer_id' => 'sometimes|uuid',
                'customer_name' => 'required|string',
                'customer_email' => 'required|email'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $ticketData = $request->all();

            // Generate customer_id if not provided (for webhook compatibility)
            if (!isset($ticketData['customer_id'])) {
                $ticketData['customer_id'] = \Illuminate\Support\Str::uuid();
            }

            // Send notification to customer
            $this->queueNotification([
                'user_id' => $ticketData['customer_id'],
                'recipient_email' => $ticketData['customer_email'],
                'type' => 'email',
                'event' => 'ticket_created',
                'template' => 'ticket_created',
                'subject' => "New Ticket Created: {$ticketData['subject']}",
                'data' => [
                    'customer_name' => $ticketData['customer_name'],
                    'ticket_number' => $ticketData['ticket_number'],
                    'ticket_subject' => $ticketData['subject'],
                    'ticket_status' => $ticketData['status']
                ]
            ]);

            // Notify agents/supervisors if ticket is high priority
            if ($ticketData['priority'] === 'high' || $ticketData['priority'] === 'urgent') {
                $this->notifyAgentsOfHighPriorityTicket($ticketData);
            }

            return response()->json([
                'success' => true,
                'message' => 'Ticket creation notifications queued successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle ticket updated webhook
     */
    public function ticketUpdated(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'ticket_id' => 'required|uuid',
                'ticket_number' => 'required|string',
                'subject' => 'required|string',
                'old_status' => 'required|string',
                'new_status' => 'required|string',
                'customer_id' => 'required|uuid',
                'customer_name' => 'required|string',
                'customer_email' => 'required|email',
                'updated_by' => 'sometimes|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $ticketData = $request->all();

            // Notify customer of status change
            if ($ticketData['old_status'] !== $ticketData['new_status']) {
                $this->queueNotification([
                    'user_id' => $ticketData['customer_id'],
                    'recipient_email' => $ticketData['customer_email'],
                    'type' => 'email',
                    'event' => 'ticket_updated',
                    'template' => 'ticket_updated',
                    'subject' => "Ticket Updated: {$ticketData['subject']}",
                    'data' => [
                        'customer_name' => $ticketData['customer_name'],
                        'ticket_number' => $ticketData['ticket_number'],
                        'ticket_subject' => $ticketData['subject'],
                        'old_status' => $ticketData['old_status'],
                        'new_status' => $ticketData['new_status'],
                        'updated_by' => $ticketData['updated_by'] ?? 'System'
                    ]
                ]);

                // Special handling for resolved tickets
                if ($ticketData['new_status'] === 'resolved') {
                    $this->handleTicketResolved($ticketData);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Ticket update notifications queued successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle ticket assigned webhook
     */
    public function ticketAssigned(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'ticket_id' => 'required|uuid',
                'ticket_number' => 'required|string',
                'subject' => 'required|string',
                'priority' => 'required|string',
                'customer_name' => 'required|string',
                'assigned_to_id' => 'required|uuid',
                'assigned_to_name' => 'required|string',
                'assigned_to_email' => 'required|email',
                'assigned_by' => 'sometimes|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $ticketData = $request->all();

            // Notify the assigned agent
            $this->queueNotification([
                'user_id' => $ticketData['assigned_to_id'],
                'recipient_email' => $ticketData['assigned_to_email'],
                'type' => 'email',
                'event' => 'ticket_assigned',
                'template' => 'ticket_assigned',
                'subject' => "Ticket Assigned: {$ticketData['subject']}",
                'data' => [
                    'agent_name' => $ticketData['assigned_to_name'],
                    'ticket_number' => $ticketData['ticket_number'],
                    'ticket_subject' => $ticketData['subject'],
                    'customer_name' => $ticketData['customer_name'],
                    'ticket_priority' => $ticketData['priority'],
                    'assigned_by' => $ticketData['assigned_by'] ?? 'System'
                ]
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ticket assignment notifications queued successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle comment added webhook
     */
    public function commentAdded(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'ticket_id' => 'required|uuid',
                'ticket_number' => 'required|string',
                'ticket_subject' => 'required|string',
                'comment_id' => 'required|uuid',
                'comment_text' => 'required|string',
                'author_name' => 'required|string',
                'author_type' => 'required|in:agent,customer',
                'customer_id' => 'required|uuid',
                'customer_email' => 'required|email',
                'assigned_agent_id' => 'sometimes|uuid',
                'assigned_agent_email' => 'sometimes|email'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $commentData = $request->all();

            // If comment is from agent, notify customer
            if ($commentData['author_type'] === 'agent') {
                $this->queueNotification([
                    'user_id' => $commentData['customer_id'],
                    'recipient_email' => $commentData['customer_email'],
                    'type' => 'email',
                    'event' => 'comment_added',
                    'template' => 'comment_added',
                    'subject' => "New Reply: {$commentData['ticket_subject']}",
                    'data' => [
                        'customer_name' => $commentData['customer_name'] ?? 'Valued Customer',
                        'ticket_number' => $commentData['ticket_number'],
                        'ticket_subject' => $commentData['ticket_subject'],
                        'comment_text' => $commentData['comment_text'],
                        'author_name' => $commentData['author_name']
                    ]
                ]);
            }
            // If comment is from customer, notify assigned agent
            elseif ($commentData['author_type'] === 'customer' &&
                   !empty($commentData['assigned_agent_id'])) {
                $this->queueNotification([
                    'user_id' => $commentData['assigned_agent_id'],
                    'recipient_email' => $commentData['assigned_agent_email'],
                    'type' => 'email',
                    'event' => 'comment_added',
                    'template' => 'comment_added_agent',
                    'subject' => "Customer Reply: {$commentData['ticket_subject']}",
                    'data' => [
                        'agent_name' => 'Agent',
                        'ticket_number' => $commentData['ticket_number'],
                        'ticket_subject' => $commentData['ticket_subject'],
                        'comment_text' => $commentData['comment_text'],
                        'customer_name' => $commentData['author_name']
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Comment notifications queued successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle SLA breach webhook
     */
    public function slaBreach(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'ticket_id' => 'required|uuid',
                'ticket_number' => 'required|string',
                'ticket_subject' => 'required|string',
                'customer_name' => 'required|string',
                'sla_type' => 'required|in:first_response,next_response,resolution',
                'breach_time' => 'required|string',
                'assigned_agent_id' => 'sometimes|uuid',
                'assigned_agent_email' => 'sometimes|email'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $slaData = $request->all();

            // Notify assigned agent if any
            if (!empty($slaData['assigned_agent_id'])) {
                $this->queueNotification([
                    'user_id' => $slaData['assigned_agent_id'],
                    'recipient_email' => $slaData['assigned_agent_email'],
                    'type' => 'email',
                    'event' => 'sla_breach',
                    'template' => 'sla_breach',
                    'subject' => "SLA Breach Alert: {$slaData['ticket_subject']}",
                    'priority' => 'urgent',
                    'data' => [
                        'ticket_number' => $slaData['ticket_number'],
                        'ticket_subject' => $slaData['ticket_subject'],
                        'customer_name' => $slaData['customer_name'],
                        'sla_type' => $slaData['sla_type'],
                        'breach_time' => $slaData['breach_time']
                    ]
                ]);
            }

            // Notify all supervisors and admins
            $this->notifySupervisorsOfBreach($slaData);

            return response()->json([
                'success' => true,
                'message' => 'SLA breach notifications queued successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Queue a notification
     */
    private function queueNotification($data)
    {
        $template = $this->getTemplate($data['template'] ?? $data['event']);

        $notificationData = [
            'id' => \Illuminate\Support\Str::uuid(),
            'notifiable_type' => 'user',
            'notifiable_id' => $data['user_id'],
            'type' => $data['event'],
            'channel' => $data['type'],
            'title' => $this->processTemplate($data['subject'], $data['data'] ?? []),
            'message' => $this->processTemplate($template['message_template'] ?? $data['subject'], $data['data'] ?? []),
            'data' => json_encode($data['data'] ?? []),
            'priority' => $this->mapPriorityToInt($data['priority'] ?? 'normal'),
            'status' => 'pending',
            'scheduled_at' => \Carbon\Carbon::now(),
            'created_at' => \Carbon\Carbon::now()
        ];

        DB::table('notification_queue')->insert($notificationData);
    }

    /**
     * Get notification template
     */
    private function getTemplate($templateName)
    {
        $template = DB::table('notification_templates')
            ->where('name', $templateName)
            ->where('is_active', true)
            ->first();

        return $template ? (array) $template : ['message_template' => '{{subject}}', 'title_template' => 'Notification'];
    }

    /**
     * Process template with variables
     */
    private function processTemplate($template, $data)
    {
        foreach ($data as $key => $value) {
            $template = str_replace("{{" . $key . "}}", $value, $template);
        }
        return $template;
    }

    /**
     * Notify agents of high priority ticket
     */
    private function notifyAgentsOfHighPriorityTicket($ticketData)
    {
        // Get available agents (this would typically come from user service)
        // For now, simulate with a simple notification
        DB::table('notification_queue')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'notifiable_type' => 'broadcast',
            'notifiable_id' => \Illuminate\Support\Str::uuid(), // Generate temp ID for broadcast
            'type' => 'high_priority_ticket',
            'channel' => 'in_app',
            'title' => "High Priority Ticket Created: {$ticketData['subject']}",
            'message' => "A {$ticketData['priority']} priority ticket has been created and requires immediate attention.\n\nTicket: {$ticketData['ticket_number']}\nCustomer: {$ticketData['customer_name']}",
            'data' => json_encode($ticketData),
            'priority' => 10, // high
            'status' => 'pending',
            'scheduled_at' => \Carbon\Carbon::now(),
            'created_at' => \Carbon\Carbon::now()
        ]);
    }

    /**
     * Handle ticket resolved
     */
    private function handleTicketResolved($ticketData)
    {
        // Send specific resolved notification template
        $template = $this->getTemplate('ticket_resolved');

        DB::table('notification_queue')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'notifiable_type' => 'user',
            'notifiable_id' => $ticketData['customer_id'],
            'type' => 'ticket_resolved',
            'channel' => 'email',
            'title' => "Ticket Resolved: {$ticketData['subject']}",
            'message' => $this->processTemplate($template['message_template'] ?? 'Your ticket has been resolved.', $ticketData),
            'data' => json_encode($ticketData),
            'priority' => 5, // medium
            'status' => 'pending',
            'scheduled_at' => \Carbon\Carbon::now(),
            'created_at' => \Carbon\Carbon::now()
        ]);
    }

    /**
     * Notify supervisors of SLA breach
     */
    private function notifySupervisorsOfBreach($slaData)
    {
        // Broadcast to supervisors and admins
        DB::table('notification_queue')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'notifiable_type' => 'broadcast',
            'notifiable_id' => \Illuminate\Support\Str::uuid(),
            'type' => 'sla_breach_supervisor',
            'channel' => 'in_app',
            'title' => "URGENT: SLA Breach - {$slaData['ticket_subject']}",
            'message' => "SLA BREACH ALERT\n\nTicket: {$slaData['ticket_number']}\nCustomer: {$slaData['customer_name']}\nBreach Type: {$slaData['sla_type']}\nTime: {$slaData['breach_time']}\n\nImmediate action required.",
            'data' => json_encode($slaData),
            'priority' => 20, // urgent
            'status' => 'pending',
            'scheduled_at' => \Carbon\Carbon::now(),
            'created_at' => \Carbon\Carbon::now()
        ]);
    }

    /**
     * Map priority string to integer
     */
    private function mapPriorityToInt($priority): int
    {
        return match($priority) {
            'low' => 1,
            'normal' => 5,
            'high' => 10,
            'urgent' => 20,
            default => 5
        };
    }
}