-- Sprint 4.2: Add missing AI fields to tickets table
-- This migration adds the enhanced AI integration fields required for Sprint 4.2

\c aidly;

-- Add missing AI fields to tickets table
ALTER TABLE tickets
ADD COLUMN IF NOT EXISTS detected_language VARCHAR(10) DEFAULT 'en',
ADD COLUMN IF NOT EXISTS language_confidence_score DECIMAL(3,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS sentiment_score DECIMAL(4,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS sentiment_confidence DECIMAL(3,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS ai_category_suggestions JSONB DEFAULT '[]'::jsonb,
ADD COLUMN IF NOT EXISTS ai_tag_suggestions JSONB DEFAULT '[]'::jsonb,
ADD COLUMN IF NOT EXISTS ai_response_suggestions JSONB DEFAULT '[]'::jsonb,
ADD COLUMN IF NOT EXISTS ai_estimated_resolution_time INTEGER DEFAULT NULL,
ADD COLUMN IF NOT EXISTS ai_processing_metadata JSONB DEFAULT '{}'::jsonb,
ADD COLUMN IF NOT EXISTS ai_processing_status VARCHAR(50) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS ai_last_processed_at TIMESTAMP DEFAULT NULL,
ADD COLUMN IF NOT EXISTS ai_categorization_enabled BOOLEAN DEFAULT false,
ADD COLUMN IF NOT EXISTS ai_suggestions_enabled BOOLEAN DEFAULT false,
ADD COLUMN IF NOT EXISTS ai_sentiment_analysis_enabled BOOLEAN DEFAULT false;

-- Update existing AI fields to match Sprint 4.2 specifications
ALTER TABLE tickets ALTER COLUMN sentiment_score TYPE DECIMAL(4,2);

-- Add comments to document AI fields
COMMENT ON COLUMN tickets.detected_language IS 'Auto-detected language of the ticket (ISO 639-1 code)';
COMMENT ON COLUMN tickets.language_confidence_score IS 'Confidence score for language detection (0.00-1.00)';
COMMENT ON COLUMN tickets.sentiment_score IS 'Sentiment analysis score (-1.00 to 1.00, negative to positive)';
COMMENT ON COLUMN tickets.sentiment_confidence IS 'Confidence score for sentiment analysis (0.00-1.00)';
COMMENT ON COLUMN tickets.ai_category_suggestions IS 'Array of AI-suggested categories with confidence scores';
COMMENT ON COLUMN tickets.ai_tag_suggestions IS 'Array of AI-suggested tags';
COMMENT ON COLUMN tickets.ai_response_suggestions IS 'Array of AI-generated response suggestions';
COMMENT ON COLUMN tickets.ai_estimated_resolution_time IS 'AI estimated resolution time in minutes';
COMMENT ON COLUMN tickets.ai_processing_metadata IS 'Additional AI processing information and metadata';
COMMENT ON COLUMN tickets.ai_processing_status IS 'Current AI processing status (pending, processing, completed, failed)';
COMMENT ON COLUMN tickets.ai_last_processed_at IS 'Timestamp of last AI processing attempt';
COMMENT ON COLUMN tickets.ai_categorization_enabled IS 'Enable AI auto-categorization for this ticket';
COMMENT ON COLUMN tickets.ai_suggestions_enabled IS 'Enable AI response suggestions for this ticket';
COMMENT ON COLUMN tickets.ai_sentiment_analysis_enabled IS 'Enable AI sentiment analysis for this ticket';

-- Create additional indexes for AI fields
CREATE INDEX IF NOT EXISTS idx_tickets_ai_processing_status ON tickets(ai_processing_status) WHERE ai_processing_status IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_tickets_ai_enabled_features ON tickets(ai_categorization_enabled, ai_suggestions_enabled, ai_sentiment_analysis_enabled);
CREATE INDEX IF NOT EXISTS idx_tickets_sentiment_score ON tickets(sentiment_score) WHERE sentiment_score IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_tickets_detected_language ON tickets(detected_language) WHERE detected_language IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_tickets_ai_last_processed ON tickets(ai_last_processed_at) WHERE ai_last_processed_at IS NOT NULL;

-- Create feature_flags table if it doesn't exist
CREATE TABLE IF NOT EXISTS feature_flags (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    key VARCHAR(100) UNIQUE NOT NULL,
    value TEXT NOT NULL DEFAULT 'false',
    type VARCHAR(50) NOT NULL DEFAULT 'boolean', -- boolean, string, number, json
    description TEXT,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add trigger for feature_flags updated_at
CREATE OR REPLACE FUNCTION update_feature_flags_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

DROP TRIGGER IF EXISTS update_feature_flags_updated_at ON feature_flags;
CREATE TRIGGER update_feature_flags_updated_at
    BEFORE UPDATE ON feature_flags
    FOR EACH ROW
    EXECUTE FUNCTION update_feature_flags_updated_at();

-- Insert default AI feature flags
INSERT INTO feature_flags (name, key, value, type, description, is_active) VALUES
('AI Auto Categorization', 'ai_auto_categorization', 'false', 'boolean', 'Automatically categorize tickets using AI', true),
('AI Response Suggestions', 'ai_response_suggestions', 'true', 'boolean', 'Show AI-generated response suggestions to agents', true),
('AI Sentiment Analysis', 'ai_sentiment_analysis', 'true', 'boolean', 'Analyze customer sentiment in tickets', true),
('AI Priority Detection', 'ai_priority_detection', 'false', 'boolean', 'Automatically detect ticket priority using AI', true),
('AI Language Detection', 'ai_language_detection', 'true', 'boolean', 'Automatically detect ticket language', true),
('AI Confidence Threshold', 'ai_confidence_threshold', '0.8', 'number', 'Minimum confidence score for AI suggestions', true),
('AI Processing Timeout', 'ai_processing_timeout', '30', 'number', 'AI processing timeout in seconds', true),
('AI Max Suggestions', 'ai_max_suggestions', '3', 'number', 'Maximum number of AI suggestions to show', true)
ON CONFLICT (key) DO UPDATE SET
    value = EXCLUDED.value,
    description = EXCLUDED.description,
    updated_at = CURRENT_TIMESTAMP;

-- Update existing test tickets with AI fields
UPDATE tickets SET
    ai_categorization_enabled = true,
    ai_suggestions_enabled = true,
    ai_sentiment_analysis_enabled = true,
    detected_language = 'en',
    language_confidence_score = 0.95,
    sentiment_score = CASE
        WHEN subject ILIKE '%problem%' OR subject ILIKE '%issue%' OR subject ILIKE '%error%' THEN -0.3
        WHEN subject ILIKE '%thank%' OR subject ILIKE '%great%' THEN 0.7
        ELSE 0.1
    END,
    sentiment_confidence = 0.85,
    ai_category_suggestions = '[
        {"category": "Technical Support", "confidence": 0.92},
        {"category": "General Inquiry", "confidence": 0.75}
    ]'::jsonb,
    ai_tag_suggestions = '["login", "authentication", "account"]'::jsonb,
    ai_response_suggestions = '[
        {
            "suggestion": "Hello! I understand you''re having trouble with your account. Let me help you resolve this issue.",
            "confidence": 0.90,
            "type": "greeting"
        },
        {
            "suggestion": "I''d be happy to assist you with this issue. Can you please provide more details about what exactly is happening?",
            "confidence": 0.85,
            "type": "clarification"
        }
    ]'::jsonb,
    ai_estimated_resolution_time = CASE priority
        WHEN 'urgent' THEN 15
        WHEN 'high' THEN 60
        WHEN 'medium' THEN 240
        ELSE 480
    END,
    ai_processing_metadata = jsonb_build_object(
        'provider', 'test',
        'model', 'test-model-v1',
        'processed_at', NOW()::text,
        'processing_time_ms', 1250
    ),
    ai_processing_status = 'completed',
    ai_last_processed_at = NOW()
WHERE ticket_number LIKE 'TKT-%';

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_feature_flags_key ON feature_flags(key) WHERE is_active = true;
CREATE INDEX IF NOT EXISTS idx_feature_flags_active ON feature_flags(is_active);

-- Verify the changes
SELECT 'AI fields migration completed successfully!' as status;
SELECT 'Enhanced AI fields count: ' || COUNT(*) as ai_fields_added
FROM information_schema.columns
WHERE table_name = 'tickets'
AND column_name LIKE '%ai_%'
OR column_name LIKE '%sentiment%'
OR column_name LIKE '%language%';

SELECT 'Feature flags created: ' || COUNT(*) as feature_flags_count FROM feature_flags;

COMMIT;