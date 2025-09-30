<?php

/**
 * Setup script for support@softart.bg shared mailbox
 * This script configures the shared mailbox with the provided app password
 */

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';

use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

echo "========================================\n";
echo "AidlY Shared Mailbox Configuration\n";
echo "========================================\n";

try {
    // Test database connection
    DB::connection()->getPdo();
    echo "✅ Database connection successful\n";

    // Check if shared mailbox already exists
    $existingMailbox = DB::table('email_accounts')
        ->where('email_address', 'support@softart.bg')
        ->first();

    if ($existingMailbox) {
        echo "⚠️  Shared mailbox support@softart.bg already exists\n";
        echo "   Updating existing configuration...\n";

        // Update existing mailbox to be a shared mailbox
        DB::table('email_accounts')
            ->where('id', $existingMailbox->id)
            ->update([
                'name' => 'SoftArt Support',
                'account_type' => 'shared_mailbox',
                'imap_host' => 'imap.gmail.com',
                'imap_port' => 993,
                'imap_username' => 'support@softart.bg',
                'imap_use_ssl' => true,
                'smtp_host' => 'smtp.gmail.com',
                'smtp_port' => 587,
                'smtp_username' => 'support@softart.bg',
                'smtp_use_tls' => true,
                'auto_create_tickets' => true,
                'default_ticket_priority' => 'medium',
                'routing_rules' => json_encode([
                    [
                        'name' => 'Urgent Priority',
                        'subject_contains' => ['urgent', 'critical', 'emergency', 'asap'],
                        'priority' => 'urgent'
                    ],
                    [
                        'name' => 'High Priority',
                        'subject_contains' => ['important', 'priority', 'problem', 'issue'],
                        'priority' => 'high'
                    ],
                    [
                        'name' => 'Bug Reports',
                        'subject_contains' => ['bug', 'error', 'crash', 'not working'],
                        'priority' => 'high'
                    ]
                ]),
                'signature_template' => "Best regards,\n{agent_name}\n{department_name}\nSoftArt Support Team\n\n--\nThis email was sent from our support system.\nPlease reply to this email for fastest assistance.\n\nSoftArt - Building Tomorrow's Software Today",
                'is_active' => true,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

        echo "✅ Updated existing mailbox configuration\n";

    } else {
        echo "📧 Creating new shared mailbox: support@softart.bg\n";

        // Get app password (you'll need to provide this)
        echo "\nTo complete setup, you need your Gmail App Password.\n";
        echo "If you don't have one:\n";
        echo "1. Go to myaccount.google.com\n";
        echo "2. Security > 2-Step Verification > App passwords\n";
        echo "3. Generate password for 'AidlY Support'\n";
        echo "\nEnter your Gmail App Password for support@softart.bg: ";

        $appPassword = trim(fgets(STDIN));

        if (empty($appPassword)) {
            echo "❌ App password is required. Exiting.\n";
            exit(1);
        }

        // Create new shared mailbox
        $mailboxId = (string) Uuid::uuid4();

        DB::table('email_accounts')->insert([
            'id' => $mailboxId,
            'name' => 'SoftArt Support',
            'email_address' => 'support@softart.bg',
            'account_type' => 'shared_mailbox',

            // IMAP Settings (Gmail)
            'imap_host' => 'imap.gmail.com',
            'imap_port' => 993,
            'imap_username' => 'support@softart.bg',
            'imap_password_encrypted' => encrypt($appPassword),
            'imap_use_ssl' => true,

            // SMTP Settings (Gmail)
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => 587,
            'smtp_username' => 'support@softart.bg',
            'smtp_password_encrypted' => encrypt($appPassword),
            'smtp_use_tls' => true,

            // Ticket Creation Settings
            'auto_create_tickets' => true,
            'default_ticket_priority' => 'medium',

            // Routing Rules (JSON)
            'routing_rules' => json_encode([
                [
                    'name' => 'Urgent Priority',
                    'subject_contains' => ['urgent', 'critical', 'emergency', 'asap'],
                    'priority' => 'urgent'
                ],
                [
                    'name' => 'High Priority',
                    'subject_contains' => ['important', 'priority', 'problem', 'issue'],
                    'priority' => 'high'
                ],
                [
                    'name' => 'Bug Reports',
                    'subject_contains' => ['bug', 'error', 'crash', 'not working'],
                    'priority' => 'high'
                ],
                [
                    'name' => 'Feature Requests',
                    'subject_contains' => ['feature', 'request', 'enhancement', 'suggestion'],
                    'priority' => 'low'
                ]
            ]),

            // Agent Signature Template
            'signature_template' => "Best regards,\n{agent_name}\n{department_name}\nSoftArt Support Team\n\n--\nThis email was sent from our support system.\nPlease reply to this email for fastest assistance.\n\nSoftArt - Building Tomorrow's Software Today",

            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'last_sync_at' => null
        ]);

        echo "✅ Created shared mailbox successfully!\n";
    }

    echo "\n📋 Shared Mailbox Configuration:\n";
    echo "================================\n";
    echo "Email: support@softart.bg\n";
    echo "Type: Shared Mailbox\n";
    echo "IMAP: imap.gmail.com:993 (SSL)\n";
    echo "SMTP: smtp.gmail.com:587 (TLS)\n";
    echo "Auto-create tickets: Yes\n";
    echo "Default priority: Medium\n";
    echo "Routing rules: 4 configured\n";

    echo "\n🎯 Next Steps:\n";
    echo "==============\n";
    echo "1. Test connection: php artisan mailbox:process-shared --test-connections\n";
    echo "2. Run dry test: php artisan mailbox:process-shared --dry-run --detailed\n";
    echo "3. Setup cron: ./setup-cron.sh\n";
    echo "4. Monitor logs: tail -f storage/logs/shared-mailbox.log\n";

    echo "\n✅ Shared mailbox setup complete!\n";
    echo "========================================\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}