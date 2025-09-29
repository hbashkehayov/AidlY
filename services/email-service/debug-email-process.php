<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

use App\Models\EmailQueue;
use App\Services\EmailToTicketService;

echo "=== Testing Email to Ticket Process ===\n\n";

// Get first pending email
$email = EmailQueue::pending()->first();

if (!$email) {
    echo "No pending emails found.\n";
    exit;
}

echo "Processing email:\n";
echo "- ID: {$email->id}\n";
echo "- Subject: {$email->subject}\n";
echo "- From: {$email->from_address}\n\n";

$service = new EmailToTicketService();

// Test client finding/creation
echo "Testing client creation for: {$email->from_address}\n";

try {
    // Use reflection to call protected method
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('findOrCreateClient');
    $method->setAccessible(true);

    $client = $method->invoke($service, $email->from_address);

    if ($client) {
        echo "✅ Client found/created successfully!\n";
        echo "- Client ID: {$client['id']}\n";
        echo "- Client Name: {$client['name']}\n";
        echo "- Client Email: {$client['email']}\n\n";

        // Now try to process the email
        echo "Attempting to process email to ticket...\n";
        $result = $service->processEmail($email);
        echo "✅ Success! Result:\n";
        print_r($result);
    } else {
        echo "❌ Failed to find/create client\n";

        // Try direct API call
        echo "\nTrying direct API call to client service...\n";
        $clientServiceUrl = env('CLIENT_SERVICE_URL', 'http://localhost:8003');

        // Search for existing
        $ch = curl_init("{$clientServiceUrl}/api/v1/clients?email={$email->from_address}&limit=1");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $searchResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        echo "Search response (HTTP {$httpCode}):\n";
        echo substr($searchResponse, 0, 500) . "\n\n";

        // Try to create
        $ch = curl_init("{$clientServiceUrl}/api/v1/clients");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'email' => $email->from_address,
            'name' => explode('@', $email->from_address)[0]
        ]));
        $createResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        echo "Create response (HTTP {$httpCode}):\n";
        echo substr($createResponse, 0, 500) . "\n";
    }
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}