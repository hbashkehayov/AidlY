<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TemplateController extends Controller
{
    /**
     * Get all notification templates
     */
    public function index(Request $request)
    {
        try {
            $eventType = $request->get('event_type');
            $channel = $request->get('channel');
            $active = $request->get('active');

            $query = DB::table('notification_templates');

            if ($eventType) {
                $query->where('event_type', $eventType);
            }

            if ($channel) {
                $query->where('channel', $channel);
            }

            if ($active !== null) {
                $query->where('is_active', $active === 'true');
            }

            $templates = $query->orderBy('name')->get();

            return response()->json([
                'success' => true,
                'data' => $templates
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific template
     */
    public function show($id)
    {
        try {
            $template = DB::table('notification_templates')->where('id', $id)->first();

            if (!$template) {
                return response()->json([
                    'success' => false,
                    'error' => 'Template not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $template
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new template
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'event_type' => 'required|string|max:255',
                'channel' => 'required|in:email,in_app,push,sms,slack,webhook',
                'locale' => 'sometimes|string|max:10',
                'subject' => 'sometimes|string|max:500',
                'title_template' => 'required|string',
                'message_template' => 'required|string',
                'html_template' => 'sometimes|string',
                'variables' => 'sometimes|array',
                'is_active' => 'sometimes|boolean',
                'priority' => 'sometimes|integer'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $templateId = \Illuminate\Support\Str::uuid();

            DB::table('notification_templates')->insert([
                'id' => $templateId,
                'name' => $request->name,
                'event_type' => $request->event_type,
                'channel' => $request->channel,
                'locale' => $request->get('locale', 'en'),
                'subject' => $request->get('subject'),
                'title_template' => $request->title_template,
                'message_template' => $request->message_template,
                'html_template' => $request->get('html_template'),
                'variables' => json_encode($request->get('variables', [])),
                'is_active' => $request->get('is_active', true),
                'priority' => $request->get('priority', 0),
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now()
            ]);

            $template = DB::table('notification_templates')->where('id', $templateId)->first();

            return response()->json([
                'success' => true,
                'message' => 'Template created successfully',
                'data' => $template
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a template
     */
    public function update(Request $request, $id)
    {
        try {
            $template = DB::table('notification_templates')->where('id', $id)->first();

            if (!$template) {
                return response()->json([
                    'success' => false,
                    'error' => 'Template not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'event_type' => 'sometimes|string|max:255',
                'channel' => 'sometimes|in:email,in_app,push,sms,slack,webhook',
                'locale' => 'sometimes|string|max:10',
                'subject' => 'sometimes|string|max:500',
                'title_template' => 'sometimes|string',
                'message_template' => 'sometimes|string',
                'html_template' => 'sometimes|string',
                'variables' => 'sometimes|array',
                'is_active' => 'sometimes|boolean',
                'priority' => 'sometimes|integer'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $updateData = array_filter([
                'name' => $request->get('name'),
                'event_type' => $request->get('event_type'),
                'channel' => $request->get('channel'),
                'locale' => $request->get('locale'),
                'subject' => $request->get('subject'),
                'title_template' => $request->get('title_template'),
                'message_template' => $request->get('message_template'),
                'html_template' => $request->get('html_template'),
                'variables' => $request->has('variables') ? json_encode($request->variables) : null,
                'is_active' => $request->get('is_active'),
                'priority' => $request->get('priority'),
                'updated_at' => \Carbon\Carbon::now()
            ], function($value) { return $value !== null; });

            DB::table('notification_templates')->where('id', $id)->update($updateData);

            $updatedTemplate = DB::table('notification_templates')->where('id', $id)->first();

            return response()->json([
                'success' => true,
                'message' => 'Template updated successfully',
                'data' => $updatedTemplate
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a template
     */
    public function destroy($id)
    {
        try {
            $template = DB::table('notification_templates')->where('id', $id)->first();

            if (!$template) {
                return response()->json([
                    'success' => false,
                    'error' => 'Template not found'
                ], 404);
            }

            DB::table('notification_templates')->where('id', $id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Template deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Seed default templates
     */
    public function seedDefaults()
    {
        try {
            $defaultTemplates = [
                [
                    'id' => \Illuminate\Support\Str::uuid(),
                    'name' => 'ticket_created_email',
                    'event_type' => 'ticket_created',
                    'channel' => 'email',
                    'locale' => 'en',
                    'subject' => 'New Ticket Created: {{ticket_subject}}',
                    'title_template' => 'New Ticket Created',
                    'message_template' => 'Hello {{customer_name}},\n\nYour support ticket has been created successfully.\n\nTicket ID: {{ticket_number}}\nSubject: {{ticket_subject}}\nStatus: {{ticket_status}}\n\nWe will get back to you soon.\n\nBest regards,\nAidlY Support Team',
                    'html_template' => '<h2>New Ticket Created</h2><p>Hello <strong>{{customer_name}}</strong>,</p><p>Your support ticket has been created successfully.</p><ul><li><strong>Ticket ID:</strong> {{ticket_number}}</li><li><strong>Subject:</strong> {{ticket_subject}}</li><li><strong>Status:</strong> {{ticket_status}}</li></ul><p>We will get back to you soon.</p><p>Best regards,<br>AidlY Support Team</p>',
                    'variables' => json_encode(['customer_name', 'ticket_number', 'ticket_subject', 'ticket_status']),
                    'is_active' => true,
                    'is_system' => true,
                    'priority' => 0,
                    'created_at' => \Carbon\Carbon::now(),
                    'updated_at' => \Carbon\Carbon::now()
                ],
                [
                    'id' => \Illuminate\Support\Str::uuid(),
                    'name' => 'ticket_assigned_email',
                    'event_type' => 'ticket_assigned',
                    'channel' => 'email',
                    'locale' => 'en',
                    'subject' => 'Ticket Assigned: {{ticket_subject}}',
                    'title_template' => 'Ticket Assigned to You',
                    'message_template' => 'Hello {{agent_name}},\n\nA ticket has been assigned to you.\n\nTicket ID: {{ticket_number}}\nSubject: {{ticket_subject}}\nCustomer: {{customer_name}}\nPriority: {{ticket_priority}}\n\nPlease review and respond accordingly.\n\nBest regards,\nAidlY System',
                    'html_template' => '<h2>Ticket Assigned to You</h2><p>Hello <strong>{{agent_name}}</strong>,</p><p>A ticket has been assigned to you.</p><ul><li><strong>Ticket ID:</strong> {{ticket_number}}</li><li><strong>Subject:</strong> {{ticket_subject}}</li><li><strong>Customer:</strong> {{customer_name}}</li><li><strong>Priority:</strong> {{ticket_priority}}</li></ul><p>Please review and respond accordingly.</p><p>Best regards,<br>AidlY System</p>',
                    'variables' => json_encode(['agent_name', 'ticket_number', 'ticket_subject', 'customer_name', 'ticket_priority']),
                    'is_active' => true,
                    'is_system' => true,
                    'priority' => 0,
                    'created_at' => \Carbon\Carbon::now(),
                    'updated_at' => \Carbon\Carbon::now()
                ],
                [
                    'id' => \Illuminate\Support\Str::uuid(),
                    'name' => 'ticket_resolved_email',
                    'event_type' => 'ticket_resolved',
                    'channel' => 'email',
                    'locale' => 'en',
                    'subject' => 'Ticket Resolved: {{ticket_subject}}',
                    'title_template' => 'Your Ticket Has Been Resolved',
                    'message_template' => 'Hello {{customer_name}},\n\nYour support ticket has been resolved.\n\nTicket ID: {{ticket_number}}\nSubject: {{ticket_subject}}\nResolution: {{resolution_notes}}\n\nIf you need further assistance, please don\'t hesitate to contact us.\n\nBest regards,\nAidlY Support Team',
                    'html_template' => '<h2>Your Ticket Has Been Resolved</h2><p>Hello <strong>{{customer_name}}</strong>,</p><p>Your support ticket has been resolved.</p><ul><li><strong>Ticket ID:</strong> {{ticket_number}}</li><li><strong>Subject:</strong> {{ticket_subject}}</li><li><strong>Resolution:</strong> {{resolution_notes}}</li></ul><p>If you need further assistance, please don\'t hesitate to contact us.</p><p>Best regards,<br>AidlY Support Team</p>',
                    'variables' => json_encode(['customer_name', 'ticket_number', 'ticket_subject', 'resolution_notes']),
                    'is_active' => true,
                    'is_system' => true,
                    'priority' => 0,
                    'created_at' => \Carbon\Carbon::now(),
                    'updated_at' => \Carbon\Carbon::now()
                ],
                [
                    'id' => \Illuminate\Support\Str::uuid(),
                    'name' => 'sla_breach_email',
                    'event_type' => 'sla_breach',
                    'channel' => 'email',
                    'locale' => 'en',
                    'subject' => 'SLA Breach Alert: {{ticket_subject}}',
                    'title_template' => 'SLA Breach Alert',
                    'message_template' => 'ALERT: SLA Breach Detected\n\nTicket ID: {{ticket_number}}\nSubject: {{ticket_subject}}\nCustomer: {{customer_name}}\nSLA Type: {{sla_type}}\nBreach Time: {{breach_time}}\n\nImmediate action required.\n\nAidlY System',
                    'html_template' => '<h2 style="color: red;">SLA Breach Alert</h2><p><strong>ALERT: SLA Breach Detected</strong></p><ul><li><strong>Ticket ID:</strong> {{ticket_number}}</li><li><strong>Subject:</strong> {{ticket_subject}}</li><li><strong>Customer:</strong> {{customer_name}}</li><li><strong>SLA Type:</strong> {{sla_type}}</li><li><strong>Breach Time:</strong> {{breach_time}}</li></ul><p><strong>Immediate action required.</strong></p><p>AidlY System</p>',
                    'variables' => json_encode(['ticket_number', 'ticket_subject', 'customer_name', 'sla_type', 'breach_time']),
                    'is_active' => true,
                    'is_system' => true,
                    'priority' => 10,
                    'created_at' => \Carbon\Carbon::now(),
                    'updated_at' => \Carbon\Carbon::now()
                ]
            ];

            foreach ($defaultTemplates as $template) {
                DB::table('notification_templates')
                    ->updateOrInsert(
                        ['name' => $template['name'], 'event_type' => $template['event_type'], 'channel' => $template['channel']],
                        $template
                    );
            }

            return response()->json([
                'success' => true,
                'message' => 'Default templates seeded successfully',
                'count' => count($defaultTemplates)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}