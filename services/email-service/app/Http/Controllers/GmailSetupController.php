<?php

namespace App\Http\Controllers;

use App\Models\EmailAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\ImapService;
use App\Services\SmtpService;

class GmailSetupController extends Controller
{
    protected $imapService;
    protected $smtpService;

    public function __construct(ImapService $imapService, SmtpService $smtpService)
    {
        $this->imapService = $imapService;
        $this->smtpService = $smtpService;
    }

    /**
     * Get Gmail setup instructions
     */
    public function instructions()
    {
        return response()->json([
            'success' => true,
            'instructions' => [
                'title' => 'Setting up Gmail for AidlY',
                'steps' => [
                    [
                        'step' => 1,
                        'title' => 'Enable 2-Factor Authentication',
                        'description' => 'Go to your Google Account settings and enable 2-Factor Authentication if not already enabled.'
                    ],
                    [
                        'step' => 2,
                        'title' => 'Generate App Password',
                        'description' => 'Go to Google Account > Security > 2-Step Verification > App passwords. Generate a new app password for "Mail".'
                    ],
                    [
                        'step' => 3,
                        'title' => 'Configure Email Account',
                        'description' => 'Use the generated app password (not your regular Gmail password) when setting up your email account in AidlY.'
                    ]
                ],
                'settings' => [
                    'imap_host' => 'imap.gmail.com',
                    'imap_port' => 993,
                    'imap_use_ssl' => true,
                    'smtp_host' => 'smtp.gmail.com',
                    'smtp_port' => 587,
                    'smtp_use_tls' => true
                ]
            ]
        ]);
    }

    /**
     * Quick setup for Gmail account
     */
    public function quickSetup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:email_accounts,email_address',
            'app_password' => 'required|string|min:16', // Gmail app passwords are 16 chars
            'department_id' => 'nullable|uuid',
            'default_ticket_priority' => 'nullable|in:low,medium,high,urgent',
            'default_category_id' => 'nullable|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Test connection first
            $testResult = $this->testGmailConnection($request->email, $request->app_password);

            if (!$testResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gmail connection test failed',
                    'error' => $testResult['error']
                ], 400);
            }

            // Create the email account
            $account = EmailAccount::create([
                'name' => $request->name,
                'email_address' => $request->email,

                // IMAP settings for Gmail
                'imap_host' => 'imap.gmail.com',
                'imap_port' => 993,
                'imap_username' => $request->email,
                'imap_password' => $request->app_password,
                'imap_use_ssl' => true,

                // SMTP settings for Gmail
                'smtp_host' => 'smtp.gmail.com',
                'smtp_port' => 587,
                'smtp_username' => $request->email,
                'smtp_password' => $request->app_password,
                'smtp_use_tls' => true,

                // Configuration
                'department_id' => $request->department_id,
                'auto_create_tickets' => true,
                'default_ticket_priority' => $request->get('default_ticket_priority', 'medium'),
                'default_category_id' => $request->default_category_id,
                'is_active' => true
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Gmail account configured successfully',
                'data' => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'email' => $account->email_address,
                    'auto_create_tickets' => $account->auto_create_tickets,
                    'is_active' => $account->is_active
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to configure Gmail account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test Gmail connection
     */
    public function testConnection(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'app_password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->testGmailConnection($request->email, $request->app_password);

        return response()->json($result);
    }

    /**
     * Get recommended Gmail settings
     */
    public function recommendedSettings()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'imap' => [
                    'host' => 'imap.gmail.com',
                    'port' => 993,
                    'encryption' => 'SSL',
                    'description' => 'Gmail IMAP settings for receiving emails'
                ],
                'smtp' => [
                    'host' => 'smtp.gmail.com',
                    'port' => 587,
                    'encryption' => 'TLS',
                    'description' => 'Gmail SMTP settings for sending emails'
                ],
                'requirements' => [
                    '2-Factor Authentication must be enabled on your Google account',
                    'Use App Password instead of regular Gmail password',
                    'Less Secure Apps setting is no longer needed with App Passwords'
                ],
                'folders' => [
                    'inbox' => 'INBOX',
                    'sent' => '[Gmail]/Sent Mail',
                    'spam' => '[Gmail]/Spam',
                    'trash' => '[Gmail]/Trash'
                ]
            ]
        ]);
    }

    /**
     * Fetch recent emails to test the setup
     */
    public function testFetch(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_id' => 'required|uuid',
            'limit' => 'nullable|integer|min:1|max:10'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $account = EmailAccount::find($request->account_id);

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Email account not found'
            ], 404);
        }

        try {
            $result = $this->imapService->fetchEmailsFromAccount($account, [
                'limit' => $request->get('limit', 5),
                'mark_as_seen' => false
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Test fetch completed',
                'data' => [
                    'account' => $account->name,
                    'emails_found' => $result['count'],
                    'preview' => array_map(function($email) {
                        return [
                            'subject' => $email['subject'] ?? 'No subject',
                            'from' => $email['from'] ?? 'Unknown sender',
                            'date' => $email['date'] ?? null,
                            'has_attachments' => !empty($email['attachments'])
                        ];
                    }, array_slice($result['emails'] ?? [], 0, 3))
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Test fetch failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test Gmail connection with provided credentials
     */
    protected function testGmailConnection(string $email, string $appPassword): array
    {
        try {
            // Test IMAP connection
            $imapConfig = [
                'host' => 'imap.gmail.com',
                'port' => 993,
                'encryption' => 'ssl',
                'username' => $email,
                'password' => $appPassword,
                'validate_cert' => true,
            ];

            // Create a temporary EmailAccount model for testing
            $tempAccount = new EmailAccount();
            $tempAccount->fill([
                'imap_host' => $imapConfig['host'],
                'imap_port' => $imapConfig['port'],
                'imap_username' => $imapConfig['username'],
                'imap_password' => $imapConfig['password'],
                'imap_use_ssl' => $imapConfig['encryption'] === 'ssl'
            ]);

            $imapTest = $this->imapService->testConnection($tempAccount);

            if (!$imapTest['success']) {
                return [
                    'success' => false,
                    'error' => 'IMAP connection failed: ' . $imapTest['error']
                ];
            }

            // Test SMTP connection
            $smtpConfig = [
                'host' => 'smtp.gmail.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => $email,
                'password' => $appPassword,
            ];

            // Create a temporary EmailAccount model for SMTP testing
            $tempSmtpAccount = new EmailAccount();
            $tempSmtpAccount->fill([
                'smtp_host' => $smtpConfig['host'],
                'smtp_port' => $smtpConfig['port'],
                'smtp_username' => $smtpConfig['username'],
                'smtp_password' => $smtpConfig['password'],
                'smtp_use_tls' => $smtpConfig['encryption'] === 'tls'
            ]);

            $smtpTest = $this->smtpService->testConnection($tempSmtpAccount);

            if (!$smtpTest['success']) {
                return [
                    'success' => false,
                    'error' => 'SMTP connection failed: ' . $smtpTest['error']
                ];
            }

            return [
                'success' => true,
                'message' => 'Gmail connection successful',
                'details' => [
                    'imap' => 'Connected successfully',
                    'smtp' => 'Connected successfully',
                    'inbox_messages' => $imapTest['message_count'] ?? 0
                ]
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Connection test failed: ' . $e->getMessage()
            ];
        }
    }
}