<?php

echo "=== Real Email Monitoring Script ===\n";
echo "Send an email to hristiyan.bashkehayov@gmail.com now!\n";
echo "From: hbashkehayov@softart.bg\n";
echo "Subject: Test Email to Ticket - " . date('Y-m-d H:i:s') . "\n";
echo "Body: This is a test email to verify email-to-ticket conversion.\n\n";

echo "Monitoring for new emails every 30 seconds...\n";
echo "Press Ctrl+C to stop\n\n";

$previousCount = 0;

while (true) {
    echo "[" . date('H:i:s') . "] Checking for new emails...\n";

    // Run the fetch command and capture output
    $output = shell_exec('cd /root/AidlY/services/email-service && php artisan emails:fetch --verbose 2>&1');

    // Check if any emails were found
    if (strpos($output, ': 0 emails') === false && strpos($output, 'emails') !== false) {
        echo "✅ NEW EMAIL DETECTED!\n";
        echo $output . "\n";

        echo "Processing emails to tickets...\n";
        $processOutput = shell_exec('cd /root/AidlY/services/email-service && php artisan emails:process 2>&1');
        echo $processOutput . "\n";

        break;
    } else {
        echo "No new emails found. Waiting...\n";
    }

    sleep(30);
}

echo "Email detected and processed! Check your tickets.\n";