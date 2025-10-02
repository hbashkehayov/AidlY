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
        Schema::create('blocked_email_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id')->nullable();
            $table->string('email_address')->index();
            $table->string('client_name')->nullable();
            $table->text('subject');
            $table->uuid('email_queue_id')->nullable();
            $table->string('message_id')->nullable();
            $table->boolean('notification_sent')->default(false);
            $table->text('block_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('attempted_at')->useCurrent();
            $table->timestamps();

            // Foreign key (if client service shares same database)
            // $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');

            // Indexes for performance
            $table->index('attempted_at');
            $table->index(['email_address', 'attempted_at']);
            $table->index('notification_sent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blocked_email_attempts');
    }
};
