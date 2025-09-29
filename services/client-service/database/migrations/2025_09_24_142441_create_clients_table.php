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
        Schema::create('clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email')->unique();
            $table->string('name')->nullable();
            $table->string('company')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('mobile', 50)->nullable();

            // Additional Info
            $table->text('avatar_url')->nullable();
            $table->string('timezone', 50)->nullable();
            $table->string('language', 10)->default('en');

            // Address
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('postal_code', 20)->nullable();

            // CRM Fields
            $table->string('crm_id')->nullable();
            $table->string('crm_type', 50)->nullable();
            $table->integer('lead_score')->nullable();
            $table->decimal('lifetime_value', 12, 2)->nullable();

            // Preferences (stored as JSON)
            $table->json('notification_preferences')->nullable();
            $table->json('custom_fields')->nullable();
            $table->json('tags')->nullable();

            // Status
            $table->boolean('is_vip')->default(false);
            $table->boolean('is_blocked')->default(false);
            $table->boolean('is_deleted')->default(false);

            // Timestamps
            $table->timestamp('first_contact_at')->nullable();
            $table->timestamp('last_contact_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('email');
            $table->index('company');
            $table->index('is_vip');
            $table->index('is_blocked');
            $table->index('is_deleted');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
