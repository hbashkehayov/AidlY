<?php

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║        Email-to-Ticket Integration Test Results               ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$tests = [];

// Test 1: Email Service Health
echo "1. Testing Email Service Health...\n";
$ch = curl_init('http://localhost:8005/health');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo "   ✅ Email service is healthy\n";
    $tests['email_service'] = true;
} else {
    echo "   ❌ Email service is not responding\n";
    $tests['email_service'] = false;
}

// Test 2: Ticket Service Connectivity
echo "\n2. Testing Ticket Service Connectivity...\n";
$ch = curl_init('http://localhost:8002/health');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo "   ✅ Ticket service is accessible\n";
    $tests['ticket_service'] = true;
} else {
    echo "   ❌ Ticket service is not accessible\n";
    $tests['ticket_service'] = false;
}

// Test 3: Client Service Connectivity
echo "\n3. Testing Client Service Connectivity...\n";
$ch = curl_init('http://localhost:8003/health');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo "   ✅ Client service is accessible\n";
    $tests['client_service'] = true;
} else {
    echo "   ❌ Client service is not accessible\n";
    $tests['client_service'] = false;
}

// Test 4: Database Connectivity
echo "\n4. Testing Database Connectivity...\n";
$dbHost = 'localhost';
$dbPort = 5432;
$dbName = 'aidly';
$dbUser = 'aidly_user';
$dbPass = 'aidly_secret_2024';

try {
    $dsn = "pgsql:host=$dbHost;port=$dbPort;dbname=$dbName";
    $pdo = new PDO($dsn, $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Test query
    $stmt = $pdo->query("SELECT COUNT(*) FROM email_queue");
    $count = $stmt->fetchColumn();

    echo "   ✅ Database connected (Email queue count: $count)\n";
    $tests['database'] = true;
} catch (PDOException $e) {
    echo "   ❌ Database connection failed: " . $e->getMessage() . "\n";
    $tests['database'] = false;
}

// Test 5: Redis Connectivity
echo "\n5. Testing Redis Cache...\n";
try {
    $redis = new Redis();
    $redis->connect('localhost', 6379);
    $redis->auth('redis_secret_2024');
    $redis->ping();
    echo "   ✅ Redis is connected\n";
    $tests['redis'] = true;
} catch (Exception $e) {
    echo "   ❌ Redis connection failed: " . $e->getMessage() . "\n";
    $tests['redis'] = false;
}

// Test 6: File Permissions
echo "\n6. Testing File Permissions...\n";
$storageDir = '/root/AidlY/services/email-service/storage';
if (is_writable($storageDir)) {
    echo "   ✅ Storage directory is writable\n";
    $tests['storage'] = true;
} else {
    echo "   ❌ Storage directory is not writable\n";
    $tests['storage'] = false;
}

// Test 7: Command Availability
echo "\n7. Testing Artisan Command...\n";
$output = [];
$returnCode = 0;
exec('php artisan list 2>/dev/null | grep emails:to-tickets', $output, $returnCode);
if (!empty($output)) {
    echo "   ✅ emails:to-tickets command is registered\n";
    $tests['command'] = true;
} else {
    echo "   ❌ emails:to-tickets command not found\n";
    $tests['command'] = false;
}

// Test 8: Service Classes
echo "\n8. Testing Service Classes...\n";
require_once __DIR__ . '/vendor/autoload.php';

$classes = [
    'App\Services\EmailToTicketService',
    'App\Services\AttachmentService',
    'App\Services\TicketAssignmentService'
];

$allClassesExist = true;
foreach ($classes as $class) {
    if (class_exists($class)) {
        echo "   ✅ $class exists\n";
    } else {
        echo "   ❌ $class not found\n";
        $allClassesExist = false;
    }
}
$tests['classes'] = $allClassesExist;

// Summary
echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                         TEST SUMMARY                          ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$passed = array_sum($tests);
$total = count($tests);
$failed = $total - $passed;

echo "Total Tests: $total\n";
echo "✅ Passed: $passed\n";
if ($failed > 0) {
    echo "❌ Failed: $failed\n\n";

    echo "Failed Tests:\n";
    foreach ($tests as $name => $result) {
        if (!$result) {
            echo "  - " . str_replace('_', ' ', ucfirst($name)) . "\n";
        }
    }
} else {
    echo "\n🎉 All tests passed! The email-to-ticket system is fully operational.\n";
}

// Recommendations
if (!$tests['database']) {
    echo "\n⚠️  Database connection is critical. Check your database credentials and ensure PostgreSQL is running.\n";
}

if (!$tests['ticket_service'] || !$tests['client_service']) {
    echo "\n⚠️  Some services are not running. Run: cd /root/AidlY && ./start-all-services.sh\n";
}

echo "\n";