<?php
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

use App\Models\EmailAccount;

echo "\nUpdating Gmail account with provided app password...\n";

try {
    // Find the Gmail account
    $emailAccount = EmailAccount::where('email_address', 'hristiyan.bashkehayov@gmail.com')->first();

    if (!$emailAccount) {
        echo "Error: Email account not found in database.\n";
        exit(1);
    }

    // Update with the provided app password
    $appPassword = 'ufhtbybkxzqmqybm';

    $emailAccount->imap_username = 'hristiyan.bashkehayov@gmail.com';
    $emailAccount->imap_password = $appPassword; // Will be encrypted by mutator
    $emailAccount->smtp_username = 'hristiyan.bashkehayov@gmail.com';
    $emailAccount->smtp_password = $appPassword; // Will be encrypted by mutator
    $emailAccount->save();

    echo "✅ Email account credentials updated successfully!\n\n";

} catch (\Exception $e) {
    echo "\nError updating account: " . $e->getMessage() . "\n";
    exit(1);
}