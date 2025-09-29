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
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type'); // ticket_update, comment_added, assignment_change, etc.
            $table->string('channel'); // email, in_app, push, sms
            $table->uuid('notifiable_id'); // user_id or client_id
            $table->string('notifiable_type'); // 'user' or 'client'

            // Related entities
            $table->uuid('ticket_id')->nullable();
            $table->uuid('comment_id')->nullable();
            $table->uuid('triggered_by')->nullable();

            // Notification content
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable(); // Additional data
            $table->string('action_url')->nullable();
            $table->string('action_text')->nullable();

            // Status tracking
            $table->enum('status', ['pending', 'sent', 'delivered', 'read', 'failed'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);

            // Priority and grouping
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->string('group_key')->nullable()->index(); // For grouping related notifications

            $table->timestamps();

            // Indexes
            $table->index(['notifiable_id', 'notifiable_type']);
            $table->index('ticket_id');
            $table->index('status');
            $table->index('channel');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};