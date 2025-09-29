<?php

namespace App\Http\Controllers;

use App\Models\EmailTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EmailTemplateController extends Controller
{
    /**
     * Get all email templates
     */
    public function index(Request $request)
    {
        $query = EmailTemplate::query();

        // Filter by status
        if ($request->has('status') && $request->status === 'active') {
            $query->active();
        }

        // Filter by category
        if ($request->has('category')) {
            $query->byCategory($request->category);
        }

        // Search by name
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'ILIKE', "%{$search}%");
        }

        $templates = $query->orderBy('category')
            ->orderBy('name')
            ->paginate($request->get('limit', 10));

        return response()->json([
            'success' => true,
            'data' => $templates->items(),
            'meta' => [
                'total' => $templates->total(),
                'page' => $templates->currentPage(),
                'pages' => $templates->lastPage(),
                'limit' => $templates->perPage(),
            ]
        ]);
    }

    /**
     * Get single email template
     */
    public function show(string $id)
    {
        $template = EmailTemplate::find($id);

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Email template not found'
            ], 404);
        }

        // Add extracted variables to response
        $template->extracted_variables = $template->extractVariables();

        return response()->json([
            'success' => true,
            'data' => $template
        ]);
    }

    /**
     * Create new email template
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:500',
            'body_html' => 'required|string',
            'body_plain' => 'sometimes|string',
            'category' => 'required|in:welcome,ticket_created,ticket_updated,ticket_resolved,ticket_closed,auto_reply,escalation,reminder,custom',
            'variables' => 'sometimes|array',
            'is_active' => 'boolean',
            'created_by' => 'required|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $template = new EmailTemplate($request->all());

            // Generate plain text from HTML if not provided
            if (empty($template->body_plain)) {
                $template->body_plain = strip_tags($template->body_html);
            }

            $template->save();

            return response()->json([
                'success' => true,
                'message' => 'Email template created successfully',
                'data' => $template
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create email template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update email template
     */
    public function update(Request $request, string $id)
    {
        $template = EmailTemplate::find($id);

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Email template not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'subject' => 'sometimes|string|max:500',
            'body_html' => 'sometimes|string',
            'body_plain' => 'sometimes|string',
            'category' => 'sometimes|in:welcome,ticket_created,ticket_updated,ticket_resolved,ticket_closed,auto_reply,escalation,reminder,custom',
            'variables' => 'sometimes|array',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $template->fill($request->all());
            $template->save();

            return response()->json([
                'success' => true,
                'message' => 'Email template updated successfully',
                'data' => $template
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update email template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete email template
     */
    public function destroy(string $id)
    {
        $template = EmailTemplate::find($id);

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Email template not found'
            ], 404);
        }

        try {
            $template->delete();

            return response()->json([
                'success' => true,
                'message' => 'Email template deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete email template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Preview template with variables
     */
    public function preview(Request $request, string $id)
    {
        $template = EmailTemplate::find($id);

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Email template not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'variables' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Validate required variables
            $missing = $template->validateVariables($request->variables);
            if (!empty($missing)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing template variables',
                    'missing_variables' => $missing
                ], 422);
            }

            // Render template
            $rendered = $template->render($request->variables);

            return response()->json([
                'success' => true,
                'data' => $rendered
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to preview template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get template variables
     */
    public function variables(string $id)
    {
        $template = EmailTemplate::find($id);

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Email template not found'
            ], 404);
        }

        $variables = $template->extractVariables();

        return response()->json([
            'success' => true,
            'data' => [
                'variables' => $variables,
                'saved_variables' => $template->variables ?: [],
            ]
        ]);
    }

    /**
     * Get template categories
     */
    public function categories()
    {
        $categories = [
            EmailTemplate::CATEGORY_WELCOME => 'Welcome',
            EmailTemplate::CATEGORY_TICKET_CREATED => 'Ticket Created',
            EmailTemplate::CATEGORY_TICKET_UPDATED => 'Ticket Updated',
            EmailTemplate::CATEGORY_TICKET_RESOLVED => 'Ticket Resolved',
            EmailTemplate::CATEGORY_TICKET_CLOSED => 'Ticket Closed',
            EmailTemplate::CATEGORY_AUTO_REPLY => 'Auto Reply',
            EmailTemplate::CATEGORY_ESCALATION => 'Escalation',
            EmailTemplate::CATEGORY_REMINDER => 'Reminder',
            EmailTemplate::CATEGORY_CUSTOM => 'Custom',
        ];

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * Create default templates
     */
    public function createDefaults(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'created_by' => 'required|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $defaultTemplates = EmailTemplate::getDefaultTemplates();
            $created = [];

            foreach ($defaultTemplates as $templateData) {
                // Check if template already exists
                $existing = EmailTemplate::where('category', $templateData['category'])
                    ->where('name', $templateData['name'])
                    ->first();

                if ($existing) {
                    continue; // Skip if already exists
                }

                $templateData['created_by'] = $request->created_by;
                $template = new EmailTemplate($templateData);
                $template->save();

                $created[] = $template;
            }

            return response()->json([
                'success' => true,
                'message' => count($created) > 0
                    ? 'Default templates created successfully'
                    : 'Default templates already exist',
                'data' => [
                    'created_count' => count($created),
                    'templates' => $created
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create default templates',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}