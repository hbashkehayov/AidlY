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
            // Email metadata fields for Gmail-style rendering
            $table->string('from_address')->nullable()->after('content');
            $table->json('to_addresses')->nullable()->after('from_address');
            $table->json('cc_addresses')->nullable()->after('to_addresses');
            $table->string('subject')->nullable()->after('cc_addresses');
            $table->text('body_html')->nullable()->after('subject');
            $table->text('body_plain')->nullable()->after('body_html');
            $table->json('headers')->nullable()->after('body_plain');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->dropColumn([
                'from_address',
                'to_addresses',
                'cc_addresses',
                'subject',
                'body_html',
                'body_plain',
                'headers',
            ]);
        });
    }
};