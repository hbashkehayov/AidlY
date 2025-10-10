<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This pivot table tracks which users have read which comments.
     * Much simpler than trying to use boolean flags.
     */
    public function up(): void
    {
        Schema::create('comment_reads', function (Blueprint $table) {
            $table->uuid('comment_id');
            $table->uuid('user_id');
            $table->timestamp('read_at')->useCurrent();

            $table->primary(['comment_id', 'user_id']);

            $table->foreign('comment_id')
                  ->references('id')
                  ->on('ticket_comments')
                  ->onDelete('cascade');

            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');

            // Index for efficient queries
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comment_reads');
    }
};
