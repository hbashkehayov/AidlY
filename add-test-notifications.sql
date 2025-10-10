-- Add 12 test notifications for hristiyan.bashkehayov@gmail.com
-- User ID: 9a8cb27e-75bd-48b8-b3cf-5093de345786

-- Notification 1: Urgent - New ticket assigned (UNREAD)
INSERT INTO notifications (id, type, channel, notifiable_id, notifiable_type, ticket_id, title, message, status, priority, created_at, updated_at, action_url)
VALUES (
    gen_random_uuid(),
    'ticket_assigned',
    'in_app',
    '9a8cb27e-75bd-48b8-b3cf-5093de345786',
    'user',
    'c22dfde9-dd92-43ec-ae74-1c9ede6816fb',
    'Urgent: New ticket assigned to you',
    'TKT-001160 - Customer reporting critical system outage. Immediate attention required.',
    'sent',
    'urgent',
    NOW() - INTERVAL '2 minutes',
    NOW() - INTERVAL '2 minutes',
    '/tickets/c22dfde9-dd92-43ec-ae74-1c9ede6816fb'
);

-- Notification 2: High priority - New comment on your ticket (UNREAD)
INSERT INTO notifications (id, type, channel, notifiable_id, notifiable_type, ticket_id, title, message, status, priority, created_at, updated_at, action_url)
VALUES (
    gen_random_uuid(),
    'new_comment',
    'in_app',
    '9a8cb27e-75bd-48b8-b3cf-5093de345786',
    'user',
    'fab25e7a-3a34-4715-983e-78e9841698ef',
    'New comment on TKT-001167',
    'Customer replied: "Still waiting for a response on the attachment issue."',
    'sent',
    'high',
    NOW() - INTERVAL '15 minutes',
    NOW() - INTERVAL '15 minutes',
    '/tickets/fab25e7a-3a34-4715-983e-78e9841698ef'
);

-- Notification 3: Normal - Ticket waiting for your response (UNREAD)
INSERT INTO notifications (id, type, channel, notifiable_id, notifiable_type, ticket_id, title, message, status, priority, created_at, updated_at, action_url)
VALUES (
    gen_random_uuid(),
    'ticket_pending',
    'in_app',
    '9a8cb27e-75bd-48b8-b3cf-5093de345786',
    'user',
    '27ba0e2e-7c23-4d02-bc12-70e8542c50b8',
    'Ticket pending your response',
    'TKT-001170 has been waiting for your reply for over 2 hours.',
    'sent',
    'normal',
    NOW() - INTERVAL '30 minutes',
    NOW() - INTERVAL '30 minutes',
    '/tickets/27ba0e2e-7c23-4d02-bc12-70e8542c50b8'
);

-- Notification 4: High - SLA approaching deadline (UNREAD)
INSERT INTO notifications (id, type, channel, notifiable_id, notifiable_type, ticket_id, title, message, status, priority, created_at, updated_at, action_url)
VALUES (
    gen_random_uuid(),
    'sla_warning',
    'in_app',
    '9a8cb27e-75bd-48b8-b3cf-5093de345786',
    'user',
    'c22dfde9-dd92-43ec-ae74-1c9ede6816fb',
    'SLA Warning: Response time approaching',
    'TKT-001160 needs a response within 45 minutes to meet SLA.',
    'sent',
    'high',
    NOW() - INTERVAL '1 hour',
    NOW() - INTERVAL '1 hour',
    '/tickets/c22dfde9-dd92-43ec-ae74-1c9ede6816fb'
);

-- Notification 5: Urgent - Customer escalation (UNREAD)
INSERT INTO notifications (id, type, channel, notifiable_id, notifiable_type, ticket_id, title, message, status, priority, created_at, updated_at, action_url)
VALUES (
    gen_random_uuid(),
    'ticket_escalated',
    'in_app',
    '9a8cb27e-75bd-48b8-b3cf-5093de345786',
    'user',
    'fab25e7a-3a34-4715-983e-78e9841698ef',
    'Ticket escalated by supervisor',
    'TKT-001167 has been escalated. Customer requested manager involvement.',
    'sent',
    'urgent',
    NOW() - INTERVAL '2 hours',
    NOW() - INTERVAL '2 hours',
    '/tickets/fab25e7a-3a34-4715-983e-78e9841698ef'
);

-- Notification 6: Normal - New ticket in queue (UNREAD)
INSERT INTO notifications (id, type, channel, notifiable_id, notifiable_type, ticket_id, title, message, status, priority, created_at, updated_at, action_url)
VALUES (
    gen_random_uuid(),
    'new_ticket',
    'in_app',
    '9a8cb27e-75bd-48b8-b3cf-5093de345786',
    'user',
    '27ba0e2e-7c23-4d02-bc12-70e8542c50b8',
    'New ticket available to claim',
    'A new support ticket is available in the queue and ready to be claimed.',
    'sent',
    'normal',
    NOW() - INTERVAL '3 hours',
    NOW() - INTERVAL '3 hours',
    '/tickets'
);

