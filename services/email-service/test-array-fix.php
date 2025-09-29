<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

use App\Models\EmailQueue;

echo "=== Testing PostgreSQL Array Fix ===\n\n";

try {
    // Test creating email queue record with arrays
    $testData = [
        'email_account_id' => 'fa36fbe6-15ef-4064-990c-37ae79ad9ff6',
        'message_id' => 'test-' . time(),
        'from_address' => 'test@example.com',
        'to_addresses' => ['test@example.com'], // Non-empty array
        'cc_addresses' => null, // Empty/null
        'subject' => 'Test Subject',
        'body_plain' => 'Test body',
        'body_html' => '<p>Test body</p>',
        'headers' => ['test' => 'value'],
        'attachments' => null, // Empty/null
        'is_processed' => false,
        'retry_count' => 0,
    ];

    echo "Creating test email with mixed array/null values...\n";
    $email = new EmailQueue($testData);
    $email->save();

    echo "✅ Success! Email saved with ID: {$email->id}\n";
    echo "- to_addresses: " . json_encode($email->to_addresses) . "\n";
    echo "- cc_addresses: " . json_encode($email->cc_addresses) . "\n";
    echo "- headers: " . json_encode($email->headers) . "\n";
    echo "- attachments: " . json_encode($email->attachments) . "\n";

    // Clean up
    $email->delete();
    echo "\n✅ Test email deleted. Array handling is working!\n";

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}