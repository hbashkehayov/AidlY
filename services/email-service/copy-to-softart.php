<?php

/**
 * Copy existing Gmail credentials to create support@softart.bg shared mailbox
 */

echo "========================================\n";
echo "Copy Gmail Credentials to SoftArt Support\n";
echo "========================================\n";

$dsn = 'pgsql:host=localhost;port=5432;dbname=aidly';
$pdo = new PDO($dsn, 'aidly_user', 'aidly_secret_2024', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

try {
    // Get the existing Gmail account credentials
    $stmt = $pdo->prepare("SELECT * FROM email_accounts WHERE email_address = ?");
    $stmt->execute(['hristiyan.bashkehayov@gmail.com']);
    $sourceAccount = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sourceAccount) {
        echo "❌ Source Gmail account not found\n";
        exit(1);
    }

    echo "✅ Found source account: {$sourceAccount['email_address']}\n";

    // Check if target already exists
    $stmt = $pdo->prepare("SELECT id FROM email_accounts WHERE email_address = ?");
    $stmt->execute(['support@softart.bg']);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        echo "⚠️  Target mailbox support@softart.bg already exists\n";
        echo "   Updating with source credentials...\n";

        // Update existing
        $updateSql = "
            UPDATE email_accounts SET
                name = 'SoftArt Support',
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
            ['name' => 'Urgent Issues', 'subject_contains' => ['urgent', 'critical', 'emergency'], 'priority' => 'urgent'],
            ['name' => 'Bug Reports', 'subject_contains' => ['bug', 'error', 'crash', 'broken'], 'priority' => 'high'],
            ['name' => 'Feature Requests', 'subject_contains' => ['feature', 'enhancement', 'request'], 'priority' => 'low']
        ]);

        $signature = "Best regards,\n{agent_name}\nSoftArt Support Team\n\n--\nPlease reply to this email for fastest assistance.\nSoftArt - Building Tomorrow's Software Today";

        $stmt = $pdo->prepare($updateSql);
        $stmt->execute([
            $sourceAccount['imap_password_encrypted'],
            $sourceAccount['smtp_password_encrypted'],
            $routingRules,
            $signature
        ]);

        echo "✅ Updated support@softart.bg mailbox\n";

    } else {
        echo "📧 Creating support@softart.bg with copied credentials...\n";

        // Generate new UUID
        function generateUuid() {
            return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
        }

        $newId = generateUuid();

        // Insert new mailbox with copied credentials
        $insertSql = "
            INSERT INTO email_accounts (
                id, name, email_address, account_type,
                imap_host, imap_port, imap_username, imap_password_encrypted, imap_use_ssl,
                smtp_host, smtp_port, smtp_username, smtp_password_encrypted, smtp_use_tls,
                auto_create_tickets, default_ticket_priority, routing_rules, signature_template,
                is_active, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";

        $routingRules = json_encode([
            ['name' => 'Urgent Issues', 'subject_contains' => ['urgent', 'critical', 'emergency'], 'priority' => 'urgent'],
            ['name' => 'Bug Reports', 'subject_contains' => ['bug', 'error', 'crash', 'broken'], 'priority' => 'high'],
            ['name' => 'Feature Requests', 'subject_contains' => ['feature', 'enhancement', 'request'], 'priority' => 'low']
        ]);

        $signature = "Best regards,\n{agent_name}\nSoftArt Support Team\n\n--\nPlease reply to this email for fastest assistance.\nSoftArt - Building Tomorrow's Software Today";

        $stmt = $pdo->prepare($insertSql);
        $stmt->execute([
            $newId, 'SoftArt Support', 'support@softart.bg', 'shared_mailbox',
            'imap.gmail.com', 993, 'support@softart.bg', $sourceAccount['imap_password_encrypted'], true,
            'smtp.gmail.com', 587, 'support@softart.bg', $sourceAccount['smtp_password_encrypted'], true,
            true, 'medium', $routingRules, $signature, true, date('Y-m-d H:i:s')
        ]);

        echo "✅ Created support@softart.bg shared mailbox\n";
    }

    echo "\n📧 SoftArt Support Mailbox Configuration:\n";
    echo "=======================================\n";
    echo "Email: support@softart.bg\n";
    echo "Type: Shared Mailbox\n";
    echo "IMAP/SMTP: Gmail servers (credentials copied)\n";
    echo "Auto-create tickets: Yes\n";
    echo "Priority routing: 3 rules configured\n";
    echo "Agent signatures: SoftArt branded\n";

    echo "\n🧪 Next Steps:\n";
    echo "==============\n";
    echo "1. Test connection: php artisan mailbox:process-shared --test-connections\n";
    echo "2. Test specific mailbox: php artisan mailbox:process-shared --mailbox=support@softart.bg --dry-run\n";
    echo "3. Setup cron: ./setup-cron.sh\n";

    echo "\n✅ Setup complete!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}