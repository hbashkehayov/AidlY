-- Check Email Queue Status
SELECT
    COUNT(*) as total_emails,
    COUNT(CASE WHEN is_processed = true THEN 1 END) as processed,
    COUNT(CASE WHEN is_processed = false THEN 1 END) as pending,
    COUNT(CASE WHEN error_message IS NOT NULL THEN 1 END) as failed
FROM email_queue;

-- Show recent emails in queue
SELECT
    id,
    substring(subject, 1, 50) as subject,
    from_address,
    is_processed,
    ticket_id,
    substring(error_message, 1, 100) as error_msg,
    received_at
FROM email_queue
ORDER BY received_at DESC
LIMIT 10;

-- Check actual tickets (not mock data)
SELECT
    t.id,
    t.ticket_number,
    substring(t.subject, 1, 50) as subject,
    t.source,
    t.status,
    c.email as client_email,
    t.created_at
FROM tickets t
LEFT JOIN clients c ON t.client_id = c.id
WHERE t.source = 'email'
ORDER BY t.created_at DESC
LIMIT 10;

-- Count tickets by source
SELECT
    source,
    COUNT(*) as count
FROM tickets
GROUP BY source
ORDER BY count DESC;