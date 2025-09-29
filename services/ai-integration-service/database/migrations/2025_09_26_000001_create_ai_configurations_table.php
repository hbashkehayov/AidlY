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
        // Create AI provider enum
        DB::statement("DO $$ BEGIN
            CREATE TYPE ai_provider AS ENUM (
                'openai',
                'claude',
                'gemini',
                'custom',
                'n8n'
            );
        EXCEPTION
            WHEN duplicate_object THEN null;
        END $$;");

        Schema::create('ai_configurations', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('name');

            // Use custom enum type for provider
            DB::statement('ALTER TABLE ai_configurations ADD COLUMN provider ai_provider NOT NULL');

            $table->text('api_endpoint')->nullable();
            $table->text('api_key_encrypted')->nullable();
            $table->string('webhook_secret')->nullable();

            // Model and processing settings
            $table->jsonb('model_settings')->nullable();
            $table->jsonb('retry_policy')->nullable();
            $table->integer('timeout_seconds')->default(30);

            // Feature toggles
            $table->boolean('enable_categorization')->default(false);
            $table->boolean('enable_suggestions')->default(false);
            $table->boolean('enable_sentiment')->default(false);
            $table->boolean('enable_auto_assign')->default(false);

            // Status
            $table->boolean('is_active')->default(true);

            // Usage tracking
            $table->integer('requests_count')->default(0);
            $table->integer('success_count')->default(0);
            $table->integer('error_count')->default(0);
            $table->timestamp('last_used_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('provider');
            $table->index('is_active');
            $table->index(['provider', 'is_active']);
            $table->index('last_used_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_configurations');
        DB::statement('DROP TYPE IF EXISTS ai_provider');
    }
};