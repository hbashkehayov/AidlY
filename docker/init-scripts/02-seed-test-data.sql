-- Seed Test Data for AidlY Database
-- This script creates sample data for testing and development

-- Insert Departments
INSERT INTO departments (id, name, description) VALUES
  ('550e8400-e29b-41d4-a716-446655440001', 'Customer Support', 'Main customer support department'),
  ('550e8400-e29b-41d4-a716-446655440002', 'Technical Support', 'Technical and IT support department'),
  ('550e8400-e29b-41d4-a716-446655440003', 'Sales Support', 'Sales and billing support'),
  ('550e8400-e29b-41d4-a716-446655440004', 'Management', 'Management and administration');

-- Insert Users (password for all: "password123" - hashed with bcrypt)
-- Note: In production, use proper password hashing
INSERT INTO users (id, email, password_hash, name, role, department_id, is_active, email_verified_at) VALUES
  ('550e8400-e29b-41d4-a716-446655440010', 'admin@aidly.com', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'Admin User', 'admin', '550e8400-e29b-41d4-a716-446655440004', true, NOW()),
  ('550e8400-e29b-41d4-a716-446655440011', 'supervisor@aidly.com', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'John Supervisor', 'supervisor', '550e8400-e29b-41d4-a716-446655440001', true, NOW()),
  ('550e8400-e29b-41d4-a716-446655440012', 'agent1@aidly.com', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'Alice Agent', 'agent', '550e8400-e29b-41d4-a716-446655440001', true, NOW()),
  ('550e8400-e29b-41d4-a716-446655440013', 'agent2@aidly.com', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'Bob Agent', 'agent', '550e8400-e29b-41d4-a716-446655440002', true, NOW()),
  ('550e8400-e29b-41d4-a716-446655440014', 'agent3@aidly.com', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'Charlie Agent', 'agent', '550e8400-e29b-41d4-a716-446655440003', true, NOW()),
  ('550e8400-e29b-41d4-a716-446655440015', 'customer@example.com', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'Demo Customer', 'customer', NULL, true, NOW());

-- Update department managers
UPDATE departments SET manager_id = '550e8400-e29b-41d4-a716-446655440011' WHERE id = '550e8400-e29b-41d4-a716-446655440001';
UPDATE departments SET manager_id = '550e8400-e29b-41d4-a716-446655440010' WHERE id = '550e8400-e29b-41d4-a716-446655440004';

-- Insert Permissions
INSERT INTO permissions (id, resource, action, description) VALUES
  -- Ticket permissions
  ('550e8400-e29b-41d4-a716-446655440020', 'tickets', 'create', 'Create new tickets'),
  ('550e8400-e29b-41d4-a716-446655440021', 'tickets', 'read', 'View tickets'),
  ('550e8400-e29b-41d4-a716-446655440022', 'tickets', 'update', 'Update tickets'),
  ('550e8400-e29b-41d4-a716-446655440023', 'tickets', 'delete', 'Delete tickets'),
  ('550e8400-e29b-41d4-a716-446655440024', 'tickets', 'assign', 'Assign tickets to agents'),

  -- User permissions
  ('550e8400-e29b-41d4-a716-446655440025', 'users', 'create', 'Create new users'),
  ('550e8400-e29b-41d4-a716-446655440026', 'users', 'read', 'View users'),
  ('550e8400-e29b-41d4-a716-446655440027', 'users', 'update', 'Update users'),
  ('550e8400-e29b-41d4-a716-446655440028', 'users', 'delete', 'Delete users'),

  -- Client permissions
  ('550e8400-e29b-41d4-a716-446655440029', 'clients', 'create', 'Create new clients'),
  ('550e8400-e29b-41d4-a716-446655440030', 'clients', 'read', 'View clients'),
  ('550e8400-e29b-41d4-a716-446655440031', 'clients', 'update', 'Update clients'),
  ('550e8400-e29b-41d4-a716-446655440032', 'clients', 'delete', 'Delete clients'),

  -- Report permissions
  ('550e8400-e29b-41d4-a716-446655440033', 'reports', 'view', 'View reports'),
  ('550e8400-e29b-41d4-a716-446655440034', 'reports', 'export', 'Export reports'),

  -- Settings permissions
  ('550e8400-e29b-41d4-a716-446655440035', 'settings', 'manage', 'Manage system settings');

