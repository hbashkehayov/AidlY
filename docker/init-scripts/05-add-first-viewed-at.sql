-- Add first_viewed_at column to tickets table to track when a ticket was first viewed
-- This helps distinguish truly new tickets from ones that have been acknowledged

ALTER TABLE tickets ADD COLUMN IF NOT EXISTS first_viewed_at TIMESTAMP NULL;

-- Add comment to explain the column
COMMENT ON COLUMN tickets.first_viewed_at IS 'Timestamp when the ticket was first viewed/acknowledged by an agent';

-- Create index for better query performance
CREATE INDEX IF NOT EXISTS idx_tickets_first_viewed_at ON tickets(first_viewed_at);
