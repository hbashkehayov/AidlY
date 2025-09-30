<?php

/**
 * Direct configuration script for support@softart.bg shared mailbox
 * This bypasses Laravel ORM issues and uses direct PDO connection
 */

echo "========================================\n";
echo "AidlY SoftArt Support Mailbox Setup\n";
echo "========================================\n";

// Database configuration (matching your .env)
$config = [
    'host' => 'localhost',
    'port' => 5432,
    'database' => 'aidly',
    'username' => 'aidly_user',
    'password' => 'aidly_secret_2024'
];

try {
    // Connect to PostgreSQL directly
    $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}";
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    echo "✅ Database connected successfully\n";

    // Check if support@softart.bg already exists
    $stmt = $pdo->prepare("SELECT id, name FROM email_accounts WHERE email_address = ?");
    $stmt->execute(['support@softart.bg']);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        echo "⚠️  Mailbox support@softart.bg already exists (ID: {$existing['id']})\n";
        echo "   Name: {$existing['name']}\n";
        echo "   Updating to shared mailbox configuration...\n";

        // Get your app password input
        echo "\nEnter your Gmail App Password for support@softart.bg: ";
        $appPassword = trim(fgets(STDIN));

        if (empty($appPassword)) {
            echo "❌ App password is required. Exiting.\n";
            exit(1);
        }

        // Use Laravel's encrypt function by loading the app
        $encryptedPassword = null;
        try {
            // Try to use Laravel encryption if available
            require_once 'vendor/autoload.php';
            $app = require_once 'bootstrap/app.php';
            $encryptedPassword = encrypt($appPassword);
        } catch (Exception $e) {
            // Fallback to base64 encoding (not as secure, but functional)
            $encryptedPassword = base64_encode($appPassword);
            echo "⚠️  Using fallback encryption (consider fixing Laravel encryption)\n";
        }

        // Update existing record
        $updateSql = "
            UPDATE email_accounts SET
                name = ?,
                account_type = 'shared_mailbox',
                imap_host = 'imap.gmail.com',
                imap_port = 993,
                imap_username = 'support@softart.bg',
                imap_password_encrypted = ?,
                imap_use_ssl = true,
                smtp_host = 'smtp.gmail.com',
                smtp_port = 587,
                smtp_username = 'support@softart.bg',
                smtp_password_encrypted = ?,
                smtp_use_tls = true,
                auto_create_tickets = true,
                default_ticket_priority = 'medium',
                routing_rules = ?,
                signature_template = ?,
                is_active = true
            WHERE email_address = 'support@softart.bg'
        ";

        $routingRules = json_encode([
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
                'subject_contains' => ['bug', 'error', 'crash', 'not working', 'broken'],
                'priority' => 'high'
            ]
        ]);

        $signatureTemplate = "Best regards,\n{agent_name}\n{department_name}\nSoftArt Support Team\n\n--\nThis email was sent from our support system.\nPlease reply to this email for fastest assistance.\n\nSoftArt - Building Tomorrow's Software Today";

        $stmt = $pdo->prepare($updateSql);
        $stmt->execute([
            'SoftArt Support',
            $encryptedPassword,
            $encryptedPassword,
            $routingRules,
            $signatureTemplate
        ]);

        echo "✅ Updated mailbox to shared mailbox configuration\n";

    } else {
        echo "📧 Creating new shared mailbox: support@softart.bg\n";

        // Get your app password
        echo "\nTo set up support@softart.bg, I need your Gmail App Password.\n";
        echo "If you don't have one:\n";
        echo "1. Go to myaccount.google.com\n";
        echo "2. Security > 2-Step Verification > App passwords\n";
        echo "3. Generate password for 'AidlY Support'\n";
        echo "\nEnter your Gmail App Password: ";

        $appPassword = trim(fgets(STDIN));

        if (empty($appPassword)) {
            echo "❌ App password is required. Exiting.\n";
            exit(1);
        }

        // Generate UUID for the mailbox
        function generateUuid() {
            return sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
        }

        $mailboxId = generateUuid();

        // Encrypt password
        $encryptedPassword = null;
        try {
            require_once 'vendor/autoload.php';
            $app = require_once 'bootstrap/app.php';
            $encryptedPassword = encrypt($appPassword);
        } catch (Exception $e) {
            $encryptedPassword = base64_encode($appPassword);
            echo "⚠️  Using fallback encryption\n";
        }

        // Insert new shared mailbox
        $insertSql = "
            INSERT INTO email_accounts (
                id, name, email_address, account_type,
                imap_host, imap_port, imap_username, imap_password_encrypted, imap_use_ssl,
                smtp_host, smtp_port, smtp_username, smtp_password_encrypted, smtp_use_tls,
                auto_create_tickets, default_ticket_priority,
                routing_rules, signature_template, is_active, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";

        $routingRules = json_encode([
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
                'subject_contains' => ['bug', 'error', 'crash', 'not working', 'broken'],
                'priority' => 'high'
            ],
            [
                'name' => 'Feature Requests',
                'subject_contains' => ['feature', 'request', 'enhancement', 'suggestion'],
                'priority' => 'low'
            ]
        ]);

        $signatureTemplate = "Best regards,\n{agent_name}\n{department_name}\nSoftArt Support Team\n\n--\nThis email was sent from our support system.\nPlease reply to this email for fastest assistance.\n\nSoftArt - Building Tomorrow's Software Today";

        $stmt = $pdo->prepare($insertSql);
        $stmt->execute([
            $mailboxId,
            'SoftArt Support',
            'support@softart.bg',
            'shared_mailbox',
            'imap.gmail.com',
            993,
            'support@softart.bg',
            $encryptedPassword,
            true,
            'smtp.gmail.com',
            587,
            'support@softart.bg',
            $encryptedPassword,
            true,
            true,
            'medium',
            $routingRules,
            $signatureTemplate,
            true,
            date('Y-m-d H:i:s')
        ]);

        echo "✅ Created shared mailbox successfully!\n";
    }

    echo "\n📋 Configuration Summary:\n";
    echo "========================\n";
    echo "Email: support@softart.bg\n";
    echo "Type: Shared Mailbox\n";
    echo "IMAP: imap.gmail.com:993 (SSL)\n";
    echo "SMTP: smtp.gmail.com:587 (TLS)\n";
    echo "Auto-create tickets: Yes\n";
    echo "Default priority: Medium\n";
    echo "Routing rules: Configured for urgent/high/bug priority\n";
    echo "Signature: SoftArt branded\n";

    echo "\n🧪 Test the configuration:\n";
    echo "=========================\n";
    echo "1. Test connection: php artisan mailbox:process-shared --test-connections\n";
    echo "2. Dry run test: php artisan mailbox:process-shared --dry-run --detailed\n";
    echo "3. Process specific mailbox: php artisan mailbox:process-shared --mailbox=support@softart.bg\n";

    echo "\n✅ SoftArt support mailbox configured successfully!\n";
    echo "========================================\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}