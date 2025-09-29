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
        Schema::create('ticket_comments', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('ticket_id');
            $table->uuid('user_id')->nullable();
            $table->uuid('client_id')->nullable();
            $table->text('content');
            $table->boolean('is_internal_note')->default(false);
            $table->boolean('is_ai_generated')->default(false);
            $table->text('ai_suggestion_used')->nullable();
            $table->jsonb('attachments')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('ticket_id')->references('id')->on('tickets')->onDelete('cascade');

            // Indexes
            $table->index('ticket_id');
            $table->index('user_id');
            $table->index('client_id');
            $table->index('created_at');
            $table->index(['ticket_id', 'created_at']);

            // Check constraint to ensure either user_id or client_id is set, but not both
            // DB::statement('ALTER TABLE ticket_comments ADD CONSTRAINT chk_comment_author
            //     CHECK ((user_id IS NOT NULL AND client_id IS NULL) OR
            //            (user_id IS NULL AND client_id IS NOT NULL))');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_comments');
    }
};