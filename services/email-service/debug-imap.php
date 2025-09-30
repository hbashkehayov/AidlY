<?php

echo "Debugging Gmail IMAP connection...\n\n";

$host = 'imap.gmail.com';
$port = 993;
$username = 'hristiyan.bashkehayov@gmail.com';
$password = 'ufhtbybkxzqmqybm';

$mailbox = "{{$host}:{$port}/imap/ssl}INBOX";
$imap = @imap_open($mailbox, $username, $password);

if (!$imap) {
    echo "❌ Connection failed: " . imap_last_error() . "\n";
    exit(1);
}

echo "✅ Connected to Gmail\n\n";

// Get all emails from today
$today = date('j-M-Y', strtotime('today'));
$searchCriteria = "SINCE \"{$today}\"";

echo "Searching for emails since: {$today}\n";
$emails = imap_search($imap, $searchCriteria);

if (!$emails) {
    echo "No emails found today\n";
} else {
    echo "Found " . count($emails) . " email(s) today:\n\n";

    foreach ($emails as $emailNumber) {
        $header = imap_headerinfo($imap, $emailNumber);
        $flags = imap_fetch_overview($imap, $emailNumber)[0];

        echo "-----------------------------------\n";
        echo "Subject: " . ($header->subject ?? '(no subject)') . "\n";
        echo "From: " . $header->from[0]->mailbox . "@" . $header->from[0]->host . "\n";
        echo "Date: " . date('Y-m-d H:i:s', $header->udate) . "\n";
        echo "Message ID: " . ($header->message_id ?? 'N/A') . "\n";
        echo "Seen: " . ($flags->seen ? "YES" : "NO") . "\n";
        echo "Answered: " . ($flags->answered ? "YES" : "NO") . "\n";
        echo "Flagged: " . ($flags->flagged ? "YES" : "NO") . "\n";

        // Check if this message ID exists in our database
        if (isset($header->message_id)) {
            require_once __DIR__ . '/vendor/autoload.php';
            $app = require_once __DIR__ . '/bootstrap/app.php';
            $app->withFacades();
            $app->withEloquent();
            $app->boot();

            $exists = \App\Models\EmailQueue::where('message_id', $header->message_id)->exists();
            echo "In Database: " . ($exists ? "YES" : "NO") . "\n";
        }
    }
}

// Check for UNSEEN emails specifically
echo "\n-----------------------------------\n";
echo "Checking for UNSEEN emails...\n";
$unseenEmails = imap_search($imap, 'UNSEEN');

if (!$unseenEmails) {
    echo "No unseen emails found\n";
} else {
    echo "Found " . count($unseenEmails) . " unseen email(s)\n";
}

imap_close($imap);
echo "\n✅ Debug complete\n";