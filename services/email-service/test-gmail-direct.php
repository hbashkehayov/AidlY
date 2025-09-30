<?php

echo "Testing direct IMAP connection to Gmail...\n\n";

$host = 'imap.gmail.com';
$port = 993;
$username = 'hristiyan.bashkehayov@gmail.com';
$password = 'ufhtbybkxzqmqybm';

// Method 1: Using PHP's built-in IMAP functions
echo "Method 1: PHP IMAP Extension\n";
echo "------------------------------\n";

$mailbox = "{{$host}:{$port}/imap/ssl}INBOX";
$imap = @imap_open($mailbox, $username, $password);

if ($imap) {
    echo "✅ Connection successful!\n";
    $check = imap_check($imap);
    echo "Messages in inbox: " . $check->Nmsgs . "\n";
    echo "Recent messages: " . $check->Recent . "\n";
    imap_close($imap);
} else {
    echo "❌ Connection failed: " . imap_last_error() . "\n";
}

echo "\n";

// Method 2: Test with openssl
echo "Method 2: OpenSSL Connection Test\n";
echo "----------------------------------\n";

$command = "echo '' | openssl s_client -connect {$host}:{$port} -servername {$host} 2>/dev/null | grep 'Verify return code'";
exec($command, $output);

if (!empty($output)) {
    echo "✅ SSL connection successful\n";
    echo implode("\n", $output) . "\n";
} else {
    echo "❌ SSL connection failed\n";
}

echo "\n";

// Method 3: Check with curl
echo "Method 3: CURL IMAP Test\n";
echo "------------------------\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "imaps://{$host}:{$port}");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERNAME, $username);
curl_setopt($ch, CURLOPT_PASSWORD, $password);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "CAPABILITY");

$result = curl_exec($ch);
$error = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($result !== false) {
    echo "✅ CURL connection successful\n";
    echo "Response: " . substr($result, 0, 100) . "...\n";
} else {
    echo "❌ CURL connection failed: " . $error . "\n";
}

echo "\n";