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
            // Email content fields
            $table->text('body_html')->nullable()->after('content');
            $table->text('body_plain')->nullable()->after('body_html');

            // Email metadata for Gmail-style rendering
            $table->string('from_address')->nullable()->after('body_plain');
            $table->jsonb('to_addresses')->nullable()->after('from_address');
            $table->jsonb('cc_addresses')->nullable()->after('to_addresses');
            $table->string('subject')->nullable()->after('cc_addresses');
            $table->jsonb('headers')->nullable()->after('subject');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->dropColumn([
                'body_html',
                'body_plain',
                'from_address',
                'to_addresses',
                'cc_addresses',
                'subject',
                'headers'
            ]);
        });
    }
};
