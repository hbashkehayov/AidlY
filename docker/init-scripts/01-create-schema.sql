-- Enable UUID extension
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- Create custom types
CREATE TYPE ticket_status AS ENUM (
    'new',
    'open',
    'pending',
    'on_hold',
    'resolved',
    'closed',
    'cancelled'
);

CREATE TYPE ticket_priority AS ENUM (
    'low',
    'medium',
    'high',
    'urgent'
);

CREATE TYPE ticket_source AS ENUM (
    'email',
    'web_form',
    'chat',
    'phone',
    'social_media',
    'api',
    'internal'
);

-- Create departments table first (referenced by users)
CREATE TABLE IF NOT EXISTS departments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    description TEXT,
    manager_id UUID,
    parent_department_id UUID,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_department_id) REFERENCES departments(id)
);

-- Create users table (Native authentication)
CREATE TABLE IF NOT EXISTS users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    avatar_url TEXT,
    role VARCHAR(50) NOT NULL,
    department_id UUID,

    -- Authentication fields
    email_verified_at TIMESTAMP,
    remember_token VARCHAR(100),
    two_factor_secret TEXT,
    two_factor_enabled BOOLEAN DEFAULT false,

    -- Status fields
    is_active BOOLEAN DEFAULT true,
    last_login_at TIMESTAMP,
    last_login_ip INET,
    login_attempts INTEGER DEFAULT 0,
    locked_until TIMESTAMP,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id)
);

-- Add foreign key for manager_id in departments
ALTER TABLE departments
ADD CONSTRAINT fk_departments_manager
FOREIGN KEY (manager_id) REFERENCES users(id);

-- Password Reset Tokens
CREATE TABLE IF NOT EXISTS password_resets (
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (email, token)
);

-- User Sessions
CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(255) PRIMARY KEY,
    user_id UUID NOT NULL,
    ip_address INET,
    user_agent TEXT,
    payload TEXT NOT NULL,
    last_activity TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Permissions
CREATE TABLE IF NOT EXISTS permissions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    resource VARCHAR(255) NOT NULL,
    action VARCHAR(50) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(resource, action)
);

-- Role Permissions
CREATE TABLE IF NOT EXISTS role_permissions (
    role VARCHAR(50) NOT NULL,
    permission_id UUID NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (role, permission_id),
    FOREIGN KEY (permission_id) REFERENCES permissions(id)
);

-- Clients/Customers
CREATE TABLE IF NOT EXISTS clients (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email VARCHAR(255) NOT NULL,
    name VARCHAR(255),
    company VARCHAR(255),
    phone VARCHAR(50),
    mobile VARCHAR(50),

    -- Additional Info
    avatar_url TEXT,
    timezone VARCHAR(50),
    language VARCHAR(10) DEFAULT 'en',

    -- Address
    address_line1 VARCHAR(255),
    address_line2 VARCHAR(255),
    city VARCHAR(100),
    state VARCHAR(100),
    country VARCHAR(100),
    postal_code VARCHAR(20),

    -- CRM Fields
    crm_id VARCHAR(255),
    crm_type VARCHAR(50),
    lead_score INTEGER,
    lifetime_value DECIMAL(12,2),

    -- Preferences
    notification_preferences JSONB,
    custom_fields JSONB,
    tags TEXT[],

    -- Status
    is_vip BOOLEAN DEFAULT false,
    is_blocked BOOLEAN DEFAULT false,
    is_deleted BOOLEAN DEFAULT false,

    -- Timestamps
    first_contact_at TIMESTAMP,
    last_contact_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE(email)
);

-- Categories for Tickets
CREATE TABLE IF NOT EXISTS categories (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    description TEXT,
    parent_category_id UUID,
    icon VARCHAR(50),
    color VARCHAR(7),
    is_active BOOLEAN DEFAULT true,
    display_order INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (parent_category_id) REFERENCES categories(id)
);

-- Business Hours Configuration
CREATE TABLE IF NOT EXISTS business_hours (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    timezone VARCHAR(50) NOT NULL,
    schedule JSONB NOT NULL,
    is_default BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- SLA Policies
CREATE TABLE IF NOT EXISTS sla_policies (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    description TEXT,

    -- Conditions
    priority_levels ticket_priority[],
    category_ids UUID[],
    client_ids UUID[],

    -- Targets (in minutes)
    first_response_time INTEGER,
    next_response_time INTEGER,
    resolution_time INTEGER,

    -- Business Hours
    business_hours_id UUID,

    is_default BOOLEAN DEFAULT false,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (business_hours_id) REFERENCES business_hours(id)
);

-- Main Tickets Table
CREATE TABLE IF NOT EXISTS tickets (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ticket_number VARCHAR(50) UNIQUE NOT NULL,
    subject VARCHAR(500) NOT NULL,
    description TEXT NOT NULL,
    status ticket_status DEFAULT 'new',
    priority ticket_priority DEFAULT 'medium',
    source ticket_source NOT NULL,
    client_id UUID NOT NULL,
    assigned_agent_id UUID,
    assigned_department_id UUID,
    category_id UUID,

    -- SLA Fields
    sla_policy_id UUID,
    first_response_at TIMESTAMP,
    first_response_due_at TIMESTAMP,
    resolution_due_at TIMESTAMP,
    resolved_at TIMESTAMP,
    closed_at TIMESTAMP,

    -- AI Integration Fields (for future use)
    ai_suggestion TEXT,
    ai_confidence_score DECIMAL(3,2),
    ai_suggested_category_id UUID,
    ai_suggested_priority ticket_priority,
    ai_processed_at TIMESTAMP,
    ai_provider VARCHAR(50),
    ai_model_version VARCHAR(50),
    ai_webhook_url TEXT,

    -- Metadata
    tags TEXT[],
    custom_fields JSONB,
    is_spam BOOLEAN DEFAULT false,
    is_deleted BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (assigned_agent_id) REFERENCES users(id),
    FOREIGN KEY (assigned_department_id) REFERENCES departments(id),
    FOREIGN KEY (category_id) REFERENCES categories(id),
    FOREIGN KEY (sla_policy_id) REFERENCES sla_policies(id)
);

-- Create indexes for better performance
CREATE INDEX idx_tickets_status ON tickets(status);
CREATE INDEX idx_tickets_client_id ON tickets(client_id);
CREATE INDEX idx_tickets_assigned_agent ON tickets(assigned_agent_id);
CREATE INDEX idx_tickets_created_at ON tickets(created_at DESC);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_clients_email ON clients(email);

-- Create updated_at trigger function
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Add updated_at triggers to relevant tables
CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_clients_updated_at BEFORE UPDATE ON clients
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_tickets_updated_at BEFORE UPDATE ON tickets
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();