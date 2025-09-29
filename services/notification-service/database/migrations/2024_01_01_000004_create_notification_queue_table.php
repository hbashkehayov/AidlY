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
        Schema::create('notification_queue', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('notification_id')->nullable();

            // Notifiable entity
            $table->string('notifiable_type'); // user, client, etc.
            $table->uuid('notifiable_id');

            // Notification details
            $table->string('type'); // ticket_created, ticket_assigned, etc.
            $table->string('channel'); // email, in_app, push, sms, etc.
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable(); // Additional data

            // Queue management
            $table->enum('status', ['pending', 'processing', 'sent', 'failed'])->default('pending');
            $table->integer('priority')->default(0); // Higher numbers = higher priority
            $table->integer('attempts')->default(0);
            $table->text('error')->nullable();

            // Scheduling
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            // Indexes for performance
            $table->index(['status', 'scheduled_at']);
            $table->index(['notifiable_type', 'notifiable_id']);
            $table->index(['type', 'channel']);
            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_queue');
    }
};