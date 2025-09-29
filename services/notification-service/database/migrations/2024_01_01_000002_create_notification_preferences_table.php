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
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->uuid('client_id')->nullable();

            // Channel preferences
            $table->boolean('email_enabled')->default(true);
            $table->boolean('in_app_enabled')->default(true);
            $table->boolean('push_enabled')->default(false);
            $table->boolean('sms_enabled')->default(false);

            // Event preferences (what to notify about)
            $table->json('events')->default('{}'); // JSON object with event types and their settings
            /*
            Example events JSON:
            {
                "ticket_assigned": {"email": true, "in_app": true, "push": false},
                "ticket_updated": {"email": false, "in_app": true, "push": false},
                "comment_added": {"email": true, "in_app": true, "push": true},
                "ticket_resolved": {"email": true, "in_app": false, "push": false},
                "mention": {"email": true, "in_app": true, "push": true},
                "sla_breach": {"email": true, "in_app": true, "push": true}
            }
            */

            // Frequency settings
            $table->enum('email_frequency', ['immediate', 'hourly', 'daily', 'weekly'])->default('immediate');
            $table->boolean('digest_enabled')->default(false);
            $table->time('digest_time')->default('09:00:00');
            $table->json('digest_days')->nullable(); // Array of weekday numbers [1,2,3,4,5] for Mon-Fri

            // Quiet hours
            $table->boolean('quiet_hours_enabled')->default(false);
            $table->time('quiet_hours_start')->default('22:00:00');
            $table->time('quiet_hours_end')->default('08:00:00');
            $table->string('timezone')->default('UTC');

            // Do not disturb
            $table->boolean('dnd_enabled')->default(false);
            $table->timestamp('dnd_until')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->index('client_id');

            // Ensure either user_id or client_id is set, not both
            $table->unique(['user_id', 'client_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};