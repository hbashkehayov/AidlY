<?php

namespace App\Http\Controllers;

use App\Models\EmailAccount;
use App\Services\ImapService;
use App\Services\SmtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EmailAccountController extends Controller
{
    protected $imapService;
    protected $smtpService;

    public function __construct(ImapService $imapService, SmtpService $smtpService)
    {
        $this->imapService = $imapService;
        $this->smtpService = $smtpService;
    }

    /**
     * Get all email accounts
     */
    public function index(Request $request)
    {
        $query = EmailAccount::query();

        // Filter by status
        if ($request->has('status') && $request->status === 'active') {
            $query->active();
        }

        // Search by name or email
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                  ->orWhere('email_address', 'ILIKE', "%{$search}%");
            });
        }

        $accounts = $query->paginate($request->get('limit', 10));

        return response()->json([
            'success' => true,
            'data' => $accounts->items(),
            'meta' => [
                'total' => $accounts->total(),
                'page' => $accounts->currentPage(),
                'pages' => $accounts->lastPage(),
                'limit' => $accounts->perPage(),
            ]
        ]);
    }

    /**
     * Get single email account
     */
    public function show(string $id)
    {
        $account = EmailAccount::find($id);

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Email account not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $account
        ]);
    }

    /**
     * Create new email account
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email_address' => 'required|email|unique:email_accounts',
            'imap_host' => 'required|string|max:255',
            'imap_port' => 'required|integer|min:1|max:65535',
            'imap_username' => 'required|string|max:255',
            'imap_password' => 'required|string',
            'imap_use_ssl' => 'boolean',
            'smtp_host' => 'required|string|max:255',
            'smtp_port' => 'required|integer|min:1|max:65535',
            'smtp_username' => 'required|string|max:255',
            'smtp_password' => 'required|string',
            'smtp_use_tls' => 'boolean',
            'department_id' => 'sometimes|uuid',
            'auto_create_tickets' => 'boolean',
            'default_ticket_priority' => 'sometimes|in:low,medium,high,urgent',
            'default_category_id' => 'sometimes|uuid',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $account = new EmailAccount($request->all());
            $account->save();

            return response()->json([
                'success' => true,
                'message' => 'Email account created successfully',
                'data' => $account
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create email account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update email account
     */
    public function update(Request $request, string $id)
    {
        $account = EmailAccount::find($id);

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Email account not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email_address' => 'sometimes|email|unique:email_accounts,email_address,' . $id,
            'imap_host' => 'sometimes|string|max:255',
            'imap_port' => 'sometimes|integer|min:1|max:65535',
            'imap_username' => 'sometimes|string|max:255',
            'imap_password' => 'sometimes|string',
            'imap_use_ssl' => 'sometimes|boolean',
            'smtp_host' => 'sometimes|string|max:255',
            'smtp_port' => 'sometimes|integer|min:1|max:65535',
            'smtp_username' => 'sometimes|string|max:255',
            'smtp_password' => 'sometimes|string',
            'smtp_use_tls' => 'sometimes|boolean',
            'department_id' => 'sometimes|uuid|nullable',
            'auto_create_tickets' => 'sometimes|boolean',
            'default_ticket_priority' => 'sometimes|in:low,medium,high,urgent',
            'default_category_id' => 'sometimes|uuid|nullable',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $account->fill($request->all());
            $account->save();

            return response()->json([
                'success' => true,
                'message' => 'Email account updated successfully',
                'data' => $account
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update email account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete email account
     */
    public function destroy(string $id)
    {
        $account = EmailAccount::find($id);

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Email account not found'
            ], 404);
        }

        try {
            $account->delete();

            return response()->json([
                'success' => true,
                'message' => 'Email account deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete email account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test IMAP connection
     */
    public function testImap(string $id)
    {
        $account = EmailAccount::find($id);

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Email account not found'
            ], 404);
        }

        $result = $this->imapService->testConnection($account);

        return response()->json($result);
    }

    /**
     * Test SMTP connection
     */
    public function testSmtp(string $id)
    {
        $account = EmailAccount::find($id);

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Email account not found'
            ], 404);
        }

        $result = $this->smtpService->testConnection($account);

        return response()->json($result);
    }

    /**
     * Get account statistics
     */
    public function stats(string $id)
    {
        $account = EmailAccount::find($id);

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Email account not found'
            ], 404);
        }

        $stats = $this->imapService->getAccountStats($account);

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Fetch emails for specific account
     */
    public function fetchEmails(string $id)
    {
        $account = EmailAccount::find($id);

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Email account not found'
            ], 404);
        }

        try {
            $result = $this->imapService->fetchEmailsFromAccount($account);

            return response()->json([
                'success' => true,
                'message' => "Fetched {$result['count']} emails",
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch emails',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}