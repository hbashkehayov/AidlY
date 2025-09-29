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
        // Create ticket status enum type if not exists
        DB::statement("DO $$ BEGIN
            CREATE TYPE ticket_status AS ENUM (
                'new',
                'open',
                'pending',
                'on_hold',
                'resolved',
                'closed',
                'cancelled'
            );
        EXCEPTION
            WHEN duplicate_object THEN null;
        END $$;");

        // Create ticket priority enum type if not exists
        DB::statement("DO $$ BEGIN
            CREATE TYPE ticket_priority AS ENUM (
                'low',
                'medium',
                'high',
                'urgent'
            );
        EXCEPTION
            WHEN duplicate_object THEN null;
        END $$;");

        // Create ticket source enum type if not exists
        DB::statement("DO $$ BEGIN
            CREATE TYPE ticket_source AS ENUM (
                'email',
                'web_form',
                'chat',
                'phone',
                'social_media',
                'api',
                'internal'
            );
        EXCEPTION
            WHEN duplicate_object THEN null;
        END $$;");

        Schema::create('tickets', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('ticket_number', 50)->unique();
            $table->string('subject', 500);
            $table->text('description');

            // Use custom enum types with proper casting
            DB::statement('ALTER TABLE tickets ADD COLUMN status ticket_status DEFAULT \'new\'');
            DB::statement('ALTER TABLE tickets ADD COLUMN priority ticket_priority DEFAULT \'medium\'');
            DB::statement('ALTER TABLE tickets ADD COLUMN source ticket_source NOT NULL');

            $table->uuid('client_id');
            $table->uuid('assigned_agent_id')->nullable();
            $table->uuid('assigned_department_id')->nullable();
            $table->uuid('category_id')->nullable();

            // SLA Fields
            $table->uuid('sla_policy_id')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('first_response_due_at')->nullable();
            $table->timestamp('resolution_due_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            // AI Integration Fields (for future use)
            $table->text('ai_suggestion')->nullable();
            $table->decimal('ai_confidence_score', 3, 2)->nullable();
            $table->uuid('ai_suggested_category_id')->nullable();
            DB::statement('ALTER TABLE tickets ADD COLUMN ai_suggested_priority ticket_priority');
            $table->timestamp('ai_processed_at')->nullable();
            $table->string('ai_provider', 50)->nullable();
            $table->string('ai_model_version', 50)->nullable();
            $table->text('ai_webhook_url')->nullable();

            // Metadata
            $table->json('tags')->nullable();
            $table->jsonb('custom_fields')->nullable();
            $table->boolean('is_spam')->default(false);
            $table->boolean('is_deleted')->default(false);

            $table->timestamps();

            // Indexes for better performance
            $table->index('status');
            $table->index('priority');
            $table->index('client_id');
            $table->index('assigned_agent_id');
            $table->index('assigned_department_id');
            $table->index('category_id');
            $table->index('created_at');
            $table->index(['status', 'assigned_agent_id']);
            $table->index(['client_id', 'created_at']);
        });

        // Add auto-increment ticket number generation
        DB::statement('CREATE SEQUENCE IF NOT EXISTS ticket_number_seq START 1000');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
        DB::statement('DROP SEQUENCE IF EXISTS ticket_number_seq');
        DB::statement('DROP TYPE IF EXISTS ticket_status');
        DB::statement('DROP TYPE IF EXISTS ticket_priority');
        DB::statement('DROP TYPE IF EXISTS ticket_source');
    }
};