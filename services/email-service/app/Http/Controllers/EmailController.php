<?php

namespace App\Http\Controllers;

use App\Models\EmailQueue;
use App\Models\EmailAccount;
use App\Models\EmailTemplate;
use App\Services\ImapService;
use App\Services\SmtpService;
use App\Services\EmailToTicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EmailController extends Controller
{
    protected $imapService;
    protected $smtpService;
    protected $emailToTicketService;

    public function __construct(
        ImapService $imapService,
        SmtpService $smtpService,
        EmailToTicketService $emailToTicketService
    ) {
        $this->imapService = $imapService;
        $this->smtpService = $smtpService;
        $this->emailToTicketService = $emailToTicketService;
    }

    /**
     * Fetch emails from all accounts
     */
    public function fetchAll()
    {
        try {
            $results = $this->imapService->fetchAllEmails();

            return response()->json([
                'success' => true,
                'message' => 'Email fetch completed',
                'data' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch emails',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process pending emails to tickets
     */
    public function processToTickets()
    {
        try {
            $results = $this->emailToTicketService->processAllPendingEmails();

            return response()->json([
                'success' => true,
                'message' => 'Email processing completed',
                'data' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process emails',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send email
     */
    public function send(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_id' => 'sometimes|uuid',
            'to' => 'required|array|min:1',
            'to.*' => 'email',
            'cc' => 'sometimes|array',
            'cc.*' => 'email',
            'bcc' => 'sometimes|array',
            'bcc.*' => 'email',
            'subject' => 'required|string|max:500',
            'body_html' => 'sometimes|string',
            'body_plain' => 'sometimes|string',
            'attachments' => 'sometimes|array',
            'reply_to' => 'sometimes|email',
            'headers' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Require at least one body content
        if (empty($request->body_html) && empty($request->body_plain)) {
            return response()->json([
                'success' => false,
                'message' => 'Either body_html or body_plain is required'
            ], 422);
        }

        try {
            $emailData = $request->only([
                'to', 'cc', 'bcc', 'subject', 'body_html', 'body_plain',
                'attachments', 'reply_to', 'headers'
            ]);

            // Use specific account or default
            if ($request->has('account_id')) {
                $account = EmailAccount::find($request->account_id);
                if (!$account) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Email account not found'
                    ], 404);
                }
                $result = $this->smtpService->sendEmail($account, $emailData);
            } else {
                $result = $this->smtpService->sendEmailDefault($emailData);
            }

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send email',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send templated email
     */
    public function sendTemplate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_id' => 'required|uuid',
            'template_id' => 'required|uuid',
            'recipients' => 'required|array|min:1',
            'recipients.*' => 'email',
            'variables' => 'required|array',
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

        $template = EmailTemplate::active()->find($request->template_id);
        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Email template not found or inactive'
            ], 404);
        }

        try {
            $result = $this->smtpService->sendTemplatedEmail(
                $account,
                $template,
                $request->variables,
                $request->recipients
            );

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send templated emails',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send ticket notification
     */
    public function sendTicketNotification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:ticket_created,ticket_updated,ticket_resolved,ticket_closed,auto_reply',
            'ticket_data' => 'required|array',
            'client_data' => 'required|array',
            'client_data.email' => 'required|email',
            'account_id' => 'sometimes|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $account = null;
            if ($request->has('account_id')) {
                $account = EmailAccount::find($request->account_id);
                if (!$account) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Email account not found'
                    ], 404);
                }
            }

            $result = $this->smtpService->sendTicketNotification(
                $request->type,
                $request->ticket_data,
                $request->client_data,
                $account
            );

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send ticket notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get email queue
     */
    public function queue(Request $request)
    {
        $query = EmailQueue::query();

        // Filter by processing status
        if ($request->has('status')) {
            switch ($request->status) {
                case 'pending':
                    $query->pending();
                    break;
                case 'processed':
                    $query->processed();
                    break;
                case 'failed':
                    $query->failed();
                    break;
            }
        }

        // Filter by email account
        if ($request->has('account_id')) {
            $query->byAccount($request->account_id);
        }

        // Search by subject or sender
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('subject', 'ILIKE', "%{$search}%")
                  ->orWhere('from_address', 'ILIKE', "%{$search}%");
            });
        }

        $emails = $query->with('emailAccount')
            ->orderBy('received_at', 'desc')
            ->paginate($request->get('limit', 10));

        return response()->json([
            'success' => true,
            'data' => $emails->items(),
            'meta' => [
                'total' => $emails->total(),
                'page' => $emails->currentPage(),
                'pages' => $emails->lastPage(),
                'limit' => $emails->perPage(),
            ]
        ]);
    }

    /**
     * Get single email from queue
     */
    public function queueItem(string $id)
    {
        $email = EmailQueue::with('emailAccount')->find($id);

        if (!$email) {
            return response()->json([
                'success' => false,
                'message' => 'Email not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $email
        ]);
    }

    /**
     * Retry failed email processing
     */
    public function retryEmail(string $id)
    {
        $email = EmailQueue::find($id);

        if (!$email) {
            return response()->json([
                'success' => false,
                'message' => 'Email not found'
            ], 404);
        }

        if (!$email->shouldRetry()) {
            return response()->json([
                'success' => false,
                'message' => 'Email cannot be retried (max retries reached or already processed)'
            ], 400);
        }

        try {
            $result = $this->emailToTicketService->processEmail($email);

            return response()->json([
                'success' => true,
                'message' => 'Email processed successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process email',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get email processing statistics
     */
    public function stats()
    {
        $stats = [
            'total_emails' => EmailQueue::count(),
            'pending_emails' => EmailQueue::pending()->count(),
            'processed_emails' => EmailQueue::processed()->count(),
            'failed_emails' => EmailQueue::failed()->count(),
            'emails_with_attachments' => EmailQueue::whereNotNull('attachments')->count(),
            'emails_today' => EmailQueue::whereDate('received_at', date('Y-m-d'))->count(),
            'emails_this_week' => EmailQueue::whereDate('received_at', '>=', date('Y-m-d', strtotime('last monday')))->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}