-- Insert Role Permissions
-- Admin has all permissions (implicitly handled in middleware)

-- Supervisor permissions
INSERT INTO role_permissions (role, permission_id) VALUES
  ('supervisor', '550e8400-e29b-41d4-a716-446655440020'), -- tickets:create
  ('supervisor', '550e8400-e29b-41d4-a716-446655440021'), -- tickets:read
  ('supervisor', '550e8400-e29b-41d4-a716-446655440022'), -- tickets:update
  ('supervisor', '550e8400-e29b-41d4-a716-446655440024'), -- tickets:assign
  ('supervisor', '550e8400-e29b-41d4-a716-446655440026'), -- users:read
  ('supervisor', '550e8400-e29b-41d4-a716-446655440030'), -- clients:read
  ('supervisor', '550e8400-e29b-41d4-a716-446655440031'), -- clients:update
  ('supervisor', '550e8400-e29b-41d4-a716-446655440033'), -- reports:view
  ('supervisor', '550e8400-e29b-41d4-a716-446655440034'); -- reports:export

-- Agent permissions
INSERT INTO role_permissions (role, permission_id) VALUES
  ('agent', '550e8400-e29b-41d4-a716-446655440020'), -- tickets:create
  ('agent', '550e8400-e29b-41d4-a716-446655440021'), -- tickets:read
  ('agent', '550e8400-e29b-41d4-a716-446655440022'), -- tickets:update
  ('agent', '550e8400-e29b-41d4-a716-446655440030'), -- clients:read
  ('agent', '550e8400-e29b-41d4-a716-446655440031'); -- clients:update

-- Customer permissions
INSERT INTO role_permissions (role, permission_id) VALUES
  ('customer', '550e8400-e29b-41d4-a716-446655440020'), -- tickets:create
  ('customer', '550e8400-e29b-41d4-a716-446655440021'); -- tickets:read (own tickets only)

-- Insert Clients
INSERT INTO clients (id, email, name, company, phone, timezone, language, is_vip, tags) VALUES
  ('550e8400-e29b-41d4-a716-446655440040', 'john.doe@example.com', 'John Doe', 'Acme Corp', '+1234567890', 'America/New_York', 'en', false, ARRAY['enterprise', 'priority']),
  ('550e8400-e29b-41d4-a716-446655440041', 'jane.smith@example.com', 'Jane Smith', 'Tech Solutions Inc', '+1987654321', 'Europe/London', 'en', true, ARRAY['vip', 'premium']),
  ('550e8400-e29b-41d4-a716-446655440042', 'bob.wilson@example.com', 'Bob Wilson', 'StartUp Ltd', '+1122334455', 'America/Los_Angeles', 'en', false, ARRAY['startup']),
  ('550e8400-e29b-41d4-a716-446655440043', 'alice.brown@example.com', 'Alice Brown', 'Global Enterprises', '+4455667788', 'Europe/Paris', 'fr', true, ARRAY['vip', 'international']),
  ('550e8400-e29b-41d4-a716-446655440044', 'charlie.davis@example.com', 'Charlie Davis', NULL, '+9988776655', 'Asia/Tokyo', 'en', false, NULL);

-- Insert Categories
INSERT INTO categories (id, name, description, color, display_order, is_active) VALUES
  ('550e8400-e29b-41d4-a716-446655440050', 'Technical Issue', 'Technical problems and bugs', '#FF5733', 1, true),
  ('550e8400-e29b-41d4-a716-446655440051', 'Billing', 'Billing and payment issues', '#33FF57', 2, true),
  ('550e8400-e29b-41d4-a716-446655440052', 'Feature Request', 'New feature requests', '#3357FF', 3, true),
  ('550e8400-e29b-41d4-a716-446655440053', 'Account', 'Account related issues', '#FF33F5', 4, true),
  ('550e8400-e29b-41d4-a716-446655440054', 'General Inquiry', 'General questions', '#33FFF5', 5, true);

