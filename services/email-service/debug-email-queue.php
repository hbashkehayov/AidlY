<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap the application
$app = require_once __DIR__ . '/bootstrap/app.php';

use App\Models\EmailQueue;
use App\Services\EmailToTicketService;

echo "=== Email Queue Debug ===\n\n";

// Check pending emails
$pendingCount = EmailQueue::pending()->count();
$processedCount = EmailQueue::processed()->count();
$failedCount = EmailQueue::failed()->count();

echo "Queue Status:\n";
echo "- Pending: {$pendingCount}\n";
echo "- Processed: {$processedCount}\n";
echo "- Failed: {$failedCount}\n\n";

// Get first pending email details
$firstPending = EmailQueue::pending()->first();
if ($firstPending) {
    echo "First Pending Email:\n";
    echo "- ID: {$firstPending->id}\n";
    echo "- Subject: {$firstPending->subject}\n";
    echo "- From: {$firstPending->from_address}\n";
    echo "- Error: " . ($firstPending->error_message ?? 'None') . "\n";
    echo "- Retry Count: {$firstPending->retry_count}\n\n";

    // Try to process it
    echo "Attempting to process this email...\n";
    try {
        $service = new EmailToTicketService();
        $result = $service->processEmail($firstPending);
        echo "Success! Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
    } catch (\Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        echo "Trace: " . $e->getTraceAsString() . "\n";
    }
} else {
    echo "No pending emails found.\n";
}

// Check failed emails with errors
$failedEmails = EmailQueue::whereNotNull('error_message')->limit(3)->get();
if ($failedEmails->count() > 0) {
    echo "\nFailed Emails:\n";
    foreach ($failedEmails as $email) {
        echo "- [{$email->id}] {$email->subject}\n";
        echo "  Error: {$email->error_message}\n";
    }
}