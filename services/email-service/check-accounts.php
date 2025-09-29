<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

use App\Models\EmailAccount;

echo "=== Checking Email Accounts Configuration ===\n\n";

try {
    $accounts = EmailAccount::all();

    if ($accounts->isEmpty()) {
        echo "❌ No email accounts found in database!\n";
        exit;
    }

    foreach ($accounts as $account) {
        echo "Account: {$account->name}\n";
        echo "- ID: {$account->id}\n";
        echo "- Email: {$account->email}\n";
        echo "- IMAP Host: {$account->imap_host}\n";
        echo "- IMAP Username: {$account->imap_username}\n";
        echo "- IMAP Port: {$account->imap_port}\n";
        echo "- IMAP SSL: " . ($account->imap_use_ssl ? 'Yes' : 'No') . "\n";
        echo "- Active: " . ($account->is_active ? 'Yes' : 'No') . "\n";
        echo "- Auto Create Tickets: " . ($account->auto_create_tickets ? 'Yes' : 'No') . "\n";
        echo "- Last Sync: " . ($account->last_sync_at ? $account->last_sync_at : 'Never') . "\n";
        echo "---\n";
    }

    // Also check what's in the email queue
    echo "\n=== Email Queue Status ===\n";
    $totalEmails = \App\Models\EmailQueue::count();
    $pendingEmails = \App\Models\EmailQueue::pending()->count();
    $processedEmails = \App\Models\EmailQueue::where('is_processed', true)->count();
    $failedEmails = \App\Models\EmailQueue::whereNotNull('error_message')->count();

    echo "Total emails in queue: {$totalEmails}\n";
    echo "Pending emails: {$pendingEmails}\n";
    echo "Processed emails: {$processedEmails}\n";
    echo "Failed emails: {$failedEmails}\n";

    if ($pendingEmails > 0) {
        echo "\n=== Recent Pending Emails ===\n";
        $recentEmails = \App\Models\EmailQueue::pending()
            ->orderBy('received_at', 'desc')
            ->limit(5)
            ->get(['from_address', 'subject', 'received_at']);

        foreach ($recentEmails as $email) {
            echo "- From: {$email->from_address}\n";
            echo "  Subject: {$email->subject}\n";
            echo "  Received: {$email->received_at}\n";
            echo "\n";
        }
    }

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}