<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add shared mailbox support to email_accounts table
        Schema::table('email_accounts', function (Blueprint $table) {
            // Add account type to distinguish shared mailboxes
            $table->enum('account_type', ['shared_mailbox', 'personal'])
                  ->default('shared_mailbox')
                  ->after('email_address')
                  ->comment('Type of email account - shared_mailbox for centralized processing');

            // Add routing rules for shared mailboxes (JSON)
            $table->json('routing_rules')
                  ->nullable()
                  ->after('default_category_id')
                  ->comment('JSON rules for routing emails to departments/categories');

            // Add signature template for agent replies
            $table->text('signature_template')
                  ->nullable()
                  ->after('routing_rules')
                  ->comment('Template for agent signatures with placeholders');

            // Add index for account type
            $table->index(['account_type', 'is_active'], 'idx_email_accounts_type_active');
        });

        // Add shared mailbox context to email_queue table
        Schema::table('email_queue', function (Blueprint $table) {
            // Track original recipient (which shared mailbox received this)
            $table->string('original_recipient', 255)
                  ->nullable()
                  ->after('cc_addresses')
                  ->comment('Original shared mailbox that received this email');

            // Track sender name separately
            $table->string('from_name', 255)
                  ->nullable()
                  ->after('from_address')
                  ->comment('Sender display name');

            // Add mailbox type for easier querying
            $table->enum('mailbox_type', ['shared', 'personal'])
                  ->default('shared')
                  ->after('retry_count')
                  ->comment('Type of mailbox that processed this email');

            // Routing results from shared mailbox rules
            $table->uuid('routed_department_id')
                  ->nullable()
                  ->after('mailbox_type')
                  ->comment('Department ID assigned by routing rules');

            $table->uuid('routed_category_id')
                  ->nullable()
                  ->after('routed_department_id')
                  ->comment('Category ID assigned by routing rules');

            $table->enum('routed_priority', ['low', 'medium', 'high', 'urgent'])
                  ->nullable()
                  ->after('routed_category_id')
                  ->comment('Priority assigned by routing rules');

            $table->string('routing_reason', 255)
                  ->nullable()
                  ->after('routed_priority')
                  ->comment('Reason for routing assignment');

            // Enhanced content field (processed from HTML/plain)
            $table->text('content')
                  ->nullable()
                  ->after('body_html')
                  ->comment('Processed email content for ticket creation');

            // Add indexes for shared mailbox processing
            $table->index(['mailbox_type', 'is_processed'], 'idx_email_queue_mailbox_processed');
            $table->index(['original_recipient', 'received_at'], 'idx_email_queue_recipient_date');
            $table->index(['from_address', 'received_at'], 'idx_email_queue_sender_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_accounts', function (Blueprint $table) {
            $table->dropIndex('idx_email_accounts_type_active');
            $table->dropColumn(['account_type', 'routing_rules', 'signature_template']);
        });

        Schema::table('email_queue', function (Blueprint $table) {
            $table->dropIndex('idx_email_queue_mailbox_processed');
            $table->dropIndex('idx_email_queue_recipient_date');
            $table->dropIndex('idx_email_queue_sender_date');

            $table->dropColumn([
                'original_recipient',
                'from_name',
                'mailbox_type',
                'routed_department_id',
                'routed_category_id',
                'routed_priority',
                'routing_reason',
                'content'
            ]);
        });
    }
};