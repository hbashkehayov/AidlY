<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AgentManagementController extends Controller
{
    /**
     * Create a new agent with automatic email integration
     * Admin-only endpoint
     */
    public function createAgent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'department_id' => 'nullable|uuid|exists:departments,id',
            'role' => 'required|string|in:agent,supervisor',

            // Email integration fields
            'enable_email_integration' => 'required|boolean',
            'gmail_address' => 'required_if:enable_email_integration,true|email',
            'gmail_app_password' => 'required_if:enable_email_integration,true|string',
            'agent_signature' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Create user account
            $user = new User();
            $user->id = (string) Str::uuid();
            $user->name = $request->name;
            $user->email = $request->email;
            $user->password_hash = Hash::make($request->password);
            $user->role = $request->role;
            $user->department_id = $request->department_id;
            $user->is_active = true;
            $user->save();

            $emailAccountId = null;

            // Create email integration if requested
            if ($request->enable_email_integration) {
                $emailAccountId = $this->createAgentEmailAccount($user, [
                    'gmail_address' => $request->gmail_address,
                    'gmail_app_password' => $request->gmail_app_password,
                    'agent_signature' => $request->agent_signature,
                ]);
            }

            Log::info('Agent created successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
                'email_integration' => $request->enable_email_integration,
                'email_account_id' => $emailAccountId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Agent created successfully',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                        'department_id' => $user->department_id,
                        'created_at' => $user->created_at,
                    ],
                    'email_integration' => $request->enable_email_integration,
                    'email_account_id' => $emailAccountId,
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to create agent', [
                'error' => $e->getMessage(),
                'request_data' => $request->except('password', 'gmail_app_password'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create agent',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create email account for agent in email service
     */
    protected function createAgentEmailAccount(User $user, array $emailData): ?string
    {
        try {
            // Call email service to create the agent's email account
            $response = Http::post(env('EMAIL_SERVICE_URL', 'http://localhost:8003') . '/api/v1/accounts', [
                'name' => $user->name . ' (Agent)',
                'email_address' => $emailData['gmail_address'],
                'account_type' => 'shared_mailbox',

                // IMAP Configuration (Gmail)
                'imap_host' => 'imap.gmail.com',
                'imap_port' => 993,
                'imap_username' => $emailData['gmail_address'],
                'imap_password' => $emailData['gmail_app_password'],
                'imap_use_ssl' => true,

                // SMTP Configuration (Gmail)
                'smtp_host' => 'smtp.gmail.com',
                'smtp_port' => 587,
                'smtp_username' => $emailData['gmail_address'],
                'smtp_password' => $emailData['gmail_app_password'],
                'smtp_use_tls' => true,

                // Agent-specific settings
                'user_id' => $user->id,
                'department_id' => $user->department_id,
                'auto_create_tickets' => true,
                'default_ticket_priority' => 'medium',
                'is_active' => true,
                'signature_template' => $emailData['agent_signature'] ?? $this->getDefaultSignatureTemplate(),

                // Routing rules for agent emails
                'routing_rules' => [
                    'auto_assign_to_agent' => $user->id,
                    'default_department' => $user->department_id,
                ]
            ]);

            if ($response->successful() && $response->json('success')) {
                return $response->json('data.id');
            } else {
                Log::error('Failed to create email account for agent', [
                    'user_id' => $user->id,
                    'response' => $response->json(),
                ]);
                return null;
            }

        } catch (\Exception $e) {
            Log::error('Error creating email account for agent', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Update agent email integration
     */
    public function updateAgentEmail(Request $request, $userId)
    {
        $validator = Validator::make($request->all(), [
            'enable_email_integration' => 'required|boolean',
            'gmail_address' => 'required_if:enable_email_integration,true|email',
            'gmail_app_password' => 'required_if:enable_email_integration,true|string',
            'agent_signature' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::findOrFail($userId);

            if ($request->enable_email_integration) {
                // Update or create email account
                $emailAccountId = $this->updateAgentEmailAccount($user, [
                    'gmail_address' => $request->gmail_address,
                    'gmail_app_password' => $request->gmail_app_password,
                    'agent_signature' => $request->agent_signature,
                ]);
            } else {
                // Disable email account
                $this->disableAgentEmailAccount($user);
            }

            return response()->json([
                'success' => true,
                'message' => 'Agent email integration updated successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update agent email integration',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update existing email account for agent
     */
    protected function updateAgentEmailAccount(User $user, array $emailData): ?string
    {
        // Call email service to update the agent's email account
        $response = Http::put(env('EMAIL_SERVICE_URL', 'http://localhost:8003') . '/api/v1/accounts/user/' . $user->id, [
            'gmail_address' => $emailData['gmail_address'],
            'gmail_app_password' => $emailData['gmail_app_password'],
            'signature_template' => $emailData['agent_signature'],
        ]);

        return $response->successful() ? $response->json('data.id') : null;
    }

    /**
     * Disable agent's email account
     */
    protected function disableAgentEmailAccount(User $user): void
    {
        Http::put(env('EMAIL_SERVICE_URL', 'http://localhost:8003') . '/api/v1/accounts/user/' . $user->id . '/disable');
    }

    /**
     * Get default signature template
     */
    protected function getDefaultSignatureTemplate(): string
    {
        return "\n\n---\nBest regards,\n{agent_name}\n{department_name}\nAidlY Support Team\n{mailbox_address}";
    }

    /**
     * List all agents with their email integration status
     */
    public function listAgents()
    {
        try {
            // Get agents from auth service
            $agents = User::whereIn('role', ['agent', 'supervisor'])
                ->with('department')
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                        'department' => $user->department->name ?? null,
                        'is_active' => $user->is_active,
                        'created_at' => $user->created_at,
                    ];
                });

            // Get email integration status from email service
            $response = Http::get(env('EMAIL_SERVICE_URL', 'http://localhost:8003') . '/api/v1/accounts/agents');
            $emailAccounts = $response->successful() ? $response->json('data') : [];

            // Merge agent data with email integration status
            $agentsWithEmail = $agents->map(function ($agent) use ($emailAccounts) {
                $emailAccount = collect($emailAccounts)->firstWhere('user_id', $agent['id']);
                $agent['email_integration'] = [
                    'enabled' => $emailAccount !== null,
                    'email_address' => $emailAccount['email_address'] ?? null,
                    'is_active' => $emailAccount['is_active'] ?? false,
                    'last_sync' => $emailAccount['last_sync_at'] ?? null,
                ];
                return $agent;
            });

            return response()->json([
                'success' => true,
                'data' => $agentsWithEmail
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to list agents',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}