# AidlY System Manual

## Complete Technical Documentation and Developer Guide

**Version:** 1.0.0
**Last Updated:** October 2025
**Status:** Production-Ready

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [System Architecture](#2-system-architecture)
3. [Technology Stack](#3-technology-stack)
4. [Infrastructure Components](#4-infrastructure-components)
5. [Microservices Deep Dive](#5-microservices-deep-dive)
6. [Database Architecture](#6-database-architecture)
7. [Frontend Application](#7-frontend-application)
8. [Authentication & Authorization](#8-authentication--authorization)
9. [Inter-Service Communication](#9-inter-service-communication)
10. [Core Workflows](#10-core-workflows)
11. [API Reference](#11-api-reference)
12. [Configuration Guide](#12-configuration-guide)
13. [Deployment](#13-deployment)
14. [Development Guide](#14-development-guide)
15. [Troubleshooting](#15-troubleshooting)

---

## 1. Introduction

### 1.1 What is AidlY?

AidlY is a modern, enterprise-grade customer support platform designed to streamline ticket management, customer interactions, and support team operations. Built with a microservices architecture, it provides scalability, maintainability, and flexibility for growing businesses.

### 1.2 Key Features

- **Ticket Management**: Complete lifecycle management from creation to resolution
- **Email Integration**: Automatic ticket creation from emails with threading support
- **AI-Powered Assistance**: Auto-categorization, response suggestions, sentiment analysis
- **Real-time Notifications**: Multi-channel notifications (in-app, email, Slack, SMS)
- **Analytics & Reporting**: Comprehensive dashboards, custom reports, data exports
- **Client Management**: Customer profiles, VIP status, merge duplicates
- **Team Management**: Agent workload distribution, auto-assignment
- **SLA Tracking**: Monitor response times and resolution deadlines

### 1.3 Target Users

- **Agents**: Handle customer support tickets
- **Supervisors**: Monitor team performance, assign tickets
- **Admins**: Full system access, configuration, user management
- **Customers**: Submit tickets, track status (via email)

---

## 2. System Architecture

### 2.1 Architecture Overview

AidlY follows a **microservices architecture** pattern with clear separation of concerns:

```
┌─────────────────────────────────────────────────────────┐
│                    Frontend Layer                        │
│              Next.js 15 + TypeScript + React             │
└────────────────────┬────────────────────────────────────┘
                     │ HTTP/REST
                     │
┌────────────────────▼────────────────────────────────────┐
│                  API Gateway (Future)                    │
│              Kong / Nginx (Optional)                     │
└────────────────────┬────────────────────────────────────┘
                     │
        ┌────────────┴────────────┐
        │                         │
┌───────▼──────────┐    ┌────────▼─────────┐
│  Service Layer   │    │  Service Layer   │
│                  │    │                  │
│ ┌──────────────┐ │    │ ┌──────────────┐ │
│ │ Auth Service │ │    │ │Email Service │ │
│ │   (8001)     │ │    │ │   (8005)     │ │
│ └──────────────┘ │    │ └──────────────┘ │
│                  │    │                  │
│ ┌──────────────┐ │    │ ┌──────────────┐ │
│ │Ticket Service│ │    │ │Notification  │ │
│ │   (8002)     │ │    │ │   (8004)     │ │
│ └──────────────┘ │    │ └──────────────┘ │
│                  │    │                  │
│ ┌──────────────┐ │    │ ┌──────────────┐ │
│ │Client Service│ │    │ │AI Integration│ │
│ │   (8003)     │ │    │ │   (8006)     │ │
│ └──────────────┘ │    │ └──────────────┘ │
│                  │    │                  │
│                  │    │ ┌──────────────┐ │
│                  │    │ │Analytics Svc │ │
│                  │    │ │   (8007)     │ │
│                  │    │ └──────────────┘ │
└──────────────────┘    └──────────────────┘
        │                         │
        └────────────┬────────────┘
                     │
┌────────────────────▼────────────────────────────────────┐
│                Infrastructure Layer                      │
│                                                          │
│  ┌──────────────┐  ┌──────────┐  ┌─────────────────┐  │
│  │  PostgreSQL  │  │  Redis   │  │  MinIO (S3)     │  │
│  │   Database   │  │  Cache   │  │  File Storage   │  │
│  └──────────────┘  └──────────┘  └─────────────────┘  │
└─────────────────────────────────────────────────────────┘
```

### 2.2 Architecture Principles

1. **Service Independence**: Each microservice can be developed, deployed, and scaled independently
2. **Single Responsibility**: Each service has a well-defined domain
3. **Database per Service**: Shared PostgreSQL but logical separation
4. **Stateless Services**: Services don't maintain session state (stored in Redis)
5. **API-First Design**: All functionality exposed via RESTful APIs
6. **Event-Driven Communication**: Webhooks for async operations

### 2.3 Design Patterns Used

- **Microservices Pattern**: Independent, loosely coupled services
- **API Gateway Pattern**: Future Kong/Nginx for routing (currently direct access)
- **Repository Pattern**: Data access abstraction in Lumen
- **Service Layer Pattern**: Business logic separation
- **Middleware Pattern**: Cross-cutting concerns (auth, CORS, logging)
- **Factory Pattern**: Email providers, AI providers

---

## 3. Technology Stack

### 3.1 Backend Technologies

| Component | Technology | Version | Purpose |
|-----------|-----------|---------|---------|
| Framework | Lumen | 10.x | Lightweight PHP microframework |
| Database | PostgreSQL | 15 | Primary data store |
| Cache/Queue | Redis | 7 | Caching, sessions, job queues |
| Object Storage | MinIO | Latest | S3-compatible file storage |
| Authentication | JWT | - | Token-based auth |
| Container | Docker | Latest | Service containerization |

### 3.2 Frontend Technologies

| Component | Technology | Version | Purpose |
|-----------|-----------|---------|---------|
| Framework | Next.js | 15.5.4 | React framework with SSR |
| Language | TypeScript | 5.x | Type-safe JavaScript |
| Styling | Tailwind CSS | 4.x | Utility-first CSS |
| UI Components | Radix UI | Latest | Accessible components |
| State Management | TanStack Query | 5.x | Server state management |
| State Management | Zustand | 5.x | Client state management |
| Forms | React Hook Form | 7.x | Form handling |
| Validation | Zod | 4.x | Schema validation |
| Rich Text | Tiptap | 3.x | WYSIWYG editor |
| Charts | Recharts | 3.x | Data visualization |

### 3.3 DevOps & Infrastructure

- **Containerization**: Docker & Docker Compose
- **Version Control**: Git
- **CI/CD**: (To be configured)
- **Monitoring**: (To be configured - Sentry ready)

---

## 4. Infrastructure Components

### 4.1 PostgreSQL Database

**Purpose**: Primary relational database for all services

**Configuration**:
- Port: `5432`
- Database: `aidly`
- User: `aidly_user`
- Password: `aidly_secret_2024` (change in production)

**Features**:
- UUID support via `uuid-ossp` extension
- Custom ENUM types for status/priority
- Automatic `updated_at` triggers
- Comprehensive indexing for performance

**Health Check**:
```bash
pg_isready -U aidly_user -d aidly
```

### 4.2 Redis Cache

**Purpose**: Caching, session storage, job queuing

**Configuration**:
- Port: `6379`
- Password: `redis_secret_2024`

**Usage**:
- Session storage for user authentication
- Cache layer for frequently accessed data
- Queue driver for background jobs
- Real-time notification pub/sub

**Common Keys**:
- `session:*` - User sessions
- `cache:*` - Cached data
- `queue:*` - Job queues

### 4.3 MinIO Object Storage

**Purpose**: S3-compatible object storage for file attachments

**Configuration**:
- API Port: `9000`
- Console Port: `9001`
- Root User: `aidly_minio_admin`
- Root Password: `minio_secret_2024`

**Buckets**:
- `aidly-attachments` - Ticket and email attachments (public)
- `aidly-avatars` - User and client avatars (public)
- `aidly-exports` - Report exports (private)

**Access**:
- Console UI: `http://localhost:9001`
- API: `http://localhost:9000`

### 4.4 Network Configuration

All services run on a custom Docker bridge network:
- **Network Name**: `aidly-network`
- **Driver**: bridge
- **DNS**: Docker's internal DNS for service discovery

Services can communicate using service names:
- `http://auth-service:8001`
- `http://ticket-service:8002`
- etc.

---

## 5. Microservices Deep Dive

### 5.1 Auth Service (Port 8001)

**Domain**: User authentication, authorization, and user management

#### 5.1.1 Responsibilities

- User registration and login
- JWT token generation and validation
- Password reset and recovery
- Two-factor authentication (2FA)
- Role-Based Access Control (RBAC)
- Permission management
- Session management
- User profile management

#### 5.1.2 Key Endpoints

**Public Endpoints**:
```
POST   /api/v1/auth/register          - Register new user
POST   /api/v1/auth/login             - Login and get JWT token
POST   /api/v1/auth/refresh           - Refresh JWT token
POST   /api/v1/auth/forgot-password   - Request password reset
POST   /api/v1/auth/reset-password    - Reset password with token
```

**Protected Endpoints**:
```
POST   /api/v1/auth/logout            - Logout (invalidate token)
GET    /api/v1/auth/me                - Get current user info
PUT    /api/v1/auth/update-profile    - Update user profile
POST   /api/v1/auth/change-password   - Change password

GET    /api/v1/users                  - List all users
GET    /api/v1/users/{id}             - Get user details
POST   /api/v1/users                  - Create user (admin)
PUT    /api/v1/users/{id}             - Update user (admin)
DELETE /api/v1/users/{id}             - Delete user (admin)

GET    /api/v1/roles                  - List roles
GET    /api/v1/permissions            - List permissions
```

#### 5.1.3 Authentication Flow

```
1. User submits credentials
   ↓
2. Auth Service validates against users table
   ↓
3. Generate JWT token with claims:
   - user_id
   - email
   - role
   - exp (expiration)
   ↓
4. Return token + user data
   ↓
5. Frontend stores token in localStorage
   ↓
6. Subsequent requests include: Authorization: Bearer <token>
   ↓
7. Services validate JWT signature and claims
```

#### 5.1.4 Role Hierarchy

```
Admin (Full Access)
  ↓
Supervisor (Manage agents, view all tickets)
  ↓
Agent (Handle assigned tickets)
```

#### 5.1.5 Database Tables

- `users` - User accounts with hashed passwords
- `sessions` - Active user sessions
- `password_resets` - Temporary reset tokens
- `permissions` - System permissions (resource + action)
- `role_permissions` - Role-to-permission mappings
- `departments` - Organizational structure

#### 5.1.6 Security Features

- Bcrypt password hashing
- JWT with HS256 algorithm
- Token expiration (60 minutes default)
- Refresh tokens (14 days default)
- Failed login attempt tracking
- Account lockout after 5 failed attempts
- Email verification support
- Two-factor authentication (TOTP)

---

### 5.2 Ticket Service (Port 8002)

**Domain**: Core ticket management and lifecycle

#### 5.2.1 Responsibilities

- Ticket CRUD operations
- Ticket assignment to agents
- Comment/reply management
- Category management
- Attachment handling
- Ticket history/audit trail
- Agent workload tracking
- Auto-assignment algorithms
- SLA tracking

#### 5.2.2 Ticket Lifecycle

```
new (created)
  ↓
open (agent viewing)
  ↓
pending (waiting for customer)
  ↓
on_hold (temporarily paused)
  ↓
resolved (issue fixed)
  ↓
closed (completed)

Alternative: cancelled (dismissed)
```

#### 5.2.3 Key Endpoints

**Public/Inter-Service**:
```
GET    /api/v1/public/tickets                    - List tickets
POST   /api/v1/public/tickets                    - Create ticket (from email)
GET    /api/v1/public/tickets/{id}               - Get ticket details
GET    /api/v1/public/tickets/by-number/{num}    - Find by ticket number
GET    /api/v1/public/tickets/by-message-id      - Find by email Message-ID
POST   /api/v1/public/tickets/{id}/comments      - Add comment
POST   /api/v1/public/tickets/{id}/message-id    - Store email Message-ID
```

**Protected**:
```
PUT    /api/v1/tickets/{id}                      - Update ticket
DELETE /api/v1/tickets/{id}                      - Delete ticket
POST   /api/v1/tickets/{id}/assign               - Assign to agent
GET    /api/v1/tickets/{id}/history              - Get audit trail

GET    /api/v1/stats/dashboard                   - Dashboard stats
GET    /api/v1/stats/trends                      - Ticket trends
GET    /api/v1/stats/recent                      - Recent tickets

GET    /api/v1/categories                        - List categories
POST   /api/v1/categories                        - Create category (admin)

GET    /api/v1/assignments/agents/workload       - Agent workload
POST   /api/v1/assignments/auto-assign           - Auto-assign tickets
POST   /api/v1/assignments/rebalance             - Rebalance workload
```

#### 5.2.4 Ticket Priority Levels

```
urgent   - Critical issues, immediate attention
high     - Important issues, high priority
medium   - Standard issues (default)
low      - Minor issues, can wait
```

#### 5.2.5 Database Tables

- `tickets` - Main ticket table
  - Core fields: subject, description, status, priority
  - Assignment: client_id, assigned_agent_id, assigned_department_id
  - SLA: first_response_at, resolution_due_at
  - AI: ai_suggestion, ai_confidence_score, ai_suggested_category_id
  - Metadata: tags, custom_fields, is_spam

- `ticket_comments` - Comments and replies
  - Fields: ticket_id, user_id, client_id, comment, is_internal, is_read
  - Support for both agent and client comments

- `ticket_history` - Audit trail
  - Fields: ticket_id, user_id, action, old_value, new_value, metadata

- `categories` - Ticket categorization
  - Hierarchical structure with parent_category_id
  - Fields: name, description, icon, color, display_order

- `attachments` - File attachments
  - Links to tickets and comments
  - Storage in MinIO

- `ticket_relationships` - Related/merged tickets
  - Types: merged, related, duplicate

#### 5.2.6 AI Integration Fields

Tickets include AI-ready fields:
- `ai_suggestion` - AI-generated response suggestion
- `ai_confidence_score` - Confidence level (0.00 to 1.00)
- `ai_suggested_category_id` - AI-recommended category
- `ai_suggested_priority` - AI-recommended priority
- `ai_processed_at` - When AI processing completed
- `ai_provider` - Which AI provider was used (openai, anthropic, gemini)
- `ai_model_version` - Model version for tracking

---

### 5.3 Client Service (Port 8003)

**Domain**: Customer/client management

#### 5.3.1 Responsibilities

- Client profile management
- Contact information storage
- VIP and blocked status management
- Client tagging
- Internal notes about clients
- Duplicate client detection and merging
- Client ticket history aggregation
- CRM integration preparation

#### 5.3.2 Key Endpoints

```
GET    /api/v1/clients                        - List all clients
POST   /api/v1/clients                        - Create client
GET    /api/v1/clients/{id}                   - Get client details
PUT    /api/v1/clients/{id}                   - Update client
DELETE /api/v1/clients/{id}                   - Delete client

POST   /api/v1/clients/{id}/block             - Toggle block status
POST   /api/v1/clients/{id}/vip               - Toggle VIP status
POST   /api/v1/clients/{id}/tags              - Add tag
DELETE /api/v1/clients/{id}/tags              - Remove tag

GET    /api/v1/clients/{id}/tickets           - Get client's tickets
GET    /api/v1/clients/{id}/notes             - Get internal notes
POST   /api/v1/clients/{id}/notes             - Add note

POST   /api/v1/clients/merge                  - Merge duplicate clients
POST   /api/v1/clients/merge/preview          - Preview merge operation
```

#### 5.3.3 Client Data Structure

**Basic Information**:
- Email (unique identifier)
- Name
- Company
- Phone/Mobile

**Address**:
- address_line1, address_line2
- city, state, country, postal_code

**CRM Integration**:
- crm_id - External CRM ID
- crm_type - CRM system type
- lead_score - Lead scoring (0-100)
- lifetime_value - Customer lifetime value

**Status Flags**:
- is_vip - VIP customer status
- is_blocked - Blocked from creating tickets
- is_deleted - Soft delete flag

**Metadata**:
- tags - Array of tags
- custom_fields - JSONB for flexible data
- notification_preferences - JSONB for preferences

#### 5.3.4 Database Tables

- `clients` - Main client table
- `client_notes` - Internal notes (agent-only)
  - Fields: client_id, created_by, note, is_pinned
- `client_merges` - Merge history
  - Tracks which clients were merged and by whom

#### 5.3.5 Client Blocking

When a client is blocked:
- Cannot create new tickets via email
- Existing tickets remain accessible
- Email service checks blocked status before ticket creation

#### 5.3.6 VIP Handling

VIP clients receive:
- Higher priority by default
- Faster response time targets
- Special notification handling
- Visual indicators in UI

---

### 5.4 Email Service (Port 8005)

**Domain**: Email integration, IMAP/SMTP, email-to-ticket conversion

#### 5.4.1 Responsibilities

- IMAP email fetching from configured accounts
- SMTP email sending
- Email-to-ticket conversion
- Email threading (conversation tracking)
- Attachment extraction and storage
- Email templates management
- Duplicate email detection
- Gmail quick setup wizard
- Email queue management

#### 5.4.2 Key Endpoints

**Email Processing**:
```
POST   /api/v1/emails/fetch                   - Fetch new emails via IMAP
POST   /api/v1/emails/process                 - Process emails to tickets
POST   /api/v1/emails/send                    - Send email via SMTP
POST   /api/v1/emails/send-template           - Send templated email
POST   /api/v1/emails/send-notification       - Send ticket notification
GET    /api/v1/emails                         - List email queue
GET    /api/v1/emails/stats                   - Email statistics
```

**Email Accounts**:
```
GET    /api/v1/accounts                       - List email accounts
POST   /api/v1/accounts                       - Add email account
PUT    /api/v1/accounts/{id}                  - Update account
DELETE /api/v1/accounts/{id}                  - Remove account
POST   /api/v1/accounts/{id}/test-imap        - Test IMAP connection
POST   /api/v1/accounts/{id}/test-smtp        - Test SMTP connection
POST   /api/v1/accounts/{id}/fetch            - Fetch from specific account
```

**Email Templates**:
```
GET    /api/v1/templates                      - List templates
POST   /api/v1/templates                      - Create template
PUT    /api/v1/templates/{id}                 - Update template
DELETE /api/v1/templates/{id}                 - Delete template
POST   /api/v1/templates/{id}/preview         - Preview with data
POST   /api/v1/templates/create-defaults      - Create default templates
```

**Webhooks**:
```
POST   /api/v1/webhooks/ticket/comment        - Handle ticket comment webhook
POST   /api/v1/webhooks/email/incoming        - Real-time email webhook
```

**Gmail Helper**:
```
GET    /api/v1/gmail/instructions             - Setup instructions
POST   /api/v1/gmail/quick-setup              - Quick Gmail setup
POST   /api/v1/gmail/test-connection          - Test Gmail connection
```

#### 5.4.3 Email-to-Ticket Flow

```
1. IMAP Fetch
   ↓
2. Parse Email Headers
   - From, To, Subject
   - Message-ID, In-Reply-To, References
   - Date
   ↓
3. Check Threading Headers
   - If In-Reply-To exists → find parent ticket
   - If References exists → check message chain
   ↓
4. Extract Attachments
   - Save to MinIO
   - Create attachment records
   ↓
5. Find or Create Client
   - Call Client Service
   - Match by email address
   ↓
6. Create Ticket or Add Comment
   - New conversation → Create ticket
   - Reply → Add comment to existing ticket
   ↓
7. Store Message-ID
   - For future threading
   ↓
8. Notify Agent
   - Call Notification Service
```

#### 5.4.4 Email Threading

Email threading maintains conversation context by tracking:

**Headers Used**:
- `Message-ID` - Unique identifier for each email
- `In-Reply-To` - Direct parent message ID
- `References` - Full chain of message IDs

**Database Fields**:
- `email_message_id` - Incoming email's Message-ID
- `sent_message_ids` - Array of sent Message-IDs (for replies)

**Threading Logic**:
1. When email arrives, check `In-Reply-To` header
2. Query tickets by `sent_message_ids` to find parent
3. If found → Add comment to that ticket
4. If not found → Create new ticket
5. Store email's `Message-ID` for future threading

#### 5.4.5 Email Account Configuration

**IMAP Settings**:
- Host: `imap.gmail.com`, `imap.outlook.com`, etc.
- Port: `993` (SSL), `143` (TLS)
- Username: Email address or username
- Password: Encrypted password or app password
- SSL/TLS: Boolean flag

**SMTP Settings**:
- Host: `smtp.gmail.com`, `smtp.outlook.com`, etc.
- Port: `587` (TLS), `465` (SSL)
- Username: Email address
- Password: Encrypted password
- TLS: Boolean flag

**Gmail Specific**:
- Requires "App Password" (not regular password)
- IMAP must be enabled in Gmail settings
- Less secure apps: Not required with app password

#### 5.4.6 Database Tables

- `email_accounts` - IMAP/SMTP configurations
  - Encrypted passwords
  - Department assignment
  - Auto-create ticket settings

- `email_queue` - Outgoing email queue
  - Status: pending, sent, failed
  - Retry logic

- `email_templates` - Reusable templates
  - Variable substitution: `{{ticket_number}}`, `{{client_name}}`
  - Categories: ticket_created, ticket_resolved, etc.

- `sent_messages` - Tracking for threading
  - ticket_id, message_id, sent_at

---

### 5.5 Notification Service (Port 8004)

**Domain**: Multi-channel notifications and user preferences

#### 5.5.1 Responsibilities

- Send in-app notifications
- Send email notifications
- Send Slack notifications
- Send SMS notifications (Twilio)
- Real-time WebSocket notifications
- User notification preferences
- Quiet hours and DND mode
- Notification digests
- Webhook listeners for events

#### 5.5.2 Key Endpoints

**Notifications**:
```
GET    /api/v1/notifications                  - Get user's notifications
GET    /api/v1/notifications/unread           - Get unread count
POST   /api/v1/notifications                  - Send notification
POST   /api/v1/notifications/bulk             - Send bulk notifications
POST   /api/v1/notifications/{id}/read        - Mark as read
POST   /api/v1/notifications/mark-read        - Mark multiple as read
DELETE /api/v1/notifications/{id}             - Delete notification
```

**Preferences**:
```
GET    /api/v1/preferences                    - Get user preferences
PUT    /api/v1/preferences                    - Update preferences
POST   /api/v1/preferences/events/{event}     - Update event preference
POST   /api/v1/preferences/dnd                - Toggle DND mode
POST   /api/v1/preferences/quiet-hours        - Set quiet hours
POST   /api/v1/preferences/digest             - Set digest settings
```

**Webhooks** (Called by other services):
```
POST   /api/v1/webhooks/ticket-created        - New ticket notification
POST   /api/v1/webhooks/ticket-updated        - Ticket updated notification
POST   /api/v1/webhooks/ticket-assigned       - Assignment notification
POST   /api/v1/webhooks/comment-added         - New comment notification
POST   /api/v1/webhooks/sla-breach            - SLA breach alert
```

#### 5.5.3 Notification Channels

**In-App**:
- Stored in database
- Real-time via WebSocket
- Badge count in UI

**Email**:
- Via configured SMTP
- Respects quiet hours
- Can be bundled in digest

**Slack**:
- Via webhook URL
- Formatted messages with links
- Channel-specific routing

**SMS**:
- Via Twilio API
- For urgent notifications only
- Opt-in required

#### 5.5.4 Notification Events

System events that trigger notifications:

- `ticket.created` - New ticket created
- `ticket.assigned` - Ticket assigned to agent
- `ticket.updated` - Ticket status/priority changed
- `ticket.comment` - New comment added
- `ticket.resolved` - Ticket marked resolved
- `ticket.reopened` - Resolved ticket reopened
- `sla.breach` - SLA deadline missed
- `mention.user` - User mentioned in comment

#### 5.5.5 User Preferences

Users can configure per-event:
- Enable/disable notification
- Channel preferences (in-app, email, Slack, SMS)
- Frequency (instant, hourly, daily digest)

**Do Not Disturb**:
- Temporarily disable all notifications
- Duration: 1 hour, 4 hours, until tomorrow, custom

**Quiet Hours**:
- Daily schedule (e.g., 10 PM - 8 AM)
- No notifications during quiet hours
- Urgent notifications can override

**Digest Mode**:
- Bundle notifications
- Daily or weekly summary
- Configurable send time

#### 5.5.6 Database Tables

- `notifications` - All notifications
  - Fields: user_id, type, title, message, data (JSON), read_at
  - Indexes on user_id, read_at

- `notification_preferences` - User settings
  - JSONB structure for flexibility

- `notification_templates` - Message templates

---

### 5.6 AI Integration Service (Port 8006)

**Domain**: AI provider integration and intelligent features

#### 5.6.1 Responsibilities

- AI provider management (OpenAI, Anthropic, Gemini)
- Auto-write text completion for editor
- Ticket auto-categorization
- Ticket auto-prioritization
- Response suggestions
- Sentiment analysis
- Entity extraction (names, dates, etc.)
- Ticket summarization
- Knowledge base article generation
- Webhook-based async processing
- Feature flag management

#### 5.6.2 Key Endpoints

**Processing**:
```
POST   /api/v1/process/auto-write             - AI text completion
POST   /api/v1/process/ticket/categorize      - Categorize ticket
POST   /api/v1/process/ticket/prioritize      - Suggest priority
POST   /api/v1/process/ticket/suggest-response - Generate response
POST   /api/v1/process/ticket/analyze-sentiment - Analyze tone
POST   /api/v1/process/ticket/extract-entities - Extract data
POST   /api/v1/process/ticket/summarize       - Summarize content
POST   /api/v1/process/batch                  - Batch processing
```

**Configuration**:
```
GET    /api/v1/configurations                 - List AI configs
POST   /api/v1/configurations                 - Add AI provider
PUT    /api/v1/configurations/{id}            - Update config
DELETE /api/v1/configurations/{id}            - Remove config
POST   /api/v1/configurations/{id}/test-connection - Test API key
```

**Providers**:
```
GET    /api/v1/providers                      - List available providers
GET    /api/v1/providers/{provider}/status    - Provider health
GET    /api/v1/providers/{provider}/usage     - Usage statistics
GET    /api/v1/providers/{provider}/models    - Available models
```

**Features**:
```
GET    /api/v1/features/flags                 - Get feature flags
PUT    /api/v1/features/flags                 - Update flags
```

**Webhooks**:
```
POST   /api/v1/webhooks/openai                - OpenAI callback
POST   /api/v1/webhooks/anthropic             - Anthropic callback
POST   /api/v1/webhooks/gemini                - Gemini callback
POST   /api/v1/webhooks/callback/{jobId}      - Generic callback
```

#### 5.6.3 Supported AI Providers

**OpenAI**:
- Models: GPT-4, GPT-3.5-turbo
- Features: All
- API Key required

**Anthropic Claude**:
- Models: Claude 3 Opus, Sonnet, Haiku
- Features: All
- API Key required

**Google Gemini**:
- Models: Gemini Pro, Gemini Ultra
- Features: Text generation, analysis
- API Key required

**Custom Webhooks**:
- n8n integration
- Custom AI workflows
- Webhook URL + signing secret

#### 5.6.4 Auto-Write Feature

Real-time AI text completion in rich text editor:

**Request**:
```json
{
  "context": "Previous conversation context",
  "prompt": "User's partial input",
  "max_length": 200,
  "tone": "professional"
}
```

**Response**:
```json
{
  "completion": "AI-generated continuation",
  "confidence": 0.95
}
```

**Usage in Frontend**:
- Triggered by user typing or keyboard shortcut
- Inline suggestions
- Accept/reject options

#### 5.6.5 Ticket Categorization

Analyzes ticket content and suggests category:

**Request**:
```json
{
  "ticket_id": "uuid",
  "subject": "Can't login to account",
  "description": "Getting error when trying to log in..."
}
```

**Response**:
```json
{
  "suggested_category_id": "uuid",
  "category_name": "Account & Authentication",
  "confidence": 0.89,
  "reasoning": "Keywords: login, error, account"
}
```

#### 5.6.6 Response Suggestions

Generates draft responses based on ticket content:

**Request**:
```json
{
  "ticket_id": "uuid",
  "include_history": true,
  "tone": "friendly"
}
```

**Response**:
```json
{
  "suggestion": "Hi John,\n\nThank you for reaching out...",
  "confidence": 0.92,
  "alternatives": ["Alternative response 1", "Alternative response 2"]
}
```

#### 5.6.7 Feature Flags

Control AI features via environment variables:

```env
AI_FEATURE_AUTO_CATEGORIZATION=true
AI_FEATURE_AUTO_PRIORITIZATION=true
AI_FEATURE_RESPONSE_SUGGESTIONS=true
AI_FEATURE_SENTIMENT_ANALYSIS=false
```

#### 5.6.8 Database Tables

- `ai_processing_queue` - Async job queue
  - Fields: ticket_id, task_type, status, result, provider

- `ai_configurations` - Provider settings
  - Encrypted API keys

---

### 5.7 Analytics Service (Port 8007)

**Domain**: Reporting, metrics, and business intelligence

#### 5.7.1 Responsibilities

- Dashboard statistics
- Ticket volume trends
- Agent performance metrics
- SLA compliance tracking
- Custom report generation
- Data exports (CSV, Excel, PDF)
- Real-time analytics
- Event tracking
- Aggregation jobs

#### 5.7.2 Key Endpoints

**Dashboard**:
```
GET    /api/v1/dashboard/stats                - Overview statistics
GET    /api/v1/dashboard/trends               - Ticket trends (7/30/90 days)
GET    /api/v1/dashboard/activity             - Recent activity feed
GET    /api/v1/dashboard/sla-compliance       - SLA performance
GET    /api/v1/dashboard/agent-performance    - Team metrics
GET    /api/v1/dashboard/agent/{id}/metrics   - Individual agent metrics
```

**Agent Dashboard**:
```
GET    /api/v1/dashboard/agent-queue          - Agent's assigned tickets
GET    /api/v1/dashboard/agent-stats          - Agent's statistics
GET    /api/v1/dashboard/agent-activity       - Agent's recent activity
GET    /api/v1/dashboard/agent-productivity   - Productivity metrics
```

**Reports**:
```
GET    /api/v1/reports                        - List saved reports
POST   /api/v1/reports                        - Create custom report
GET    /api/v1/reports/{id}                   - Get report details
POST   /api/v1/reports/{id}/execute           - Run report
POST   /api/v1/reports/{id}/schedule          - Schedule report
```

**Exports**:
```
POST   /api/v1/exports/tickets                - Export ticket data
POST   /api/v1/exports/agents                 - Export agent data
POST   /api/v1/exports/custom                 - Custom data export
GET    /api/v1/exports/{id}/download          - Download export file
GET    /api/v1/exports/{id}/status            - Check export status
```

**Metrics**:
```
GET    /api/v1/metrics/ticket-metrics         - Ticket KPIs
GET    /api/v1/metrics/agent-metrics          - Agent KPIs
GET    /api/v1/metrics/client-metrics         - Client statistics
POST   /api/v1/metrics/aggregate/daily        - Run daily aggregation
```

**Real-time**:
```
GET    /api/v1/realtime/current-stats         - Live statistics
GET    /api/v1/realtime/active-agents         - Currently online agents
GET    /api/v1/realtime/queue-status          - Current queue status
```

#### 5.7.3 Dashboard Statistics

**Overview Metrics**:
- Open tickets count
- Pending tickets count
- Resolved tickets count (today/week/month)
- Average response time
- Average resolution time
- First response SLA compliance %
- Resolution SLA compliance %
- Agent utilization rate

**Trends Data**:
```json
{
  "date": "2025-10-01",
  "tickets": 45,
  "resolved": 32,
  "open": 13,
  "avg_response_time_minutes": 47,
  "avg_resolution_time_hours": 4.2
}
```

**Priority Distribution**:
```json
{
  "urgent": 5,
  "high": 12,
  "medium": 28,
  "low": 8
}
```

#### 5.7.4 Agent Performance Metrics

Per-agent statistics:
- Assigned tickets (current)
- Resolved tickets (today/week/month)
- Average response time
- Average resolution time
- Customer satisfaction score (if enabled)
- First response SLA compliance
- Resolution SLA compliance
- Workload score (0-100)

#### 5.7.5 Custom Reports

Users can create custom reports with:
- Date range selection
- Filter by status, priority, category, agent
- Group by date, agent, category, client
- Sort and limit options
- Schedule daily/weekly/monthly

#### 5.7.6 Data Exports

Export formats:
- CSV - Comma-separated values
- Excel - .xlsx format
- PDF - Formatted reports

Export process:
1. User requests export
2. Background job processes data
3. File generated and stored in MinIO
4. Download link provided
5. Auto-cleanup after 7 days

---

## 6. Database Architecture

### 6.1 Database Overview

**Single PostgreSQL instance with logical separation**:
- All services share one database: `aidly`
- Tables organized by domain
- Service-specific prefixes (optional)
- Shared tables: users, clients

### 6.2 Core Tables Reference

#### 6.2.1 Users & Authentication

**users**
```sql
id                  UUID PRIMARY KEY
email               VARCHAR(255) UNIQUE NOT NULL
password_hash       VARCHAR(255) NOT NULL
name                VARCHAR(255) NOT NULL
avatar_url          TEXT
role                VARCHAR(50) NOT NULL -- 'admin', 'supervisor', 'agent'
department_id       UUID
email_verified_at   TIMESTAMP
two_factor_enabled  BOOLEAN
is_active           BOOLEAN
last_login_at       TIMESTAMP
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

**sessions**
```sql
id              VARCHAR(255) PRIMARY KEY
user_id         UUID NOT NULL
ip_address      INET
user_agent      TEXT
payload         TEXT
last_activity   TIMESTAMP
```

**permissions**
```sql
id          UUID PRIMARY KEY
resource    VARCHAR(255) -- 'tickets', 'clients', 'users'
action      VARCHAR(50)  -- 'view', 'create', 'update', 'delete'
description TEXT
```

#### 6.2.2 Clients

**clients**
```sql
id                      UUID PRIMARY KEY
email                   VARCHAR(255) UNIQUE NOT NULL
name                    VARCHAR(255)
company                 VARCHAR(255)
phone                   VARCHAR(50)
is_vip                  BOOLEAN DEFAULT false
is_blocked              BOOLEAN DEFAULT false
tags                    TEXT[]
custom_fields           JSONB
notification_preferences JSONB
created_at              TIMESTAMP
updated_at              TIMESTAMP
```

**client_notes**
```sql
id          UUID PRIMARY KEY
client_id   UUID NOT NULL
created_by  UUID NOT NULL
note        TEXT NOT NULL
is_pinned   BOOLEAN DEFAULT false
created_at  TIMESTAMP
```

#### 6.2.3 Tickets

**tickets**
```sql
id                      UUID PRIMARY KEY
ticket_number           VARCHAR(50) UNIQUE NOT NULL
subject                 VARCHAR(500) NOT NULL
description             TEXT NOT NULL
status                  ticket_status DEFAULT 'new'
priority                ticket_priority DEFAULT 'medium'
source                  ticket_source NOT NULL
client_id               UUID NOT NULL
assigned_agent_id       UUID
category_id             UUID
sla_policy_id           UUID
first_response_at       TIMESTAMP
first_response_due_at   TIMESTAMP
resolution_due_at       TIMESTAMP
resolved_at             TIMESTAMP
closed_at               TIMESTAMP
ai_suggestion           TEXT
ai_confidence_score     DECIMAL(3,2)
ai_suggested_category_id UUID
email_message_id        VARCHAR(255)
sent_message_ids        TEXT[]
tags                    TEXT[]
custom_fields           JSONB
created_at              TIMESTAMP
updated_at              TIMESTAMP
```

**ticket_comments**
```sql
id              UUID PRIMARY KEY
ticket_id       UUID NOT NULL
user_id         UUID (agent comment)
client_id       UUID (client comment)
comment         TEXT NOT NULL
is_internal     BOOLEAN DEFAULT false
is_read         BOOLEAN DEFAULT false
created_at      TIMESTAMP
```

**ticket_history**
```sql
id          UUID PRIMARY KEY
ticket_id   UUID NOT NULL
user_id     UUID
action      VARCHAR(100)
old_value   TEXT
new_value   TEXT
metadata    JSONB
created_at  TIMESTAMP
```

#### 6.2.4 Email

**email_accounts**
```sql
id                      UUID PRIMARY KEY
name                    VARCHAR(255)
email_address           VARCHAR(255) NOT NULL
imap_host               VARCHAR(255)
imap_port               INTEGER
imap_username           VARCHAR(255)
imap_password_encrypted TEXT
smtp_host               VARCHAR(255)
smtp_port               INTEGER
smtp_username           VARCHAR(255)
smtp_password_encrypted TEXT
is_active               BOOLEAN DEFAULT true
last_sync_at            TIMESTAMP
```

**email_queue**
```sql
id              UUID PRIMARY KEY
to_address      VARCHAR(255)
subject         VARCHAR(500)
body            TEXT
ticket_id       UUID
status          VARCHAR(50) -- 'pending', 'sent', 'failed'
retry_count     INTEGER DEFAULT 0
sent_at         TIMESTAMP
created_at      TIMESTAMP
```

### 6.3 Indexes

Performance indexes on frequently queried columns:

```sql
CREATE INDEX idx_tickets_status ON tickets(status);
CREATE INDEX idx_tickets_client_id ON tickets(client_id);
CREATE INDEX idx_tickets_assigned_agent ON tickets(assigned_agent_id);
CREATE INDEX idx_tickets_created_at ON tickets(created_at DESC);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_clients_email ON clients(email);
CREATE INDEX idx_comments_ticket_id ON ticket_comments(ticket_id);
CREATE INDEX idx_history_ticket_id ON ticket_history(ticket_id);
```

### 6.4 Database Migrations

Initial schema created via Docker init scripts:
- `01-create-schema.sql` - Core tables
- `02-create-remaining-tables.sql` - Additional tables
- `02-seed-test-data.sql` - Test data
- `03-add-email-threading-fields.sql` - Email features

For ongoing migrations, use Lumen migrations in each service.

---

## 7. Frontend Application

### 7.1 Frontend Architecture

**Framework**: Next.js 15 with App Router
**Directory**: `/frontend`

### 7.2 Project Structure

```
frontend/
├── app/
│   ├── (app)/                  # Authenticated layout
│   │   ├── dashboard/          # Dashboard pages
│   │   │   ├── page.tsx        # Main dashboard
│   │   │   └── agent/          # Agent-specific dashboard
│   │   ├── tickets/            # Ticket management
│   │   │   ├── page.tsx        # Ticket list
│   │   │   └── [id]/           # Ticket detail
│   │   ├── customers/          # Client management
│   │   ├── team/               # Agent management
│   │   ├── reports/            # Analytics reports
│   │   ├── settings/           # Settings
│   │   ├── notifications/      # Notification center
│   │   └── profile/            # User profile
│   ├── auth/                   # Login/register pages
│   │   └── page.tsx
│   ├── layout.tsx              # Root layout
│   └── globals.css             # Global styles
├── components/
│   ├── ui/                     # Radix UI components
│   │   ├── button.tsx
│   │   ├── card.tsx
│   │   ├── dialog.tsx
│   │   └── ...
│   ├── forms/                  # Form components
│   ├── tables/                 # Data tables
│   └── layout/                 # Layout components
├── lib/
│   ├── api.ts                  # API client
│   ├── colors.ts               # Color utilities
│   ├── utils.ts                # Utilities
│   └── auth.ts                 # Auth helpers
├── hooks/                      # Custom React hooks
└── public/                     # Static assets
```

### 7.3 API Client (`/frontend/lib/api.ts`)

Centralized Axios client with interceptors:

```typescript
import axios from 'axios';

const api = axios.create({
  baseURL: process.env.NEXT_PUBLIC_API_URL || 'http://localhost:3000',
  timeout: 30000,
});

// Add JWT token to requests
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Handle 401 errors
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('token');
      window.location.href = '/auth';
    }
    return Promise.reject(error);
  }
);

export default {
  auth: {
    login: (data) => api.post('http://localhost:8001/api/v1/auth/login', data),
    register: (data) => api.post('http://localhost:8001/api/v1/auth/register', data),
    me: () => api.get('http://localhost:8001/api/v1/auth/me'),
  },
  tickets: {
    list: (params) => api.get('http://localhost:8002/api/v1/public/tickets', { params }),
    get: (id) => api.get(`http://localhost:8002/api/v1/public/tickets/${id}`),
    create: (data) => api.post('http://localhost:8002/api/v1/public/tickets', data),
    update: (id, data) => api.put(`http://localhost:8002/api/v1/public/tickets/${id}`, data),
  },
  clients: {
    list: (params) => api.get('http://localhost:8003/api/v1/clients', { params }),
    get: (id) => api.get(`http://localhost:8003/api/v1/clients/${id}`),
  },
  analytics: {
    dashboard: {
      stats: () => api.get('http://localhost:8007/api/v1/dashboard/stats'),
      trends: (params) => api.get('http://localhost:8007/api/v1/dashboard/trends', { params }),
    },
  },
};
```

### 7.4 State Management

**Server State** (TanStack Query):
```typescript
import { useQuery, useMutation } from '@tanstack/react-query';

// Fetch tickets
const { data, isLoading } = useQuery({
  queryKey: ['tickets', filters],
  queryFn: () => api.tickets.list(filters),
});

// Create ticket
const createTicket = useMutation({
  mutationFn: api.tickets.create,
  onSuccess: () => {
    queryClient.invalidateQueries(['tickets']);
  },
});
```

**Client State** (Zustand):
```typescript
import { create } from 'zustand';

const useAuthStore = create((set) => ({
  user: null,
  token: null,
  setAuth: (user, token) => set({ user, token }),
  logout: () => set({ user: null, token: null }),
}));
```

### 7.5 Key Frontend Features

**Dashboard** (`/dashboard`):
- Real-time statistics cards
- Ticket trend charts (Recharts)
- Priority distribution pie chart
- Recent tickets list
- Agent-specific view for agents

**Ticket List** (`/tickets`):
- Filterable/sortable table
- Status and priority badges
- Pagination
- Bulk actions

**Ticket Detail** (`/tickets/[id]`):
- Full ticket information
- Comment thread
- Rich text editor (Tiptap) with AI auto-write
- Attachment upload/download
- Status/priority updates
- Assignment

**Client Management** (`/customers`):
- Client list with search
- Client detail with ticket history
- VIP/blocked toggles
- Internal notes

### 7.6 UI Components

Built with Radix UI primitives and Tailwind CSS:
- `Button` - All button variants
- `Card` - Content containers
- `Dialog` - Modal dialogs
- `Select` - Dropdowns
- `Table` - Data tables
- `Badge` - Status indicators
- `Avatar` - User avatars
- `Tabs` - Tab navigation

### 7.7 Routing

Next.js App Router conventions:
- `page.tsx` - Route page
- `layout.tsx` - Shared layout
- `[id]` - Dynamic routes
- `(app)` - Route groups (no URL segment)

---

## 8. Authentication & Authorization

### 8.1 JWT Token Structure

**Token Claims**:
```json
{
  "sub": "user-uuid",
  "email": "agent@example.com",
  "name": "John Doe",
  "role": "agent",
  "iat": 1696118400,
  "exp": 1696122000
}
```

**Token Lifetime**:
- Access Token: 60 minutes (configurable via `JWT_TTL`)
- Refresh Token: 14 days (configurable via `JWT_REFRESH_TTL`)

### 8.2 Authentication Flow

```
1. User → POST /api/v1/auth/login
   Request: { email, password }
   ↓
2. Auth Service validates credentials
   ↓
3. Generate JWT token
   ↓
4. Response: { token, user: { id, email, name, role } }
   ↓
5. Frontend stores token in localStorage
   ↓
6. Subsequent requests include header:
   Authorization: Bearer <token>
   ↓
7. Services validate JWT signature
   ↓
8. Middleware extracts user from token
   ↓
9. Role/permission checks
```

### 8.3 Role-Based Access Control

**Role Hierarchy**:

```
admin
  - Full system access
  - User management
  - Configuration management
  - All CRUD operations
  ↓
supervisor
  - View all tickets
  - Assign tickets to agents
  - View reports
  - Manage categories
  ↓
agent
  - View assigned tickets
  - Create/update tickets
  - Add comments
  - View client info
```

**Permission Examples**:

```php
// Middleware in routes/web.php
$router->group(['middleware' => ['jwt', 'role:admin']], function () use ($router) {
    $router->post('/users', 'UserController@create');
});

$router->group(['middleware' => ['jwt', 'role:admin,supervisor']], function () use ($router) {
    $router->post('/tickets/{id}/assign', 'TicketController@assign');
});

$router->group(['middleware' => 'jwt'], function () use ($router) {
    $router->get('/tickets', 'TicketController@index');
});
```

### 8.4 Password Security

**Hashing**: Bcrypt with work factor 10

```php
// Registration
$passwordHash = password_hash($request->input('password'), PASSWORD_BCRYPT);

// Login validation
if (!password_verify($password, $user->password_hash)) {
    throw new UnauthorizedException('Invalid credentials');
}
```

**Password Reset Flow**:
1. User requests reset via email
2. Generate random token
3. Store in `password_resets` table with expiration
4. Send email with reset link
5. User clicks link with token
6. Validate token not expired
7. Allow new password
8. Hash and update password
9. Delete reset token

### 8.5 Session Management

**Redis-based sessions**:
- Session ID stored in `sessions` table
- Session data in Redis
- TTL matches JWT expiration
- Logout invalidates session

**Multi-device Support**:
- Users can view active sessions
- Revoke individual sessions
- "Logout from all devices" option

### 8.6 Two-Factor Authentication

**TOTP-based 2FA**:
1. User enables 2FA in settings
2. Generate secret key
3. Display QR code for authenticator app
4. User scans and enters verification code
5. Store `two_factor_secret` encrypted
6. Login requires TOTP code

**Recovery Codes**:
- 10 one-time recovery codes
- Used if authenticator unavailable
- Regenerate after use

---

## 9. Inter-Service Communication

### 9.1 Communication Pattern

Services communicate via **synchronous HTTP REST** calls:

```
Ticket Service                Client Service
      │                             │
      │──GET /api/v1/clients/{id}──→│
      │                             │
      │←─────────JSON response──────│
      │                             │
```

### 9.2 Service Discovery

**Docker DNS**:
- Services use container names as hostnames
- Network: `aidly-network`
- Example: `http://auth-service:8001`

**Environment Variables**:
```env
AUTH_SERVICE_URL=http://auth-service:8001
TICKET_SERVICE_URL=http://ticket-service:8002
CLIENT_SERVICE_URL=http://client-service:8003
EMAIL_SERVICE_URL=http://email-service:8005
```

### 9.3 Common Inter-Service Calls

**Email Service → Client Service**:
```php
// Find or create client by email
$response = Http::get(env('CLIENT_SERVICE_URL') . '/api/v1/clients', [
    'email' => $fromEmail
]);
```

**Email Service → Ticket Service**:
```php
// Create ticket from email
$response = Http::post(env('TICKET_SERVICE_URL') . '/api/v1/public/tickets', [
    'client_id' => $clientId,
    'subject' => $subject,
    'description' => $body,
    'source' => 'email',
    'priority' => 'medium'
]);
```

**Ticket Service → Notification Service**:
```php
// Notify agent of new assignment
$response = Http::post(env('NOTIFICATION_SERVICE_URL') . '/api/v1/webhooks/ticket-assigned', [
    'ticket_id' => $ticketId,
    'agent_id' => $agentId
]);
```

**Ticket Service → AI Service**:
```php
// Get response suggestion
$response = Http::post(env('AI_SERVICE_URL') . '/api/v1/process/ticket/suggest-response', [
    'ticket_id' => $ticketId,
    'include_history' => true
]);
```

### 9.4 Error Handling

**Retry Logic**:
```php
use Illuminate\Support\Facades\Http;

$response = Http::retry(3, 100) // 3 retries, 100ms delay
    ->timeout(5)
    ->get($url);

if ($response->failed()) {
    Log::error('Service call failed', ['url' => $url]);
    // Fallback logic
}
```

**Circuit Breaker Pattern**:
- Track service failures
- Open circuit after threshold
- Attempt recovery periodically

### 9.5 Data Consistency

**Eventual Consistency**:
- Services may have stale data briefly
- Background sync jobs for critical data
- Cache invalidation strategies

**Transaction Boundaries**:
- Each service manages own transactions
- No distributed transactions (avoid complexity)
- Compensating actions for rollback

---

## 10. Core Workflows

### 10.1 Complete Email-to-Ticket Workflow

```
┌─────────────────────────────────────────────────┐
│ 1. Email arrives at support@company.com        │
└────────────┬────────────────────────────────────┘
             │
┌────────────▼────────────────────────────────────┐
│ 2. Email Service (IMAP Cron Job)               │
│    - Fetch new emails every 5 minutes          │
│    - Parse headers: From, Subject, Message-ID  │
│    - Check In-Reply-To for threading           │
└────────────┬────────────────────────────────────┘
             │
┌────────────▼────────────────────────────────────┐
│ 3. Check if reply or new conversation          │
│    Query: tickets.sent_message_ids             │
│    ├─ Match found → Existing ticket            │
│    └─ No match → New ticket                    │
└────────────┬────────────────────────────────────┘
             │
┌────────────▼────────────────────────────────────┐
│ 4. Client Service: Find or create client       │
│    POST /api/v1/clients                        │
│    - Match by email address                    │
│    - Create if not exists                      │
│    - Check is_blocked status                   │
└────────────┬────────────────────────────────────┘
             │
┌────────────▼────────────────────────────────────┐
│ 5. Extract and store attachments               │
│    - Download from email                       │
│    - Upload to MinIO                           │
│    - Create attachment records                 │
└────────────┬────────────────────────────────────┘
             │
┌────────────▼────────────────────────────────────┐
│ 6. Ticket Service: Create ticket or comment    │
│    If new:                                     │
│      POST /api/v1/public/tickets               │
│      - Store email_message_id                  │
│    If reply:                                   │
│      POST /api/v1/public/tickets/{id}/comments │
└────────────┬────────────────────────────────────┘
             │
┌────────────▼────────────────────────────────────┐
│ 7. AI Service: Auto-process (optional)         │
│    POST /api/v1/process/ticket/categorize      │
│    POST /api/v1/process/ticket/prioritize      │
│    - Update ticket with suggestions            │
└────────────┬────────────────────────────────────┘
             │
┌────────────▼────────────────────────────────────┐
│ 8. Auto-assignment (if configured)             │
│    - Calculate agent workload                  │
│    - Assign to least loaded agent              │
│    - Update ticket.assigned_agent_id           │
└────────────┬────────────────────────────────────┘
             │
┌────────────▼────────────────────────────────────┐
│ 9. Notification Service: Notify agent          │
│    POST /api/v1/webhooks/ticket-created        │
│    - In-app notification                       │
│    - Email notification                        │
│    - Slack notification (if configured)        │
└────────────┬────────────────────────────────────┘
             │
┌────────────▼────────────────────────────────────┐
│ 10. Analytics Service: Track event             │
│     - Increment ticket count                   │
│     - Update dashboard metrics                 │
└─────────────────────────────────────────────────┘
```

### 10.2 Agent Response Workflow

```
┌─────────────────────────────────────────────────┐
│ 1. Agent opens ticket in frontend              │
│    GET /api/v1/public/tickets/{id}             │
└────────────┬────────────────────────────────────┘
             │
┌────────────▼────────────────────────────────────┐
│ 2. Load ticket details                         │
│    - Subject, description, status              │
│    - Client information                        │
│    - Previous comments                         │
│    - Attachments                               │
└────────────┬────────────────────────────────────┘
             │
┌────────────▼────────────────────────────────────┐
│ 3. Agent types response in rich text editor    │
│    - AI auto-write assistance (optional)       │
│    - Formatting options                        │
└────────────┬────────────────────────────────────┘
             │
┌────────────▼────────────────────────────────────┐
│ 4. Agent clicks "Send Reply"                   │
│    POST /api/v1/public/tickets/{id}/comments   │
│    - Comment text                              │
│    - is_internal: false (visible to client)    │
└────────────┬────────────────────────────────────┘
             │
┌────────────▼────────────────────────────────────┐
│ 5. Ticket Service: Save comment                │
│    - Create ticket_comments record             │
│    - Update ticket.updated_at                  │
│    - Create ticket_history entry               │
└────────────┬────────────────────────────────────┘
             │
┌────────────▼────────────────────────────────────┐
│ 6. Email Service: Send email to client        │
│    POST /api/v1/emails/send-notification       │
│    - Format email with template                │
│    - Include ticket context                    │
│    - Add In-Reply-To header                    │
│    - Send via SMTP                             │
│    - Store sent message_id                     │
└────────────┬────────────────────────────────────┘
             │
┌────────────▼────────────────────────────────────┐
│ 7. Update ticket status (if needed)            │
│    PUT /api/v1/public/tickets/{id}             │
│    - Status: new → open                        │
│    - first_response_at timestamp               │
└────────────┬────────────────────────────────────┘
             │
┌────────────▼────────────────────────────────────┐
│ 8. Notification Service: Notify client        │
│    - Email sent notification                   │
└────────────┬────────────────────────────────────┘
             │
┌────────────▼────────────────────────────────────┐
│ 9. Analytics Service: Track metrics            │
│    - Response time calculation                 │
│    - SLA compliance check                      │
│    - Agent productivity metrics                │
└─────────────────────────────────────────────────┘
```

### 10.3 Ticket Assignment Workflow

**Manual Assignment**:
```
1. Supervisor views ticket list
   ↓
2. Selects ticket
   ↓
3. Clicks "Assign to Agent"
   ↓
4. Selects agent from dropdown
   ↓
5. POST /api/v1/tickets/{id}/assign
   Request: { agent_id: "uuid" }
   ↓
6. Ticket Service updates assigned_agent_id
   ↓
7. Notification sent to agent
   ↓
8. Ticket appears in agent's queue
```

**Auto-Assignment**:
```
1. New ticket created
   ↓
2. Check auto-assignment rules
   ↓
3. Calculate agent workload
   GET /api/v1/assignments/agents/workload
   ↓
4. Find least loaded agent
   - Filter by department (if applicable)
   - Filter by availability
   - Sort by current ticket count
   ↓
5. Assign to selected agent
   POST /api/v1/assignments/auto-assign
   ↓
6. Update ticket.assigned_agent_id
   ↓
7. Notify agent
```

### 10.4 SLA Tracking Workflow

```
1. Ticket created with SLA policy
   ↓
2. Calculate due dates
   - First response due: created_at + policy.first_response_time
   - Resolution due: created_at + policy.resolution_time
   ↓
3. Store in ticket:
   - first_response_due_at
   - resolution_due_at
   ↓
4. Background job checks SLA status (every 5 min)
   ↓
5. If approaching deadline (80% of time passed):
   - Send warning notification
   ↓
6. If deadline passed:
   - Mark SLA as breached
   - Send breach notification
   - Escalate (if configured)
   ↓
7. Analytics Service tracks:
   - SLA compliance rate
   - Average time to first response
   - Average time to resolution
```

### 10.5 Client Merge Workflow

```
1. Admin identifies duplicate clients
   ↓
2. Preview merge operation
   POST /api/v1/clients/merge/preview
   Request: {
     primary_client_id: "uuid1",
     duplicate_client_ids: ["uuid2", "uuid3"]
   }
   ↓
3. Preview shows:
   - Combined client data
   - Total tickets
   - Notes
   ↓
4. Confirm merge
   POST /api/v1/clients/merge
   ↓
5. Client Service:
   - Update all tickets to primary client
   - Merge notes
   - Combine tags
   - Create client_merges record
   - Soft delete duplicate clients
   ↓
6. Ticket Service updates:
   - Reassign all tickets to primary client
   ↓
7. Return merged client profile
```

---

## 11. API Reference

### 11.1 Authentication API

**Base URL**: `http://localhost:8001`

#### POST /api/v1/auth/register
Create new user account (admin only in production)

**Request**:
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "SecurePass123!",
  "password_confirmation": "SecurePass123!",
  "role": "agent",
  "department_id": "uuid" (optional)
}
```

**Response** (201):
```json
{
  "success": true,
  "data": {
    "user": {
      "id": "uuid",
      "name": "John Doe",
      "email": "john@example.com",
      "role": "agent"
    },
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
  }
}
```

#### POST /api/v1/auth/login
Authenticate user and get JWT token

**Request**:
```json
{
  "email": "john@example.com",
  "password": "SecurePass123!"
}
```

**Response** (200):
```json
{
  "success": true,
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "user": {
      "id": "uuid",
      "name": "John Doe",
      "email": "john@example.com",
      "role": "agent",
      "department_id": "uuid"
    }
  }
}
```

#### GET /api/v1/auth/me
Get current authenticated user

**Headers**: `Authorization: Bearer {token}`

**Response** (200):
```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "name": "John Doe",
    "email": "john@example.com",
    "role": "agent",
    "department": {
      "id": "uuid",
      "name": "Support Team"
    }
  }
}
```

### 11.2 Tickets API

**Base URL**: `http://localhost:8002`

#### GET /api/v1/public/tickets
List tickets with filtering

**Query Parameters**:
```
status        - Filter by status (new, open, resolved, etc.)
priority      - Filter by priority (low, medium, high, urgent)
assigned_to   - Filter by agent ID
client_id     - Filter by client ID
category_id   - Filter by category ID
search        - Search in subject/description
page          - Page number (default: 1)
per_page      - Items per page (default: 20)
sort          - Sort field (created_at, updated_at, priority)
direction     - Sort direction (asc, desc)
```

**Response** (200):
```json
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "ticket_number": "TKT-001234",
      "subject": "Cannot login to account",
      "description": "Getting error when...",
      "status": "open",
      "priority": "high",
      "source": "email",
      "client_id": "uuid",
      "assigned_agent_id": "uuid",
      "category_id": "uuid",
      "created_at": "2025-10-06T10:30:00Z",
      "updated_at": "2025-10-06T11:45:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 150,
    "last_page": 8
  }
}
```

#### POST /api/v1/public/tickets
Create new ticket

**Request**:
```json
{
  "client_id": "uuid",
  "subject": "Need help with payment",
  "description": "I'm having trouble processing my payment...",
  "priority": "medium",
  "source": "web_form",
  "category_id": "uuid" (optional),
  "tags": ["billing", "urgent"] (optional)
}
```

**Response** (201):
```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "ticket_number": "TKT-001235",
    "subject": "Need help with payment",
    "status": "new",
    "priority": "medium",
    "created_at": "2025-10-06T12:00:00Z"
  }
}
```

#### GET /api/v1/public/tickets/{id}
Get ticket details

**Response** (200):
```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "ticket_number": "TKT-001234",
    "subject": "Cannot login to account",
    "description": "Getting error when trying to log in...",
    "status": "open",
    "priority": "high",
    "source": "email",
    "client": {
      "id": "uuid",
      "name": "Jane Smith",
      "email": "jane@example.com",
      "is_vip": false
    },
    "assigned_agent": {
      "id": "uuid",
      "name": "John Doe",
      "email": "john@company.com"
    },
    "category": {
      "id": "uuid",
      "name": "Account & Authentication"
    },
    "comments": [
      {
        "id": "uuid",
        "comment": "Thank you for contacting us...",
        "user": {
          "id": "uuid",
          "name": "John Doe"
        },
        "is_internal": false,
        "created_at": "2025-10-06T10:45:00Z"
      }
    ],
    "attachments": [],
    "created_at": "2025-10-06T10:30:00Z",
    "updated_at": "2025-10-06T11:45:00Z"
  }
}
```

#### PUT /api/v1/public/tickets/{id}
Update ticket

**Request**:
```json
{
  "status": "resolved",
  "priority": "medium",
  "category_id": "uuid"
}
```

**Response** (200):
```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "status": "resolved",
    "resolved_at": "2025-10-06T14:00:00Z",
    "updated_at": "2025-10-06T14:00:00Z"
  }
}
```

#### POST /api/v1/public/tickets/{id}/comments
Add comment/reply to ticket

**Request**:
```json
{
  "comment": "I've reset your password. Please try again.",
  "is_internal": false,
  "attachments": ["file-uuid-1", "file-uuid-2"] (optional)
}
```

**Response** (201):
```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "ticket_id": "uuid",
    "comment": "I've reset your password...",
    "user": {
      "id": "uuid",
      "name": "John Doe"
    },
    "is_internal": false,
    "created_at": "2025-10-06T13:00:00Z"
  }
}
```

### 11.3 Clients API

**Base URL**: `http://localhost:8003`

#### GET /api/v1/clients
List clients

**Query Parameters**:
```
search      - Search by name, email, company
is_vip      - Filter VIP clients (true/false)
is_blocked  - Filter blocked clients (true/false)
tags        - Filter by tags
page        - Page number
per_page    - Items per page
```

**Response** (200):
```json
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "email": "client@example.com",
      "name": "Jane Smith",
      "company": "Acme Corp",
      "phone": "+1-555-0123",
      "is_vip": true,
      "is_blocked": false,
      "tags": ["premium", "enterprise"],
      "ticket_count": 15,
      "created_at": "2025-01-15T08:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "total": 450
  }
}
```

#### GET /api/v1/clients/{id}
Get client details

**Response** (200):
```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "email": "client@example.com",
    "name": "Jane Smith",
    "company": "Acme Corp",
    "phone": "+1-555-0123",
    "is_vip": true,
    "is_blocked": false,
    "tags": ["premium", "enterprise"],
    "custom_fields": {
      "account_manager": "Sarah Johnson",
      "contract_value": "50000"
    },
    "created_at": "2025-01-15T08:00:00Z",
    "last_contact_at": "2025-10-05T16:30:00Z"
  }
}
```

#### POST /api/v1/clients/{id}/block
Toggle client blocked status

**Response** (200):
```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "is_blocked": true
  }
}
```

### 11.4 Analytics API

**Base URL**: `http://localhost:8007`

#### GET /api/v1/dashboard/stats
Get dashboard statistics

**Response** (200):
```json
{
  "success": true,
  "data": {
    "open_tickets": 45,
    "open_tickets_change": -5.2,
    "pending_tickets": 12,
    "pending_tickets_change": 2.1,
    "resolved_tickets": 138,
    "resolved_tickets_change": 15.3,
    "avg_response_time": "2.3 hrs",
    "avg_response_time_change": -8.5,
    "priority_distribution": [
      { "priority": "low", "count": 8 },
      { "priority": "medium", "count": 28 },
      { "priority": "high", "count": 12 },
      { "priority": "urgent", "count": 5 }
    ],
    "sla_compliance": 94.5
  }
}
```

#### GET /api/v1/dashboard/trends
Get ticket trends

**Query Parameters**:
```
days - Number of days (7, 30, 90)
```

**Response** (200):
```json
{
  "success": true,
  "data": [
    {
      "date": "2025-10-01",
      "tickets": 23,
      "resolved": 18,
      "open": 5,
      "avg_response_time_minutes": 125
    },
    {
      "date": "2025-10-02",
      "tickets": 31,
      "resolved": 25,
      "open": 6,
      "avg_response_time_minutes": 98
    }
  ]
}
```

---

## 12. Configuration Guide

### 12.1 Environment Variables

**Location**: `/root/AidlY/.env`

#### Application Settings
```env
APP_NAME=AidlY
APP_ENV=development          # development, production
APP_DEBUG=true               # Set false in production
APP_URL=http://localhost:3000
```

#### Database Configuration
```env
DB_HOST=localhost            # Use 'postgres' in Docker
DB_PORT=5432
DB_DATABASE=aidly
DB_USERNAME=aidly_user
DB_PASSWORD=aidly_secret_2024  # CHANGE IN PRODUCTION
```

#### Redis Configuration
```env
REDIS_HOST=localhost         # Use 'redis' in Docker
REDIS_PORT=6379
REDIS_PASSWORD=redis_secret_2024  # CHANGE IN PRODUCTION
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
```

#### MinIO Configuration
```env
MINIO_HOST=localhost         # Use 'minio' in Docker
MINIO_PORT=9000
MINIO_ROOT_USER=aidly_minio_admin
MINIO_ROOT_PASSWORD=minio_secret_2024  # CHANGE IN PRODUCTION
MINIO_BUCKET=aidly-attachments
```

#### JWT Configuration
```env
JWT_SECRET=your-secret-jwt-key-please-change-this-in-production
JWT_TTL=60                   # Token lifetime in minutes
JWT_REFRESH_TTL=20160        # Refresh token lifetime in minutes (14 days)
JWT_ALGO=HS256
```

#### Email Configuration
```env
# For sending emails (SMTP)
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@aidly.com
MAIL_FROM_NAME="${APP_NAME}"
```

#### AI Provider Configuration
```env
AI_ENABLED=true
AI_DEFAULT_PROVIDER=openai   # openai, anthropic, gemini

# OpenAI
OPENAI_ENABLED=true
OPENAI_API_KEY=sk-...

# Anthropic Claude
ANTHROPIC_ENABLED=false
ANTHROPIC_API_KEY=sk-ant-...

# Google Gemini
GEMINI_ENABLED=false
GEMINI_API_KEY=...

# Feature Flags
AI_FEATURE_AUTO_CATEGORIZATION=true
AI_FEATURE_AUTO_PRIORITIZATION=true
AI_FEATURE_RESPONSE_SUGGESTIONS=true
AI_FEATURE_SENTIMENT_ANALYSIS=false
```

#### Frontend Configuration
```env
NEXT_PUBLIC_API_URL=http://localhost:8000
NEXT_PUBLIC_WS_URL=ws://localhost:8000
```

#### SLA Defaults
```env
DEFAULT_FIRST_RESPONSE_TIME=120    # Minutes
DEFAULT_RESOLUTION_TIME=1440        # Minutes (24 hours)
DEFAULT_TIMEZONE=UTC
BUSINESS_HOURS_START=09:00
BUSINESS_HOURS_END=18:00
BUSINESS_DAYS=1,2,3,4,5            # Mon-Fri
```

### 12.2 Service-Specific Configuration

Each service has its own `.env` or configuration file, but most inherit from the root `.env` via Docker Compose.

**Service Ports**:
- Auth Service: `8001`
- Ticket Service: `8002`
- Client Service: `8003`
- Notification Service: `8004`
- Email Service: `8005`
- AI Integration Service: `8006`
- Analytics Service: `8007`

### 12.3 Email Account Setup

**Gmail Setup**:
1. Enable IMAP in Gmail settings
2. Generate App Password (not regular password)
3. Configure in Email Service:
   ```json
   {
     "name": "Support Gmail",
     "email_address": "support@company.com",
     "imap_host": "imap.gmail.com",
     "imap_port": 993,
     "imap_username": "support@company.com",
     "imap_password": "app-password-here",
     "imap_use_ssl": true,
     "smtp_host": "smtp.gmail.com",
     "smtp_port": 587,
     "smtp_username": "support@company.com",
     "smtp_password": "app-password-here",
     "smtp_use_tls": true
   }
   ```

**Other Providers**:
- Outlook/Office 365: `smtp-mail.outlook.com`, port 587
- Yahoo: `smtp.mail.yahoo.com`, port 465
- Custom SMTP: Configure based on provider

### 12.4 Notification Channels

**Slack Integration**:
```env
SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
SLACK_CHANNEL=#support
```

**Twilio SMS**:
```env
SMS_PROVIDER=twilio
TWILIO_SID=ACxxxxxxxxxxxxxxxxxxxx
TWILIO_TOKEN=your-auth-token
TWILIO_FROM=+15551234567
```

---

## 13. Deployment

### 13.1 Development Environment

**Start All Services**:
```bash
cd /root/AidlY
./start-services.sh
```

Or manually:
```bash
docker-compose up -d
```

**Check Service Health**:
```bash
./check-services.sh
```

Or manually:
```bash
curl http://localhost:8001/health  # Auth
curl http://localhost:8002/health  # Tickets
curl http://localhost:8003/health  # Clients
curl http://localhost:8004/health  # Notifications
curl http://localhost:8005/health  # Email
curl http://localhost:8006/health  # AI
curl http://localhost:8007/health  # Analytics
```

**View Logs**:
```bash
docker-compose logs -f service-name

# Examples:
docker-compose logs -f auth-service
docker-compose logs -f ticket-service
```

**Stop Services**:
```bash
docker-compose down

# Or to remove volumes (data):
docker-compose down -v
```

### 13.2 Production Deployment

**Prerequisites**:
1. Linux server (Ubuntu 20.04+ recommended)
2. Docker and Docker Compose installed
3. Domain name configured
4. SSL certificates (Let's Encrypt)

**Steps**:

1. **Clone repository**:
   ```bash
   git clone <repository-url>
   cd aidly
   ```

2. **Configure environment**:
   ```bash
   cp .env.example .env
   nano .env
   ```

   Update:
   - All passwords
   - JWT secret
   - Email credentials
   - AI API keys
   - Set `APP_ENV=production`
   - Set `APP_DEBUG=false`

3. **Build services**:
   ```bash
   docker-compose build
   ```

4. **Start services**:
   ```bash
   docker-compose up -d
   ```

5. **Configure reverse proxy** (Nginx):
   ```nginx
   server {
       listen 80;
       server_name aidly.yourdomain.com;

       location / {
           return 301 https://$server_name$request_uri;
       }
   }

   server {
       listen 443 ssl http2;
       server_name aidly.yourdomain.com;

       ssl_certificate /path/to/cert.pem;
       ssl_certificate_key /path/to/key.pem;

       # Frontend
       location / {
           proxy_pass http://localhost:3000;
           proxy_set_header Host $host;
           proxy_set_header X-Real-IP $remote_addr;
       }

       # Auth Service
       location /api/auth {
           proxy_pass http://localhost:8001;
       }

       # Ticket Service
       location /api/tickets {
           proxy_pass http://localhost:8002;
       }

       # Add other services...
   }
   ```

6. **Set up database backups**:
   ```bash
   # Backup script
   #!/bin/bash
   docker exec aidly-postgres pg_dump -U aidly_user aidly > backup-$(date +%Y%m%d).sql
   ```

7. **Configure monitoring**:
   - Set up Sentry for error tracking
   - Configure log aggregation
   - Set up uptime monitoring

### 13.3 Scaling Considerations

**Horizontal Scaling**:
- Run multiple instances of each service behind load balancer
- Shared PostgreSQL and Redis instances
- Session affinity not required (stateless services)

**Vertical Scaling**:
- Increase Docker resource limits
- Optimize database queries
- Add database read replicas

**Database Optimization**:
- Regular VACUUM and ANALYZE
- Index optimization
- Partition large tables (tickets, history)

---

## 14. Development Guide

### 14.1 Project Setup for Development

**Clone Repository**:
```bash
git clone <repository-url>
cd aidly
```

**Install Dependencies**:

Backend (each service):
```bash
cd services/auth-service
composer install
```

Frontend:
```bash
cd frontend
npm install
```

**Start Infrastructure**:
```bash
docker-compose up -d postgres redis minio
```

**Run Services Locally** (optional, instead of Docker):

Each service:
```bash
cd services/auth-service
php -S localhost:8001 -t public
```

Frontend:
```bash
cd frontend
npm run dev
```

### 14.2 Creating a New Microservice

1. **Create service directory**:
   ```bash
   cd services
   composer create-project --prefer-dist laravel/lumen new-service
   ```

2. **Configure service**:
   - Update `.env`
   - Configure database connection
   - Set up routes in `routes/web.php`

3. **Add to docker-compose.yml**:
   ```yaml
   new-service:
     build:
       context: ./services/new-service
       dockerfile: Dockerfile
     container_name: aidly-new-service
     ports:
       - "8008:8008"
     environment:
       DB_HOST: postgres
       REDIS_HOST: redis
     networks:
       - aidly-network
   ```

4. **Create controllers and models**

5. **Update frontend API client**:
   ```typescript
   // lib/api.ts
   newService: {
     list: () => api.get('http://localhost:8008/api/v1/items'),
   }
   ```

### 14.3 Database Migrations

**Create Migration**:
```bash
cd services/ticket-service
php artisan make:migration create_new_table
```

**Edit Migration**:
```php
// database/migrations/2025_10_06_create_new_table.php
public function up() {
    Schema::create('new_table', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });
}
```

**Run Migration**:
```bash
php artisan migrate
```

### 14.4 Adding API Endpoints

**Define Route**:
```php
// routes/web.php
$router->group(['prefix' => 'api/v1'], function () use ($router) {
    $router->get('/items', 'ItemController@index');
    $router->post('/items', 'ItemController@store');
});
```

**Create Controller**:
```php
// app/Http/Controllers/ItemController.php
namespace App\Http\Controllers;

class ItemController extends Controller
{
    public function index() {
        $items = Item::all();
        return response()->json([
            'success' => true,
            'data' => $items
        ]);
    }

    public function store(Request $request) {
        $this->validate($request, [
            'name' => 'required|string|max:255'
        ]);

        $item = Item::create($request->all());

        return response()->json([
            'success' => true,
            'data' => $item
        ], 201);
    }
}
```

### 14.5 Testing

**Unit Tests** (Lumen):
```bash
cd services/ticket-service
./vendor/bin/phpunit
```

**Frontend Tests**:
```bash
cd frontend
npm run test
```

**Integration Tests**:
```bash
./tests/integration-test.sh
```

### 14.6 Code Style

**PHP** (PSR-12):
```bash
./vendor/bin/php-cs-fixer fix
```

**TypeScript**:
```bash
cd frontend
npm run lint
```

---

## 15. Troubleshooting

### 15.1 Common Issues

#### Service Won't Start

**Problem**: Docker container exits immediately

**Solution**:
```bash
# Check logs
docker-compose logs service-name

# Common issues:
# 1. Port already in use
sudo lsof -i :8001  # Check port usage
docker-compose down
docker-compose up -d

# 2. Database connection failed
# - Ensure PostgreSQL is running
# - Check credentials in .env
docker-compose ps postgres

# 3. Missing dependencies
# - Rebuild service
docker-compose build service-name
```

#### Database Connection Error

**Problem**: "SQLSTATE[08006] Connection refused"

**Solution**:
```bash
# 1. Check PostgreSQL is running
docker-compose ps postgres

# 2. Check network
docker network inspect aidly-network

# 3. Test connection
docker exec -it aidly-postgres psql -U aidly_user -d aidly

# 4. Check service environment
docker-compose config | grep DB_
```

#### Redis Connection Error

**Problem**: "Connection refused [tcp://redis:6379]"

**Solution**:
```bash
# 1. Check Redis is running
docker-compose ps redis

# 2. Test connection
docker exec -it aidly-redis redis-cli ping
# Should return PONG

# 3. Check password
docker exec -it aidly-redis redis-cli -a redis_secret_2024 ping
```

#### Email Not Sending

**Problem**: Emails not being sent from Email Service

**Solution**:
```bash
# 1. Check email queue
curl http://localhost:8005/api/v1/emails/stats

# 2. Test SMTP connection
curl -X POST http://localhost:8005/api/v1/accounts/1/test-smtp

# 3. Check logs
docker-compose logs email-service | grep -i error

# 4. Common issues:
# - Gmail App Password not set (not regular password)
# - IMAP not enabled in Gmail
# - Firewall blocking SMTP port
```

#### Tickets Not Creating from Emails

**Problem**: Emails received but tickets not created

**Solution**:
```bash
# 1. Manually trigger email fetch
curl -X POST http://localhost:8005/api/v1/emails/fetch

# 2. Check email processing
curl -X POST http://localhost:8005/api/v1/emails/process

# 3. Check logs
docker-compose logs email-service
docker-compose logs ticket-service

# 4. Verify client not blocked
curl http://localhost:8003/api/v1/clients?email=client@example.com
```

#### Frontend Can't Connect to Backend

**Problem**: API calls failing with CORS or connection errors

**Solution**:
```javascript
// 1. Check API URLs in .env.local
NEXT_PUBLIC_API_URL=http://localhost:8000

// 2. Test direct service access
curl http://localhost:8001/health
curl http://localhost:8002/health

// 3. Check browser console for CORS errors
// - Add CORS middleware to services if needed

// 4. Verify network
// - Services on same Docker network
// - Ports exposed correctly
```

#### AI Features Not Working

**Problem**: AI suggestions not generating

**Solution**:
```bash
# 1. Check feature flags
grep AI_FEATURE .env

# 2. Verify API key
curl http://localhost:8006/api/v1/providers/openai/status

# 3. Test AI endpoint
curl -X POST http://localhost:8006/api/v1/process/auto-write \
  -H "Content-Type: application/json" \
  -d '{"prompt":"Hello","context":""}'

# 4. Check logs
docker-compose logs ai-integration-service
```

### 15.2 Performance Issues

**Slow Ticket Loading**:
```bash
# 1. Check database indexes
docker exec -it aidly-postgres psql -U aidly_user -d aidly
\d tickets  # View indexes

# 2. Analyze slow queries
SET log_min_duration_statement = 100;  # Log queries > 100ms

# 3. Add indexes if needed
CREATE INDEX idx_tickets_custom ON tickets(status, priority);
```

**High Memory Usage**:
```bash
# 1. Check container stats
docker stats

# 2. Increase limits in docker-compose.yml
services:
  ticket-service:
    mem_limit: 512m
    memswap_limit: 1g

# 3. Optimize queries
# - Use pagination
# - Limit result sets
# - Add caching
```

### 15.3 Debug Mode

Enable detailed error messages:

**.env**:
```env
APP_DEBUG=true
APP_ENV=development
LOG_LEVEL=debug
```

View detailed logs:
```bash
docker-compose logs -f --tail=100 service-name
```

### 15.4 Health Check Commands

```bash
# All services health
for port in 8001 8002 8003 8004 8005 8006 8007; do
  echo "Port $port:"
  curl -s http://localhost:$port/health | jq .
done

# Database
docker exec aidly-postgres pg_isready -U aidly_user

# Redis
docker exec aidly-redis redis-cli ping

# MinIO
curl http://localhost:9000/minio/health/live
```

---

## Appendix

### A. Glossary

**Agent**: Support team member who handles tickets
**Client**: Customer who submits tickets
**Ticket**: Support request or issue
**SLA**: Service Level Agreement - response/resolution time targets
**IMAP**: Internet Message Access Protocol - for receiving emails
**SMTP**: Simple Mail Transfer Protocol - for sending emails
**JWT**: JSON Web Token - authentication mechanism
**RBAC**: Role-Based Access Control
**TOTP**: Time-based One-Time Password - for 2FA
**MinIO**: S3-compatible object storage
**Lumen**: Lightweight PHP microframework by Laravel

### B. Port Reference

| Service | Port | Protocol |
|---------|------|----------|
| Frontend | 3000 | HTTP |
| Auth Service | 8001 | HTTP |
| Ticket Service | 8002 | HTTP |
| Client Service | 8003 | HTTP |
| Notification Service | 8004 | HTTP |
| Email Service | 8005 | HTTP |
| AI Integration Service | 8006 | HTTP |
| Analytics Service | 8007 | HTTP |
| PostgreSQL | 5432 | PostgreSQL |
| Redis | 6379 | Redis |
| MinIO API | 9000 | HTTP |
| MinIO Console | 9001 | HTTP |

### C. File Locations

**Services**: `/root/AidlY/services/`
**Frontend**: `/root/AidlY/frontend/`
**Database Init**: `/root/AidlY/docker/init-scripts/`
**Docker Compose**: `/root/AidlY/docker-compose.yml`
**Environment**: `/root/AidlY/.env`
**Logs**: `/root/AidlY/logs/` or `docker-compose logs`

### D. Useful Commands

```bash
# Start everything
docker-compose up -d

# Restart single service
docker-compose restart ticket-service

# View logs
docker-compose logs -f ticket-service

# Execute command in container
docker exec -it aidly-ticket-service bash

# Database shell
docker exec -it aidly-postgres psql -U aidly_user -d aidly

# Redis CLI
docker exec -it aidly-redis redis-cli -a redis_secret_2024

# Clear Redis cache
docker exec -it aidly-redis redis-cli -a redis_secret_2024 FLUSHDB

# Backup database
docker exec aidly-postgres pg_dump -U aidly_user aidly > backup.sql

# Restore database
docker exec -i aidly-postgres psql -U aidly_user aidly < backup.sql
```

### E. Support and Resources

**Documentation**: This manual
**Issue Tracking**: GitHub Issues
**Code Repository**: [Repository URL]
**Development Team**: [Contact Information]

---

**End of Manual**

This documentation is maintained by the AidlY development team. For updates, corrections, or questions, please contact the team or submit a pull request.

**Version History**:
- v1.0.0 (2025-10-06): Initial comprehensive manual
