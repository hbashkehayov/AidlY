-- Create remaining tables for complete AidlY schema
-- This script adds all missing tables from the comprehensive roadmap

-- Ticket Attachments
CREATE TABLE IF NOT EXISTS attachments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ticket_id UUID,
    comment_id UUID,
    uploaded_by_user_id UUID,
    uploaded_by_client_id UUID,
    file_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(100),
    file_size INTEGER,
    storage_path TEXT NOT NULL,
    mime_type VARCHAR(100),
    is_inline BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (comment_id) REFERENCES ticket_comments(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id),
    FOREIGN KEY (uploaded_by_client_id) REFERENCES clients(id)
);

-- Ticket Relationships (for merging, parent-child)
CREATE TABLE IF NOT EXISTS ticket_relationships (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    parent_ticket_id UUID NOT NULL,
    child_ticket_id UUID NOT NULL,
    relationship_type VARCHAR(50) NOT NULL, -- 'merged', 'related', 'duplicate'
    created_by UUID NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (parent_ticket_id) REFERENCES tickets(id),
    FOREIGN KEY (child_ticket_id) REFERENCES tickets(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    UNIQUE(parent_ticket_id, child_ticket_id)
);

-- Client Merge History
CREATE TABLE IF NOT EXISTS client_merges (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    primary_client_id UUID NOT NULL,
    merged_client_id UUID NOT NULL,
    merged_by UUID NOT NULL,
    merge_data JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (primary_client_id) REFERENCES clients(id),
    FOREIGN KEY (merged_by) REFERENCES users(id)
);

-- Client Notes
CREATE TABLE IF NOT EXISTS client_notes (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    client_id UUID NOT NULL,
    created_by UUID NOT NULL,
    note TEXT NOT NULL,
    is_pinned BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Email Accounts Configuration
CREATE TABLE IF NOT EXISTS email_accounts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    email_address VARCHAR(255) NOT NULL,

    -- IMAP Settings
    imap_host VARCHAR(255),
    imap_port INTEGER,
    imap_username VARCHAR(255),
    imap_password_encrypted TEXT,
    imap_use_ssl BOOLEAN DEFAULT true,

    -- SMTP Settings
    smtp_host VARCHAR(255),
    smtp_port INTEGER,
    smtp_username VARCHAR(255),
    smtp_password_encrypted TEXT,
    smtp_use_tls BOOLEAN DEFAULT true,

    -- Configuration
    department_id UUID,
    auto_create_tickets BOOLEAN DEFAULT true,
    default_ticket_priority ticket_priority DEFAULT 'medium',
    default_category_id UUID,

    is_active BOOLEAN DEFAULT true,
    last_sync_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (default_category_id) REFERENCES categories(id)
);

-- Notification Queue Table (for processing notifications)
CREATE TABLE notification_queue (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    notification_id UUID,

    -- Notifiable entity
    notifiable_type VARCHAR(50) NOT NULL,
    notifiable_id UUID NOT NULL,

    -- Notification details
    type VARCHAR(100) NOT NULL,
    channel VARCHAR(20) NOT NULL,
    title VARCHAR(500) NOT NULL,
    message TEXT NOT NULL,
    data JSONB,

    -- Queue management
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'processing', 'sent', 'failed')),
    priority INTEGER DEFAULT 0,
    attempts INTEGER DEFAULT 0,
    error TEXT,

    -- Scheduling
    scheduled_at TIMESTAMP,
    started_at TIMESTAMP,
    sent_at TIMESTAMP,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for notification_queue
CREATE INDEX idx_notification_queue_status_scheduled ON notification_queue(status, scheduled_at);
CREATE INDEX idx_notification_queue_notifiable ON notification_queue(notifiable_type, notifiable_id);
CREATE INDEX idx_notification_queue_type_channel ON notification_queue(type, channel);
CREATE INDEX idx_notification_queue_priority ON notification_queue(priority);

-- Email Queue
CREATE TABLE IF NOT EXISTS email_queue (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email_account_id UUID NOT NULL,
    message_id VARCHAR(500) UNIQUE,
    from_address VARCHAR(255),
    to_addresses TEXT[],
    cc_addresses TEXT[],
    subject TEXT,
    body_plain TEXT,
    body_html TEXT,
    headers JSONB,
    attachments JSONB,

    -- Processing
    ticket_id UUID,
    is_processed BOOLEAN DEFAULT false,
    processed_at TIMESTAMP,
    error_message TEXT,
    retry_count INTEGER DEFAULT 0,

    received_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (email_account_id) REFERENCES email_accounts(id),
    FOREIGN KEY (ticket_id) REFERENCES tickets(id)
);

-- Email Templates
CREATE TABLE IF NOT EXISTS email_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    subject VARCHAR(500) NOT NULL,
    body_html TEXT NOT NULL,
    body_plain TEXT,
    category VARCHAR(100),
    variables JSONB,
    is_active BOOLEAN DEFAULT true,
    created_by UUID NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Automation Rules
