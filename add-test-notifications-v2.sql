-- Add 12 UNREAD test notifications for hristiyan.bashkehayov@gmail.com
-- User ID: 9a8cb27e-75bd-48b8-b3cf-5093de345786
-- All notifications are UNREAD (no read_at timestamp)

-- Notification 1: Urgent - New ticket assigned
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

-- Notification 2: High priority - New comment
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

-- Notification 3: Normal - Ticket pending
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

-- Notification 4: High - SLA warning
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

-- Notification 5: Urgent - Escalation
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

-- Notification 6: Normal - New ticket
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

-- Notification 7: High - Ticket reopened
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
    NOW() - INTERVAL '4 hours',
    NOW() - INTERVAL '4 hours',
    '/tickets/c22dfde9-dd92-43ec-ae74-1c9ede6816fb'
);

-- Notification 8: Normal - Customer feedback
INSERT INTO notifications (id, type, channel, notifiable_id, notifiable_type, ticket_id, title, message, status, priority, created_at, updated_at, action_url)
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
    'normal',
    NOW() - INTERVAL '5 hours',
    NOW() - INTERVAL '5 hours',
    '/tickets/fab25e7a-3a34-4715-983e-78e9841698ef'
);

-- Notification 9: Urgent - VIP customer
INSERT INTO notifications (id, type, channel, notifiable_id, notifiable_type, ticket_id, title, message, status, priority, created_at, updated_at, action_url)
VALUES (
    gen_random_uuid(),
    'vip_ticket',
    'in_app',
    '9a8cb27e-75bd-48b8-b3cf-5093de345786',
    'user',
    '27ba0e2e-7c23-4d02-bc12-70e8542c50b8',
    'VIP Customer: Immediate attention required',
    'VIP customer submitted a ticket. Priority handling required.',
    'sent',
    'urgent',
    NOW() - INTERVAL '6 hours',
    NOW() - INTERVAL '6 hours',
    '/tickets/27ba0e2e-7c23-4d02-bc12-70e8542c50b8'
);

-- Notification 10: High - Multiple replies
INSERT INTO notifications (id, type, channel, notifiable_id, notifiable_type, ticket_id, title, message, status, priority, created_at, updated_at, action_url)
VALUES (
    gen_random_uuid(),
    'multiple_replies',
    'in_app',
    '9a8cb27e-75bd-48b8-b3cf-5093de345786',
    'user',
    'c22dfde9-dd92-43ec-ae74-1c9ede6816fb',
    'Customer sent 3 follow-up messages',
    'TKT-001160 has received multiple customer replies. Please review urgently.',
    'sent',
    'high',
    NOW() - INTERVAL '7 hours',
    NOW() - INTERVAL '7 hours',
    '/tickets/c22dfde9-dd92-43ec-ae74-1c9ede6816fb'
);

-- Notification 11: Normal - Ticket reassigned
INSERT INTO notifications (id, type, channel, notifiable_id, notifiable_type, ticket_id, title, message, status, priority, created_at, updated_at, action_url)
VALUES (
    gen_random_uuid(),
    'ticket_reassigned',
    'in_app',
    '9a8cb27e-75bd-48b8-b3cf-5093de345786',
    'user',
    'fab25e7a-3a34-4715-983e-78e9841698ef',
    'Ticket reassigned to you',
    'TKT-001167 was reassigned to you by supervisor for follow-up.',
    'sent',
    'normal',
    NOW() - INTERVAL '8 hours',
    NOW() - INTERVAL '8 hours',
    '/tickets/fab25e7a-3a34-4715-983e-78e9841698ef'
);

-- Notification 12: Normal - Internal note
INSERT INTO notifications (id, type, channel, notifiable_id, notifiable_type, ticket_id, title, message, status, priority, created_at, updated_at, action_url)
VALUES (
    gen_random_uuid(),
    'internal_note',
    'in_app',
    '9a8cb27e-75bd-48b8-b3cf-5093de345786',
    'user',
    '27ba0e2e-7c23-4d02-bc12-70e8542c50b8',
    'Internal note added',
    'Supervisor added a note: "Please escalate to engineering if issue persists."',
    'sent',
    'normal',
    NOW() - INTERVAL '9 hours',
    NOW() - INTERVAL '9 hours',
    '/tickets/27ba0e2e-7c23-4d02-bc12-70e8542c50b8'
);
