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
        Schema::table('ticket_comments', function (Blueprint $table) {
            // JSONB field to store array of user IDs who can see this internal note
            // Can contain 'all' as a special value to indicate all agents can see it
            $table->jsonb('visible_to_agents')->nullable()->after('is_internal_note');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->dropColumn('visible_to_agents');
        });
    }
};