-- Insert Business Hours (Default - Mon-Fri 9AM-6PM UTC)
INSERT INTO business_hours (id, name, timezone, schedule, is_default) VALUES
  ('550e8400-e29b-41d4-a716-446655440060', 'Default Business Hours', 'UTC',
   '{"monday": {"start": "09:00", "end": "18:00"}, "tuesday": {"start": "09:00", "end": "18:00"}, "wednesday": {"start": "09:00", "end": "18:00"}, "thursday": {"start": "09:00", "end": "18:00"}, "friday": {"start": "09:00", "end": "18:00"}, "saturday": null, "sunday": null, "holidays": ["2024-12-25", "2024-01-01"]}',
   true);

-- Insert SLA Policies
INSERT INTO sla_policies (id, name, description, priority_levels, first_response_time, next_response_time, resolution_time, business_hours_id, is_default, is_active) VALUES
  ('550e8400-e29b-41d4-a716-446655440070', 'Standard SLA', 'Default SLA for all tickets', ARRAY['low', 'medium']::ticket_priority[], 120, 240, 1440, '550e8400-e29b-41d4-a716-446655440060', true, true),
  ('550e8400-e29b-41d4-a716-446655440071', 'Priority SLA', 'SLA for high priority tickets', ARRAY['high', 'urgent']::ticket_priority[], 30, 60, 480, '550e8400-e29b-41d4-a716-446655440060', false, true);

-- Insert Sample Tickets
INSERT INTO tickets (id, ticket_number, subject, description, status, priority, source, client_id, assigned_agent_id, category_id, sla_policy_id, tags, created_at, updated_at) VALUES
  ('550e8400-e29b-41d4-a716-446655440080', 'TKT-001', 'Cannot login to account', 'I am unable to login to my account. Getting error message "Invalid credentials"', 'open', 'high', 'email', '550e8400-e29b-41d4-a716-446655440040', '550e8400-e29b-41d4-a716-446655440012', '550e8400-e29b-41d4-a716-446655440053', '550e8400-e29b-41d4-a716-446655440071', ARRAY['login', 'urgent'], NOW() - INTERVAL '2 days', NOW() - INTERVAL '1 day'),

  ('550e8400-e29b-41d4-a716-446655440081', 'TKT-002', 'Invoice not received', 'I haven''t received my invoice for last month', 'pending', 'medium', 'web_form', '550e8400-e29b-41d4-a716-446655440041', '550e8400-e29b-41d4-a716-446655440014', '550e8400-e29b-41d4-a716-446655440051', '550e8400-e29b-41d4-a716-446655440070', ARRAY['billing', 'invoice'], NOW() - INTERVAL '1 day', NOW() - INTERVAL '6 hours'),

  ('550e8400-e29b-41d4-a716-446655440082', 'TKT-003', 'Feature request: Dark mode', 'Would love to have a dark mode option in the dashboard', 'new', 'low', 'email', '550e8400-e29b-41d4-a716-446655440042', NULL, '550e8400-e29b-41d4-a716-446655440052', '550e8400-e29b-41d4-a716-446655440070', ARRAY['feature', 'ui'], NOW() - INTERVAL '3 hours', NOW() - INTERVAL '3 hours'),

  ('550e8400-e29b-41d4-a716-446655440083', 'TKT-004', 'Application crash on export', 'The application crashes when trying to export reports to PDF', 'open', 'urgent', 'phone', '550e8400-e29b-41d4-a716-446655440043', '550e8400-e29b-41d4-a716-446655440013', '550e8400-e29b-41d4-a716-446655440050', '550e8400-e29b-41d4-a716-446655440071', ARRAY['bug', 'export', 'crash'], NOW() - INTERVAL '5 hours', NOW() - INTERVAL '2 hours'),

  ('550e8400-e29b-41d4-a716-446655440084', 'TKT-005', 'How to integrate API?', 'Need documentation on how to integrate your API with our system', 'resolved', 'medium', 'chat', '550e8400-e29b-41d4-a716-446655440044', '550e8400-e29b-41d4-a716-446655440012', '550e8400-e29b-41d4-a716-446655440054', '550e8400-e29b-41d4-a716-446655440070', ARRAY['api', 'integration', 'documentation'], NOW() - INTERVAL '1 week', NOW() - INTERVAL '3 days');

