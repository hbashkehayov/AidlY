-- Add email threading fields to tickets table
-- This migration adds support for proper email threading by storing sent Message-IDs

-- Add sent_message_ids field to track all outgoing email Message-IDs for this ticket
ALTER TABLE tickets
ADD COLUMN IF NOT EXISTS sent_message_ids TEXT[] DEFAULT '{}';

-- Add index for faster lookups when searching by message ID
CREATE INDEX IF NOT EXISTS idx_tickets_sent_message_ids ON tickets USING GIN (sent_message_ids);

-- Add comment_id field to ticket_comments to store sent message ID per comment
ALTER TABLE ticket_comments
ADD COLUMN IF NOT EXISTS sent_message_id VARCHAR(500);

-- Add index for faster lookups
CREATE INDEX IF NOT EXISTS idx_ticket_comments_sent_message_id ON ticket_comments(sent_message_id) WHERE sent_message_id IS NOT NULL;

-- Add helper function to append message ID to tickets.sent_message_ids array
CREATE OR REPLACE FUNCTION append_sent_message_id_to_ticket(
    p_ticket_id UUID,
    p_message_id TEXT
) RETURNS VOID AS $$
BEGIN
    UPDATE tickets
    SET sent_message_ids = array_append(sent_message_ids, p_message_id)
    WHERE id = p_ticket_id
    AND NOT (p_message_id = ANY(sent_message_ids)); -- Avoid duplicates
END;
$$ LANGUAGE plpgsql;

COMMENT ON COLUMN tickets.sent_message_ids IS 'Array of Message-IDs sent from this ticket for email threading';
COMMENT ON COLUMN ticket_comments.sent_message_id IS 'Message-ID of the email sent for this comment (for threading)';
COMMENT ON FUNCTION append_sent_message_id_to_ticket IS 'Helper function to add a Message-ID to the tickets sent_message_ids array without duplicates';
