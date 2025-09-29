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
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('event_type'); // ticket_created, ticket_assigned, etc.
            $table->string('channel'); // email, in_app, push, sms
            $table->string('locale')->default('en');

            // Template content
            $table->string('subject')->nullable(); // For email
            $table->text('title_template');
            $table->text('message_template');
            $table->text('html_template')->nullable(); // For email HTML
            $table->json('variables'); // List of available variables for this template

            // Settings
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false); // System templates can't be deleted
            $table->integer('priority')->default(0); // For template selection order

            $table->timestamps();

            // Indexes
            $table->index(['event_type', 'channel', 'locale']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};