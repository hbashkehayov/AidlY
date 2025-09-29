-- Sprint 4.2 Test Data Setup
-- Creates necessary test data and configurations for AI integration testing

-- Connect to the database
\c aidly;

-- Create test AI configurations
INSERT INTO ai_configurations (id, name, provider, api_endpoint, model_settings, enable_categorization, enable_suggestions, enable_sentiment, is_active, created_at, updated_at) VALUES
(gen_random_uuid(), 'OpenAI GPT-4 Configuration', 'openai', 'https://api.openai.com/v1/', '{"model": "gpt-4", "temperature": 0.7, "max_tokens": 1000}', true, true, true, false, NOW(), NOW()),
(gen_random_uuid(), 'Anthropic Claude Configuration', 'claude', 'https://api.anthropic.com/v1/', '{"model": "claude-3-sonnet", "temperature": 0.7, "max_tokens": 1000}', true, true, true, false, NOW(), NOW()),
(gen_random_uuid(), 'Google Gemini Configuration', 'gemini', 'https://generativelanguage.googleapis.com/v1/', '{"model": "gemini-pro", "temperature": 0.7}', true, false, true, false, NOW(), NOW()),
(gen_random_uuid(), 'n8n Workflow Configuration', 'n8n', 'http://localhost:5678/webhook/', '{"workflow_id": "ai-ticket-processing", "timeout": 30}', true, true, false, false, NOW(), NOW()),
(gen_random_uuid(), 'Custom AI Provider', 'custom', 'http://localhost:9000/api/ai/', '{"version": "v1", "format": "json"}', false, true, false, false, NOW(), NOW())
ON CONFLICT DO NOTHING;

-- Create test categories for AI suggestions
INSERT INTO categories (id, name, description, icon, color, is_active, display_order, created_at) VALUES
(gen_random_uuid(), 'Technical Support', 'Technical issues and troubleshooting', 'wrench', '#3B82F6', true, 1, NOW()),
(gen_random_uuid(), 'Billing Inquiry', 'Billing and payment related questions', 'credit-card', '#10B981', true, 2, NOW()),
(gen_random_uuid(), 'Feature Request', 'New feature suggestions and enhancements', 'lightbulb', '#F59E0B', true, 3, NOW()),
(gen_random_uuid(), 'Bug Report', 'Software bugs and issues', 'bug', '#EF4444', true, 4, NOW()),
(gen_random_uuid(), 'General Inquiry', 'General questions and information', 'help-circle', '#6B7280', true, 5, NOW())
ON CONFLICT DO NOTHING;

-- Create test client
INSERT INTO clients (id, email, name, company, phone, language, created_at, updated_at) VALUES
(gen_random_uuid(), 'test.client@example.com', 'Test Client', 'Test Company', '+1234567890', 'en', NOW(), NOW())
ON CONFLICT (email) DO NOTHING;

-- Create test tickets with AI fields
WITH test_client AS (
    SELECT id FROM clients WHERE email = 'test.client@example.com' LIMIT 1
),
tech_category AS (
    SELECT id FROM categories WHERE name = 'Technical Support' LIMIT 1
)
INSERT INTO tickets (
    id,
    ticket_number,
    subject,
    description,
    status,
    priority,
    source,
    client_id,
    category_id,
    -- AI Enhancement Fields
    detected_language,
    language_confidence_score,
    sentiment_score,
    sentiment_confidence,
    ai_category_suggestions,
    ai_tag_suggestions,
    ai_response_suggestions,
    ai_estimated_resolution_time,
    ai_processing_metadata,
    ai_processing_status,
    ai_categorization_enabled,
    ai_suggestions_enabled,
    ai_sentiment_analysis_enabled,
    created_at,
    updated_at
) VALUES
(
    gen_random_uuid(),
    'TKT-000001',
    'Unable to login to my account',
    'I am having trouble logging into my account. The password reset link is not working.',
    'new',
    'medium',
    'email',
    (SELECT id FROM test_client),
    (SELECT id FROM tech_category),
    -- AI fields
    'en',
    0.95,
    -0.3,
    0.87,
    '["Technical Support", "Account Issues", "Authentication"]'::jsonb,
    '["login", "password", "authentication", "account"]'::jsonb,
    '[{"suggestion": "Hello! I understand you''re having trouble logging into your account. Let me help you resolve this issue.", "confidence": 0.92, "type": "greeting"}, {"suggestion": "Please try clearing your browser cache and cookies, then attempt to log in again.", "confidence": 0.85, "type": "solution"}]'::jsonb,
    60,
    '{"provider": "test", "processed_at": "2024-01-15T10:30:00Z", "confidence_threshold": 0.8}'::jsonb,
    'completed',
    true,
    true,
    true,
    NOW(),
    NOW()
),
(
    gen_random_uuid(),
    'TKT-000002',
    'Billing question about my invoice',
    'I received an invoice but I don''t understand some of the charges. Can you please explain?',
    'new',
    'low',
    'web_form',
    (SELECT id FROM test_client),
    (SELECT id FROM categories WHERE name = 'Billing Inquiry' LIMIT 1),
    -- AI fields
    'en',
    0.98,
    0.1,
    0.75,
    '["Billing Inquiry", "Invoice", "Charges"]'::jsonb,
    '["billing", "invoice", "charges", "payment"]'::jsonb,
    '[{"suggestion": "Thank you for reaching out about your invoice. I''d be happy to explain the charges for you.", "confidence": 0.91, "type": "greeting"}]'::jsonb,
    30,
    '{"provider": "test", "processed_at": "2024-01-15T11:00:00Z", "confidence_threshold": 0.8}'::jsonb,
    'pending',
    true,
    true,
    true,
    NOW(),
    NOW()
);