-- Notification 7: Normal - Ticket resolved confirmation (READ)
INSERT INTO notifications (id, type, channel, notifiable_id, notifiable_type, ticket_id, title, message, status, priority, created_at, updated_at, read_at, action_url)
VALUES (
    gen_random_uuid(),
    'ticket_resolved',
    'in_app',
    '9a8cb27e-75bd-48b8-b3cf-5093de345786',
    'user',
    'c22dfde9-dd92-43ec-ae74-1c9ede6816fb',
    'Ticket resolved successfully',
    'TKT-001160 has been marked as resolved. Customer satisfaction confirmed.',
    'sent',
    'normal',
    NOW() - INTERVAL '5 hours',
    NOW() - INTERVAL '5 hours',
    NOW() - INTERVAL '4 hours',
    '/tickets/c22dfde9-dd92-43ec-ae74-1c9ede6816fb'
);

-- Notification 8: Low - Customer feedback received (READ)
INSERT INTO notifications (id, type, channel, notifiable_id, notifiable_type, ticket_id, title, message, status, priority, created_at, updated_at, read_at, action_url)
VALUES (
    gen_random_uuid(),
    'customer_feedback',
    'in_app',
    '9a8cb27e-75bd-48b8-b3cf-5093de345786',
    'user',
    'fab25e7a-3a34-4715-983e-78e9841698ef',
    'Customer feedback received',
    'Customer rated your support 5 stars! Great job on TKT-001167.',
    'sent',
    'low',
    NOW() - INTERVAL '1 day',
    NOW() - INTERVAL '1 day',
    NOW() - INTERVAL '23 hours',
    '/tickets/fab25e7a-3a34-4715-983e-78e9841698ef'
);

-- Notification 9: Normal - Ticket reassigned (READ)
INSERT INTO notifications (id, type, channel, notifiable_id, notifiable_type, ticket_id, title, message, status, priority, created_at, updated_at, read_at, action_url)
VALUES (
    gen_random_uuid(),
    'ticket_reassigned',
    'in_app',
    '9a8cb27e-75bd-48b8-b3cf-5093de345786',
    'user',
    '27ba0e2e-7c23-4d02-bc12-70e8542c50b8',
    'Ticket reassigned to your queue',
    'TKT-001170 was reassigned to you by supervisor for follow-up.',
    'sent',
    'normal',
    NOW() - INTERVAL '1 day',
    NOW() - INTERVAL '1 day',
    NOW() - INTERVAL '20 hours',
    '/tickets/27ba0e2e-7c23-4d02-bc12-70e8542c50b8'
);

-- Notification 10: High - Customer replied after resolved (UNREAD)
INSERT INTO notifications (id, type, channel, notifiable_id, notifiable_type, ticket_id, title, message, status, priority, created_at, updated_at, action_url)
VALUES (
    gen_random_uuid(),
    'ticket_reopened',
    'in_app',
    '9a8cb27e-75bd-48b8-b3cf-5093de345786',
    'user',
    'c22dfde9-dd92-43ec-ae74-1c9ede6816fb',
    'Resolved ticket reopened',
    'Customer replied to TKT-001160: "The issue is back again."',
    'sent',
    'high',
    NOW() - INTERVAL '6 hours',
    NOW() - INTERVAL '6 hours',
    '/tickets/c22dfde9-dd92-43ec-ae74-1c9ede6816fb'
);

-- Notification 11: Normal - Internal note added (READ)
INSERT INTO notifications (id, type, channel, notifiable_id, notifiable_type, ticket_id, title, message, status, priority, created_at, updated_at, read_at, action_url)
VALUES (
    gen_random_uuid(),
    'internal_note',
    'in_app',
    '9a8cb27e-75bd-48b8-b3cf-5093de345786',
    'user',
    'fab25e7a-3a34-4715-983e-78e9841698ef',
    'Internal note added to your ticket',
    'Supervisor added a note: "Please check with engineering team before responding."',
    'sent',
    'normal',
    NOW() - INTERVAL '2 days',
    NOW() - INTERVAL '2 days',
    NOW() - INTERVAL '1 day',
    '/tickets/fab25e7a-3a34-4715-983e-78e9841698ef'
);

-- Notification 12: Low - Weekly performance summary (READ)
INSERT INTO notifications (id, type, channel, notifiable_id, notifiable_type, title, message, status, priority, created_at, updated_at, read_at, action_url)
VALUES (
    gen_random_uuid(),
    'performance_summary',
    'in_app',
    '9a8cb27e-75bd-48b8-b3cf-5093de345786',
    'user',
    'Weekly Performance Report',
    'Great week! You resolved 24 tickets with an average response time of 15 minutes.',
    'sent',
    'low',
    NOW() - INTERVAL '3 days',
    NOW() - INTERVAL '3 days',
    NOW() - INTERVAL '2 days',
    '/dashboard'
);
