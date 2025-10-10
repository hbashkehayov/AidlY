-- Add is_archived field to tickets table for soft-delete functionality
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS is_archived BOOLEAN DEFAULT false;

-- Add index for better performance when filtering archived tickets
CREATE INDEX IF NOT EXISTS idx_tickets_is_archived ON tickets(is_archived);

-- Update existing tickets to ensure they have the is_archived field set
UPDATE tickets SET is_archived = false WHERE is_archived IS NULL;