-- Create test AI processing queue entries
INSERT INTO ai_processing_queue (id, ticket_id, configuration_id, action_type, request_payload, status, scheduled_at)
SELECT
    gen_random_uuid(),
    t.id,
    c.id,
    'categorize',
    jsonb_build_object('ticket_id', t.id, 'subject', t.subject, 'description', t.description),
    'pending',
    NOW()
FROM tickets t
CROSS JOIN ai_configurations c
WHERE t.ticket_number IN ('TKT-000001', 'TKT-000002')
AND c.provider = 'openai'
LIMIT 2;

-- Create feature flag configurations (if table exists)
DO $$
BEGIN
    IF EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'feature_flags') THEN
        INSERT INTO feature_flags (id, name, key, value, type, description, is_active, created_at, updated_at) VALUES
        (gen_random_uuid(), 'AI Auto Categorization', 'ai_auto_categorization', 'false', 'boolean', 'Automatically categorize tickets using AI', true, NOW(), NOW()),
        (gen_random_uuid(), 'AI Response Suggestions', 'ai_response_suggestions', 'true', 'boolean', 'Show AI-generated response suggestions to agents', true, NOW(), NOW()),
        (gen_random_uuid(), 'AI Sentiment Analysis', 'ai_sentiment_analysis', 'true', 'boolean', 'Analyze customer sentiment in tickets', true, NOW(), NOW()),
        (gen_random_uuid(), 'AI Confidence Threshold', 'ai_confidence_threshold', '0.8', 'number', 'Minimum confidence score for AI suggestions', true, NOW(), NOW())
        ON CONFLICT (key) DO NOTHING;
    END IF;
END $$;

-- Insert test permissions for AI features
INSERT INTO permissions (id, resource, action, description, created_at) VALUES
(gen_random_uuid(), 'ai_configurations', 'view', 'View AI configuration settings', NOW()),
(gen_random_uuid(), 'ai_configurations', 'create', 'Create new AI configurations', NOW()),
(gen_random_uuid(), 'ai_configurations', 'update', 'Update existing AI configurations', NOW()),
(gen_random_uuid(), 'ai_configurations', 'delete', 'Delete AI configurations', NOW()),
(gen_random_uuid(), 'ai_suggestions', 'view', 'View AI suggestions for tickets', NOW()),
(gen_random_uuid(), 'ai_suggestions', 'apply', 'Apply AI suggestions to tickets', NOW()),
(gen_random_uuid(), 'feature_flags', 'manage', 'Manage feature flags', NOW())
ON CONFLICT (resource, action) DO NOTHING;

-- Grant AI permissions to admin role
INSERT INTO role_permissions (role, permission_id)
SELECT 'admin', id FROM permissions
WHERE resource IN ('ai_configurations', 'ai_suggestions', 'feature_flags')
ON CONFLICT DO NOTHING;

-- Create test admin user if not exists
INSERT INTO users (id, email, password_hash, name, role, is_active, created_at, updated_at) VALUES
(gen_random_uuid(), 'admin@aidly.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Test Admin', 'admin', true, NOW(), NOW())
ON CONFLICT (email) DO NOTHING;

-- Create indices for better AI query performance
CREATE INDEX IF NOT EXISTS idx_tickets_ai_processing_status ON tickets(ai_processing_status);
CREATE INDEX IF NOT EXISTS idx_tickets_ai_enabled ON tickets(ai_categorization_enabled, ai_suggestions_enabled, ai_sentiment_analysis_enabled);
CREATE INDEX IF NOT EXISTS idx_ai_processing_queue_status ON ai_processing_queue(status, scheduled_at);
CREATE INDEX IF NOT EXISTS idx_ai_configurations_provider ON ai_configurations(provider, is_active);

-- Create sequence for ticket numbers if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_sequences WHERE schemaname = 'public' AND sequencename = 'ticket_number_seq') THEN
        CREATE SEQUENCE ticket_number_seq START 3;
    ELSE
        -- Reset sequence to continue from existing tickets
        PERFORM setval('ticket_number_seq', GREATEST(3, (SELECT COALESCE(MAX(CAST(SUBSTRING(ticket_number FROM 5) AS INTEGER)), 2) + 1 FROM tickets WHERE ticket_number ~ '^TKT-\d+$')));
    END IF;
END $$;

-- Verify data creation
SELECT 'AI Configurations created: ' || COUNT(*) FROM ai_configurations;
SELECT 'Test tickets with AI fields created: ' || COUNT(*) FROM tickets WHERE ai_processing_status IS NOT NULL;
SELECT 'AI processing queue entries: ' || COUNT(*) FROM ai_processing_queue;
SELECT 'Categories created: ' || COUNT(*) FROM categories;
SELECT 'Test clients created: ' || COUNT(*) FROM clients WHERE email = 'test.client@example.com';

COMMIT;