CREATE TABLE IF NOT EXISTS automation_rules (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    description TEXT,

    -- Trigger
    trigger_type VARCHAR(100) NOT NULL, -- 'ticket_created', 'ticket_updated', 'time_based', etc.
    trigger_conditions JSONB,

    -- Actions
    actions JSONB NOT NULL,

    -- Configuration
    execution_order INTEGER DEFAULT 100,
    stop_processing BOOLEAN DEFAULT false,
    is_active BOOLEAN DEFAULT true,

    -- Stats
    last_triggered_at TIMESTAMP,
    trigger_count INTEGER DEFAULT 0,

    created_by UUID NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Macros (Saved Action Sets)
CREATE TABLE IF NOT EXISTS macros (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    description TEXT,
    actions JSONB NOT NULL,
    is_public BOOLEAN DEFAULT false,
    created_by UUID NOT NULL,
    usage_count INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Knowledge Base Categories
CREATE TABLE IF NOT EXISTS kb_categories (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT,
    parent_category_id UUID,
    icon VARCHAR(50),
    display_order INTEGER,
    is_public BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (parent_category_id) REFERENCES kb_categories(id)
);

-- Knowledge Base Articles
CREATE TABLE IF NOT EXISTS kb_articles (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title VARCHAR(500) NOT NULL,
    slug VARCHAR(500) UNIQUE NOT NULL,
    content TEXT NOT NULL,
    summary TEXT,

    -- Organization
    category_id UUID,
    tags TEXT[],

    -- Visibility
    is_public BOOLEAN DEFAULT false,
    is_featured BOOLEAN DEFAULT false,

    -- SEO
    meta_title VARCHAR(255),
    meta_description TEXT,

    -- Versioning
    version INTEGER DEFAULT 1,
    parent_article_id UUID,

    -- Stats
    view_count INTEGER DEFAULT 0,
    helpful_count INTEGER DEFAULT 0,
    not_helpful_count INTEGER DEFAULT 0,

    -- Workflow
    status VARCHAR(50) DEFAULT 'draft', -- 'draft', 'review', 'published', 'archived'
    published_at TIMESTAMP,
    reviewed_by UUID,

    author_id UUID NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (category_id) REFERENCES kb_categories(id),
    FOREIGN KEY (parent_article_id) REFERENCES kb_articles(id),
    FOREIGN KEY (author_id) REFERENCES users(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id)
);

-- Article Feedback
CREATE TABLE IF NOT EXISTS kb_feedback (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    article_id UUID NOT NULL,
    client_id UUID,
    is_helpful BOOLEAN NOT NULL,
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (article_id) REFERENCES kb_articles(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id)
);

-- AI Integration Configuration
CREATE TABLE IF NOT EXISTS ai_configurations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    provider VARCHAR(50) NOT NULL, -- 'openai', 'claude', 'n8n', 'custom'

    -- Connection settings
    api_endpoint TEXT,
    api_key_encrypted TEXT,
    webhook_secret VARCHAR(255),

    -- Configuration
    model_settings JSONB,
    retry_policy JSONB,
    timeout_seconds INTEGER DEFAULT 30,

    -- Feature flags
    enable_categorization BOOLEAN DEFAULT false,
    enable_suggestions BOOLEAN DEFAULT false,
    enable_sentiment BOOLEAN DEFAULT false,
    enable_auto_assign BOOLEAN DEFAULT false,

    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- AI Processing Queue
CREATE TABLE IF NOT EXISTS ai_processing_queue (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ticket_id UUID NOT NULL,
    configuration_id UUID NOT NULL,

    -- Processing details
    action_type VARCHAR(50) NOT NULL, -- 'categorize', 'suggest', 'sentiment'
    request_payload JSONB,
    response_payload JSONB,

    -- Status tracking
    status VARCHAR(50) DEFAULT 'pending', -- 'pending', 'processing', 'completed', 'failed'
    attempts INTEGER DEFAULT 0,
    error_message TEXT,

    -- Timestamps
    scheduled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP,
    completed_at TIMESTAMP,

    FOREIGN KEY (ticket_id) REFERENCES tickets(id),
    FOREIGN KEY (configuration_id) REFERENCES ai_configurations(id)
);

-- Analytics Events
CREATE TABLE IF NOT EXISTS analytics_events (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    event_type VARCHAR(100) NOT NULL,
    event_category VARCHAR(100),

    -- Related entities
    ticket_id UUID,
    client_id UUID,
    user_id UUID,

    -- Event data
    properties JSONB,

    -- Session info
    session_id VARCHAR(255),
    ip_address INET,
    user_agent TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (ticket_id) REFERENCES tickets(id),
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Agent Performance Metrics
CREATE TABLE IF NOT EXISTS agent_metrics (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    agent_id UUID NOT NULL,
    date DATE NOT NULL,

    -- Ticket Metrics
    tickets_created INTEGER DEFAULT 0,
    tickets_resolved INTEGER DEFAULT 0,
    tickets_escalated INTEGER DEFAULT 0,

    -- Time Metrics (in seconds)
    avg_first_response_time INTEGER,
    avg_resolution_time INTEGER,
    total_working_time INTEGER,

    -- Quality Metrics
    customer_satisfaction_score DECIMAL(3,2),
    internal_quality_score DECIMAL(3,2),

    -- Activity Metrics
    comments_sent INTEGER DEFAULT 0,
    internal_notes INTEGER DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (agent_id) REFERENCES users(id),
    UNIQUE(agent_id, date)
);

-- Custom Reports
CREATE TABLE IF NOT EXISTS reports (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    description TEXT,
    report_type VARCHAR(100) NOT NULL,

    -- Configuration
    query TEXT,
    filters JSONB,
    columns JSONB,
    chart_config JSONB,

    -- Scheduling
    schedule_config JSONB,
    recipients TEXT[],

    -- Access
    is_public BOOLEAN DEFAULT false,
    created_by UUID NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- External Integrations
CREATE TABLE IF NOT EXISTS integrations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    type VARCHAR(100) NOT NULL, -- 'crm', 'chat', 'email', 'custom'

    -- Configuration
    config JSONB NOT NULL,
    credentials_encrypted TEXT,

    -- Mappings
    field_mappings JSONB,

    -- Status
    is_active BOOLEAN DEFAULT true,
    last_sync_at TIMESTAMP,
    last_error TEXT,

    created_by UUID NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Webhooks
CREATE TABLE IF NOT EXISTS webhooks (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    url TEXT NOT NULL,

    -- Events
    events TEXT[] NOT NULL,

    -- Security
    secret_key VARCHAR(255),

    -- Headers
    custom_headers JSONB,

    -- Status
    is_active BOOLEAN DEFAULT true,
    last_triggered_at TIMESTAMP,
    failure_count INTEGER DEFAULT 0,

    created_by UUID NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Webhook Logs
CREATE TABLE IF NOT EXISTS webhook_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    webhook_id UUID NOT NULL,
    event_type VARCHAR(100) NOT NULL,

    -- Request/Response
    request_payload JSONB,
    response_status INTEGER,
    response_body TEXT,

    -- Timing
    duration_ms INTEGER,

    -- Status
    is_success BOOLEAN,
    error_message TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (webhook_id) REFERENCES webhooks(id) ON DELETE CASCADE
);

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_attachments_ticket ON attachments(ticket_id);
CREATE INDEX IF NOT EXISTS idx_attachments_comment ON attachments(comment_id);
CREATE INDEX IF NOT EXISTS idx_client_notes_client ON client_notes(client_id);
CREATE INDEX IF NOT EXISTS idx_email_queue_processed ON email_queue(is_processed);
CREATE INDEX IF NOT EXISTS idx_kb_articles_status ON kb_articles(status);
CREATE INDEX IF NOT EXISTS idx_kb_articles_public ON kb_articles(is_public);
CREATE INDEX IF NOT EXISTS idx_analytics_events_created ON analytics_events(created_at);
CREATE INDEX IF NOT EXISTS idx_agent_metrics_date ON agent_metrics(agent_id, date);
CREATE INDEX IF NOT EXISTS idx_webhooks_active ON webhooks(is_active);

-- Create ticket number sequence if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_class WHERE relname = 'ticket_number_seq') THEN
        CREATE SEQUENCE ticket_number_seq START 1000;
    END IF;
END$$;

-- Add any missing foreign key constraints to users table
DO $$
BEGIN
    -- Check and add foreign key for users referencing users (for foreign keys that reference users)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.table_constraints
        WHERE constraint_name = 'fk_tickets_assigned_agent'
        AND table_name = 'tickets'
    ) THEN
        ALTER TABLE tickets
        ADD CONSTRAINT fk_tickets_assigned_agent
        FOREIGN KEY (assigned_agent_id) REFERENCES users(id);
    END IF;
END$$;

-- Add missing foreign key constraints to tickets table
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.table_constraints
        WHERE constraint_name = 'fk_tickets_assigned_department'
        AND table_name = 'tickets'
    ) THEN
        ALTER TABLE tickets
        ADD CONSTRAINT fk_tickets_assigned_department
        FOREIGN KEY (assigned_department_id) REFERENCES departments(id);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.table_constraints
        WHERE constraint_name = 'fk_tickets_sla_policy'
        AND table_name = 'tickets'
    ) THEN
        ALTER TABLE tickets
        ADD CONSTRAINT fk_tickets_sla_policy
        FOREIGN KEY (sla_policy_id) REFERENCES sla_policies(id);
    END IF;
END$$;