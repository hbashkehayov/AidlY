-- Track when each user last viewed each ticket
-- This allows multiple users to independently track their views
CREATE TABLE IF NOT EXISTS ticket_user_views (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ticket_id UUID NOT NULL,
    user_id UUID NOT NULL,
    last_viewed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    -- Each user can only have one view record per ticket
    UNIQUE(ticket_id, user_id)
);

-- Index for fast lookups
CREATE INDEX idx_ticket_user_views_ticket ON ticket_user_views(ticket_id);
CREATE INDEX idx_ticket_user_views_user ON ticket_user_views(user_id);
CREATE INDEX idx_ticket_user_views_composite ON ticket_user_views(ticket_id, user_id);

-- Add updated_at trigger
CREATE TRIGGER update_ticket_user_views_updated_at BEFORE UPDATE ON ticket_user_views
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Comments are considered "unread" if they were created AFTER the user's last view of the ticket
