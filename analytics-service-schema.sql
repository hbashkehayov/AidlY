-- Sprint 5.1: Analytics Service Database Schema
-- This script creates tables for analytics aggregation and reporting

\c aidly;

-- Analytics Events Table (for detailed event tracking)
CREATE TABLE IF NOT EXISTS analytics_events (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    event_type VARCHAR(100) NOT NULL,
    event_category VARCHAR(100),

    -- Related entities
    ticket_id UUID,
    client_id UUID,
    user_id UUID,

    -- Event data
    properties JSONB DEFAULT '{}',

    -- Session info
    session_id VARCHAR(255),
    ip_address INET,
    user_agent TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (ticket_id) REFERENCES tickets(id),
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Agent Performance Metrics (aggregated daily)
CREATE TABLE IF NOT EXISTS agent_metrics (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    agent_id UUID NOT NULL,
    date DATE NOT NULL,

    -- Ticket Metrics
    tickets_created INTEGER DEFAULT 0,
    tickets_resolved INTEGER DEFAULT 0,
    tickets_escalated INTEGER DEFAULT 0,
    tickets_assigned INTEGER DEFAULT 0,

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

-- Ticket Metrics (aggregated daily)
CREATE TABLE IF NOT EXISTS ticket_metrics (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    date DATE NOT NULL,

    -- Volume metrics
    tickets_created INTEGER DEFAULT 0,
    tickets_resolved INTEGER DEFAULT 0,
    tickets_closed INTEGER DEFAULT 0,
    tickets_reopened INTEGER DEFAULT 0,

    -- Status distribution
    status_new INTEGER DEFAULT 0,
    status_open INTEGER DEFAULT 0,
    status_pending INTEGER DEFAULT 0,
    status_on_hold INTEGER DEFAULT 0,
    status_resolved INTEGER DEFAULT 0,
    status_closed INTEGER DEFAULT 0,
    status_cancelled INTEGER DEFAULT 0,

    -- Priority distribution
    priority_low INTEGER DEFAULT 0,
    priority_medium INTEGER DEFAULT 0,
    priority_high INTEGER DEFAULT 0,
    priority_urgent INTEGER DEFAULT 0,

    -- Source distribution
    source_email INTEGER DEFAULT 0,
    source_web_form INTEGER DEFAULT 0,
    source_chat INTEGER DEFAULT 0,
    source_phone INTEGER DEFAULT 0,
    source_social_media INTEGER DEFAULT 0,
    source_api INTEGER DEFAULT 0,
    source_internal INTEGER DEFAULT 0,

    -- Performance metrics
    avg_first_response_time INTEGER,
    avg_resolution_time INTEGER,
    avg_customer_satisfaction DECIMAL(3,2),

    -- AI metrics
    ai_categorizations INTEGER DEFAULT 0,
    ai_suggestions_used INTEGER DEFAULT 0,
    ai_sentiment_analyzed INTEGER DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE(date)
);

-- Client Metrics (aggregated daily)
CREATE TABLE IF NOT EXISTS client_metrics (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    date DATE NOT NULL,

    -- Client counts
    new_clients INTEGER DEFAULT 0,
    active_clients INTEGER DEFAULT 0,
    vip_clients INTEGER DEFAULT 0,
    blocked_clients INTEGER DEFAULT 0,

    -- Engagement metrics
    clients_with_tickets INTEGER DEFAULT 0,
    avg_tickets_per_client DECIMAL(5,2),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE(date)
);

-- Custom Reports Configuration
CREATE TABLE IF NOT EXISTS reports (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    description TEXT,
    report_type VARCHAR(100) NOT NULL,

    -- Configuration
    query_sql TEXT NOT NULL,
    filters JSONB DEFAULT '{}',
    columns JSONB NOT NULL,
    chart_config JSONB DEFAULT '{}',

    -- Scheduling
    schedule_config JSONB DEFAULT '{}',
    recipients TEXT[],

    -- Access control
    is_public BOOLEAN DEFAULT false,
    created_by UUID NOT NULL,

    -- Status
    is_active BOOLEAN DEFAULT true,
    last_executed_at TIMESTAMP,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Report Executions Log
CREATE TABLE IF NOT EXISTS report_executions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    report_id UUID NOT NULL,

    -- Execution details
    executed_by UUID,
    execution_type VARCHAR(50) NOT NULL, -- 'manual', 'scheduled', 'export'

    -- Results
    status VARCHAR(50) NOT NULL, -- 'running', 'completed', 'failed'
    record_count INTEGER,
    execution_time_ms INTEGER,
    file_path TEXT,
    error_message TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
    FOREIGN KEY (executed_by) REFERENCES users(id)
);

-- Scheduled Report Jobs
CREATE TABLE IF NOT EXISTS scheduled_reports (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    report_id UUID NOT NULL,

    -- Schedule
    cron_expression VARCHAR(255) NOT NULL,
    timezone VARCHAR(50) DEFAULT 'UTC',

    -- Recipients
    recipients JSONB NOT NULL, -- Array of email addresses and user IDs

    -- Status
    is_active BOOLEAN DEFAULT true,
    last_run_at TIMESTAMP,
    next_run_at TIMESTAMP,
    failure_count INTEGER DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE
);

-- Analytics Cache (for frequently accessed aggregations)
CREATE TABLE IF NOT EXISTS analytics_cache (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    cache_key VARCHAR(255) UNIQUE NOT NULL,
    cache_data JSONB NOT NULL,

    -- Metadata
    generated_at TIMESTAMP NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    generation_time_ms INTEGER,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_analytics_events_type ON analytics_events(event_type);
CREATE INDEX IF NOT EXISTS idx_analytics_events_category ON analytics_events(event_category);
CREATE INDEX IF NOT EXISTS idx_analytics_events_ticket_id ON analytics_events(ticket_id);
CREATE INDEX IF NOT EXISTS idx_analytics_events_created_at ON analytics_events(created_at);
CREATE INDEX IF NOT EXISTS idx_analytics_events_user_id ON analytics_events(user_id);

CREATE INDEX IF NOT EXISTS idx_agent_metrics_agent_date ON agent_metrics(agent_id, date);
CREATE INDEX IF NOT EXISTS idx_agent_metrics_date ON agent_metrics(date);

CREATE INDEX IF NOT EXISTS idx_ticket_metrics_date ON ticket_metrics(date);

CREATE INDEX IF NOT EXISTS idx_client_metrics_date ON client_metrics(date);

CREATE INDEX IF NOT EXISTS idx_reports_type ON reports(report_type);
CREATE INDEX IF NOT EXISTS idx_reports_created_by ON reports(created_by);
CREATE INDEX IF NOT EXISTS idx_reports_active ON reports(is_active);

CREATE INDEX IF NOT EXISTS idx_report_executions_report_id ON report_executions(report_id);
CREATE INDEX IF NOT EXISTS idx_report_executions_created_at ON report_executions(created_at);
CREATE INDEX IF NOT EXISTS idx_report_executions_status ON report_executions(status);

CREATE INDEX IF NOT EXISTS idx_scheduled_reports_next_run ON scheduled_reports(next_run_at) WHERE is_active = true;

CREATE INDEX IF NOT EXISTS idx_analytics_cache_key ON analytics_cache(cache_key);
CREATE INDEX IF NOT EXISTS idx_analytics_cache_expires ON analytics_cache(expires_at);

-- Add updated_at triggers
CREATE TRIGGER update_agent_metrics_updated_at BEFORE UPDATE ON agent_metrics
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_ticket_metrics_updated_at BEFORE UPDATE ON ticket_metrics
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_client_metrics_updated_at BEFORE UPDATE ON client_metrics
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_reports_updated_at BEFORE UPDATE ON reports
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_scheduled_reports_updated_at BEFORE UPDATE ON scheduled_reports
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_analytics_cache_updated_at BEFORE UPDATE ON analytics_cache
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Insert some sample reports
INSERT INTO reports (name, description, report_type, query_sql, columns, chart_config, is_public, created_by) VALUES
(
    'Daily Ticket Summary',
    'Daily overview of ticket volume and resolution metrics',
    'dashboard',
    'SELECT date, tickets_created, tickets_resolved, avg_resolution_time FROM ticket_metrics WHERE date >= $1 AND date <= $2 ORDER BY date',
    '["date", "tickets_created", "tickets_resolved", "avg_resolution_time"]'::jsonb,
    '{"type": "line", "x": "date", "y": ["tickets_created", "tickets_resolved"]}'::jsonb,
    true,
    (SELECT id FROM users WHERE role = 'admin' LIMIT 1)
),
(
    'Agent Performance Report',
    'Weekly performance metrics by agent',
    'performance',
    'SELECT u.name as agent_name, SUM(am.tickets_resolved) as total_resolved, AVG(am.avg_resolution_time) as avg_resolution_time FROM agent_metrics am JOIN users u ON am.agent_id = u.id WHERE am.date >= $1 AND am.date <= $2 GROUP BY u.id, u.name ORDER BY total_resolved DESC',
    '["agent_name", "total_resolved", "avg_resolution_time"]'::jsonb,
    '{"type": "bar", "x": "agent_name", "y": "total_resolved"}'::jsonb,
    false,
    (SELECT id FROM users WHERE role = 'admin' LIMIT 1)
),
(
    'Customer Satisfaction Trends',
    'Monthly customer satisfaction trends by category',
    'satisfaction',
    'SELECT DATE_TRUNC(''month'', tm.date) as month, AVG(tm.avg_customer_satisfaction) as satisfaction FROM ticket_metrics tm WHERE tm.date >= $1 AND tm.date <= $2 GROUP BY DATE_TRUNC(''month'', tm.date) ORDER BY month',
    '["month", "satisfaction"]'::jsonb,
    '{"type": "area", "x": "month", "y": "satisfaction"}'::jsonb,
    true,
    (SELECT id FROM users WHERE role = 'admin' LIMIT 1)
) ON CONFLICT DO NOTHING;

-- Create sequence for analytics event IDs (if needed for high volume)
CREATE SEQUENCE IF NOT EXISTS analytics_event_seq;

-- Create materialized view for quick dashboard stats
CREATE MATERIALIZED VIEW IF NOT EXISTS dashboard_stats AS
SELECT
    -- Current counts
    (SELECT COUNT(*) FROM tickets WHERE status IN ('new', 'open', 'pending', 'on_hold')) as open_tickets,
    (SELECT COUNT(*) FROM tickets WHERE status = 'pending') as pending_tickets,
    (SELECT COUNT(*) FROM clients WHERE is_active = true AND last_contact_at >= NOW() - INTERVAL '30 days') as active_customers,
    (SELECT COUNT(*) FROM users WHERE role IN ('agent', 'supervisor') AND is_active = true) as active_agents,

    -- Today's metrics
    (SELECT COUNT(*) FROM tickets WHERE DATE(created_at) = CURRENT_DATE) as tickets_created_today,
    (SELECT COUNT(*) FROM tickets WHERE DATE(resolved_at) = CURRENT_DATE) as tickets_resolved_today,

    -- Averages (last 30 days)
    (SELECT AVG(EXTRACT(EPOCH FROM (first_response_at - created_at))/3600) FROM tickets WHERE first_response_at IS NOT NULL AND created_at >= NOW() - INTERVAL '30 days') as avg_response_time_hours,
    (SELECT AVG(EXTRACT(EPOCH FROM (resolved_at - created_at))/3600) FROM tickets WHERE resolved_at IS NOT NULL AND created_at >= NOW() - INTERVAL '30 days') as avg_resolution_time_hours,

    -- Priority distribution
    (SELECT jsonb_object_agg(priority, count) FROM (SELECT priority, COUNT(*) FROM tickets WHERE status IN ('new', 'open', 'pending', 'on_hold') GROUP BY priority) counts) as priority_distribution,

    -- Status distribution
    (SELECT jsonb_object_agg(status, count) FROM (SELECT status, COUNT(*) FROM tickets GROUP BY status) counts) as status_distribution,

    -- Updated timestamp
    NOW() as last_updated;

-- Create unique index on materialized view
CREATE UNIQUE INDEX IF NOT EXISTS dashboard_stats_unique ON dashboard_stats(last_updated);

-- Function to refresh dashboard stats
CREATE OR REPLACE FUNCTION refresh_dashboard_stats()
RETURNS void AS $$
BEGIN
    REFRESH MATERIALIZED VIEW CONCURRENTLY dashboard_stats;
END;
$$ LANGUAGE plpgsql;

-- Verify the analytics schema creation
SELECT 'Analytics service schema created successfully!' as status;
SELECT 'Analytics tables count: ' || COUNT(*) as tables_created
FROM information_schema.tables
WHERE table_schema = 'public'
AND table_name IN (
    'analytics_events', 'agent_metrics', 'ticket_metrics', 'client_metrics',
    'reports', 'report_executions', 'scheduled_reports', 'analytics_cache'
);

COMMIT;