-- Update ticket resolved/closed timestamps
UPDATE tickets SET resolved_at = NOW() - INTERVAL '3 days', closed_at = NOW() - INTERVAL '3 days' WHERE id = '550e8400-e29b-41d4-a716-446655440084';

-- Insert Ticket Comments
INSERT INTO ticket_comments (id, ticket_id, user_id, content, is_internal_note, created_at) VALUES
  ('550e8400-e29b-41d4-a716-446655440090', '550e8400-e29b-41d4-a716-446655440080', '550e8400-e29b-41d4-a716-446655440012', 'I''ve reset your password. Please check your email for the new temporary password.', false, NOW() - INTERVAL '1 day'),
  ('550e8400-e29b-41d4-a716-446655440091', '550e8400-e29b-41d4-a716-446655440080', '550e8400-e29b-41d4-a716-446655440012', 'Customer has VIP status - prioritizing this ticket', true, NOW() - INTERVAL '1 day'),
  ('550e8400-e29b-41d4-a716-446655440092', '550e8400-e29b-41d4-a716-446655440081', '550e8400-e29b-41d4-a716-446655440014', 'Checking with billing department for the invoice.', false, NOW() - INTERVAL '6 hours'),
  ('550e8400-e29b-41d4-a716-446655440093', '550e8400-e29b-41d4-a716-446655440083', '550e8400-e29b-41d4-a716-446655440013', 'This is a critical bug. Escalating to development team.', true, NOW() - INTERVAL '2 hours'),
  ('550e8400-e29b-41d4-a716-446655440094', '550e8400-e29b-41d4-a716-446655440084', '550e8400-e29b-41d4-a716-446655440012', 'Here''s the API documentation link: https://docs.aidly.com/api', false, NOW() - INTERVAL '4 days');

-- Insert Ticket History samples
INSERT INTO ticket_history (id, ticket_id, user_id, action, field_name, old_value, new_value, created_at) VALUES
  ('550e8400-e29b-41d4-a716-446655440100', '550e8400-e29b-41d4-a716-446655440080', '550e8400-e29b-41d4-a716-446655440012', 'status_changed', 'status', 'new', 'open', NOW() - INTERVAL '2 days'),
  ('550e8400-e29b-41d4-a716-446655440101', '550e8400-e29b-41d4-a716-446655440080', '550e8400-e29b-41d4-a716-446655440012', 'assigned', 'assigned_agent_id', NULL, '550e8400-e29b-41d4-a716-446655440012', NOW() - INTERVAL '2 days'),
  ('550e8400-e29b-41d4-a716-446655440102', '550e8400-e29b-41d4-a716-446655440081', '550e8400-e29b-41d4-a716-446655440014', 'status_changed', 'status', 'new', 'pending', NOW() - INTERVAL '6 hours'),
  ('550e8400-e29b-41d4-a716-446655440103', '550e8400-e29b-41d4-a716-446655440084', '550e8400-e29b-41d4-a716-446655440012', 'status_changed', 'status', 'open', 'resolved', NOW() - INTERVAL '3 days');

-- Summary of test data
DO $$
BEGIN
    RAISE NOTICE '';
    RAISE NOTICE '=== Test Data Successfully Seeded ===';
    RAISE NOTICE '';
    RAISE NOTICE 'Users Created:';
    RAISE NOTICE '  - admin@aidly.com (Admin)';
    RAISE NOTICE '  - supervisor@aidly.com (Supervisor)';
    RAISE NOTICE '  - agent1@aidly.com (Agent)';
    RAISE NOTICE '  - agent2@aidly.com (Agent)';
    RAISE NOTICE '  - agent3@aidly.com (Agent)';
    RAISE NOTICE '  - customer@example.com (Customer)';
    RAISE NOTICE '';
    RAISE NOTICE 'Password for all users: password123';
    RAISE NOTICE '';
    RAISE NOTICE 'Tickets Created: 5';
    RAISE NOTICE 'Clients Created: 5';
    RAISE NOTICE 'Departments Created: 4';
    RAISE NOTICE 'Categories Created: 5';
    RAISE NOTICE '';
    RAISE NOTICE '=== Ready for Testing ===';
END $$;