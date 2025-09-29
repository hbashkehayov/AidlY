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
        Schema::table('tickets', function (Blueprint $table) {
            // Language detection field
            $table->string('detected_language', 10)->nullable()->after('ai_webhook_url');
            $table->decimal('language_confidence_score', 3, 2)->nullable()->after('detected_language');

            // Sentiment analysis fields
            $table->string('sentiment_score', 20)->nullable()->after('language_confidence_score'); // positive, negative, neutral
            $table->decimal('sentiment_confidence', 3, 2)->nullable()->after('sentiment_score');

            // AI categorization enhancements
            $table->json('ai_category_suggestions')->nullable()->after('sentiment_confidence');
            $table->json('ai_tag_suggestions')->nullable()->after('ai_category_suggestions');

            // Response suggestion metadata
            $table->json('ai_response_suggestions')->nullable()->after('ai_tag_suggestions');
            $table->integer('ai_estimated_resolution_time')->nullable()->after('ai_response_suggestions'); // in minutes

            // AI processing metadata
            $table->json('ai_processing_metadata')->nullable()->after('ai_estimated_resolution_time');
            $table->timestamp('ai_last_processed_at')->nullable()->after('ai_processing_metadata');
            $table->string('ai_processing_status', 50)->nullable()->after('ai_last_processed_at'); // pending, processing, completed, failed

            // Feature flags for this ticket
            $table->boolean('ai_categorization_enabled')->default(false)->after('ai_processing_status');
            $table->boolean('ai_suggestions_enabled')->default(false)->after('ai_categorization_enabled');
            $table->boolean('ai_sentiment_analysis_enabled')->default(false)->after('ai_suggestions_enabled');
        });

        // Add indexes for AI fields
        DB::statement('CREATE INDEX idx_tickets_detected_language ON tickets(detected_language)');
        DB::statement('CREATE INDEX idx_tickets_sentiment_score ON tickets(sentiment_score)');
        DB::statement('CREATE INDEX idx_tickets_ai_processing_status ON tickets(ai_processing_status)');
        DB::statement('CREATE INDEX idx_tickets_ai_last_processed_at ON tickets(ai_last_processed_at)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes
        DB::statement('DROP INDEX IF EXISTS idx_tickets_detected_language');
        DB::statement('DROP INDEX IF EXISTS idx_tickets_sentiment_score');
        DB::statement('DROP INDEX IF EXISTS idx_tickets_ai_processing_status');
        DB::statement('DROP INDEX IF EXISTS idx_tickets_ai_last_processed_at');

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn([
                'detected_language',
                'language_confidence_score',
                'sentiment_score',
                'sentiment_confidence',
                'ai_category_suggestions',
                'ai_tag_suggestions',
                'ai_response_suggestions',
                'ai_estimated_resolution_time',
                'ai_processing_metadata',
                'ai_last_processed_at',
                'ai_processing_status',
                'ai_categorization_enabled',
                'ai_suggestions_enabled',
                'ai_sentiment_analysis_enabled'
            ]);
        });
    }
};