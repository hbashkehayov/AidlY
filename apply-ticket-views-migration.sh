#!/bin/bash
# Apply the ticket_user_views migration to the database

echo "Applying ticket_user_views migration..."

# Check if docker-compose is running
if ! docker ps | grep -q postgres; then
    echo "Error: PostgreSQL container is not running"
    echo "Please start the services first: docker-compose up -d"
    exit 1
fi

# Apply the migration
docker exec -i $(docker ps -q -f name=postgres) psql -U postgres -d aidly << 'SQL'
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
CREATE INDEX IF NOT EXISTS idx_ticket_user_views_ticket ON ticket_user_views(ticket_id);
CREATE INDEX IF NOT EXISTS idx_ticket_user_views_user ON ticket_user_views(user_id);
CREATE INDEX IF NOT EXISTS idx_ticket_user_views_composite ON ticket_user_views(ticket_id, user_id);

-- Add updated_at trigger
DROP TRIGGER IF EXISTS update_ticket_user_views_updated_at ON ticket_user_views;
CREATE TRIGGER update_ticket_user_views_updated_at BEFORE UPDATE ON ticket_user_views
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

SQL

if [ $? -eq 0 ]; then
    echo "✅ Migration applied successfully!"
    echo ""
    echo "The ticket_user_views table has been created."
    echo "Each user can now independently track when they last viewed each ticket."
    echo "Unread counters will now work correctly across multiple users!"
else
    echo "❌ Migration failed!"
    exit 1
fi
