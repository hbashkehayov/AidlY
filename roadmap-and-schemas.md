# AidlY - Comprehensive Roadmap & System Schemas

## 🎯 Project Vision
Build a modern, AI-ready customer support platform that rivals Freshdesk, with microservices architecture enabling infinite scalability, seamless CRM integration capabilities, and prepared infrastructure for future AI enhancement.

---

## 📊 System Architecture Visualization

```
┌──────────────────────────────────────────────────────────────────────┐
│                           CLIENT LAYER                               │
├──────────────────────────────────────────────────────────────────────┤
│  Web App (Next.js)  │  Mobile App  │  Widget SDK  │  Public API     │
└──────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌──────────────────────────────────────────────────────────────────────┐
│                         API GATEWAY (Kong/Nginx)                     │
│            Rate Limiting │ Auth │ Routing │ Load Balancing          │
└──────────────────────────────────────────────────────────────────────┘
                                    │
        ┌───────────────────────────┼───────────────────────────┐
        ▼                           ▼                           ▼
┌─────────────────┐       ┌─────────────────┐       ┌─────────────────┐
│  Auth Service   │       │ Ticket Service  │       │ Client Service  │
│    (Lumen)      │       │    (Lumen)      │       │    (Lumen)      │
└─────────────────┘       └─────────────────┘       └─────────────────┘
        │                           │                           │
        ▼                           ▼                           ▼
┌──────────────────────────────────────────────────────────────────────┐
│                    MESSAGE QUEUE (RabbitMQ)                          │
└──────────────────────────────────────────────────────────────────────┘
        │                           │                           │
        ▼                           ▼                           ▼
┌─────────────────┐       ┌─────────────────┐       ┌─────────────────┐
│ Email Service   │       │ AI Integration  │       │Notification Svc │
│   (Lumen)       │       │   Service       │       │    (Lumen)      │
└─────────────────┘       │ (Webhooks Ready)│       └─────────────────┘
                          └─────────────────┘
                                    │
┌──────────────────────────────────────────────────────────────────────┐
│                      DATA LAYER                                      │
├────────────────┬─────────────────┬──────────────────────────────────┤
│  PostgreSQL    │     Redis        │        MinIO/S3                 │
│  (Primary DB)  │    (Cache)       │    (File Storage)               │
└────────────────┴─────────────────┴──────────────────────────────────┘
```

---

## 🗺️ Development Roadmap

### **Phase 1: Foundation (Weeks 1-2)**

#### Sprint 1.1: Infrastructure Setup ✅ COMPLETED
```yaml
Timeline: Days 1-3
Status: ✅ COMPLETED
Tasks:
  - ✅ Initialize Git repository with .gitignore
  - ✅ Create Docker Compose configuration
  - ✅ Set up PostgreSQL with initial database
  - ✅ Configure Redis for caching
  - ✅ Set up MinIO for file storage
  - ✅ Configure development environment variables

Deliverables:
  - ✅ Working Docker environment (docker-compose.yml)
  - ✅ Database connectivity (PostgreSQL with initial schema)
  - ✅ Basic project structure (folders and configuration files)

Completed Files:
  - .gitignore
  - docker-compose.yml
  - docker/init-scripts/01-create-schema.sql
  - .env.example
  - README.md
```

#### Sprint 1.2: Authentication Foundation ✅ COMPLETED
```yaml
Timeline: Days 4-7
Status: ✅ COMPLETED
Tasks:
  - ✅ Build custom JWT authentication service in Lumen
  - ✅ Implement user registration and login
  - ✅ Create password reset functionality
  - ✅ Set up role-based access control (RBAC)
  - ✅ Implement refresh token mechanism
  - ✅ Add session management

Deliverables:
  - ✅ Working authentication system (Lumen with Firebase JWT)
  - ✅ User registration/login APIs
  - ✅ Role and permission management (middleware implemented)
  - ✅ Secure token handling (with blacklisting)

Completed Files:
  - services/auth-service/ (complete Lumen project)
  - app/Models/User.php
  - app/Services/JwtService.php
  - app/Http/Controllers/AuthController.php
  - app/Http/Middleware/JwtMiddleware.php
  - app/Http/Middleware/RoleMiddleware.php
  - app/Http/Middleware/PermissionMiddleware.php
  - app/Http/Middleware/CorsMiddleware.php
  - routes/web.php (all auth routes)
  - API_DOCUMENTATION.md
```

#### Sprint 1.3: API Gateway & Service Discovery ✅ COMPLETED
```yaml
Timeline: Days 8-10
Status: ✅ COMPLETED
Tasks:
  - ✅ Configure Kong API Gateway (latest version)
  - ✅ Set up service routing (5 microservices configured)
  - ✅ Implement rate limiting (per-service rate limits)
  - ✅ Configure CORS policies (development + production ready)
  - ✅ Set up health checks (comprehensive monitoring script)
  - ✅ Add request/response logging (Prometheus + file logging)

Deliverables:
  - ✅ Centralized API entry point (Kong on port 8000)
  - ✅ Service routing configuration (declarative Kong config)
  - ✅ Basic security policies (CORS, rate limiting, auth ready)
  - ✅ API monitoring setup (health checks, metrics, alerting)

Completed Files:
  - docker/kong/kong.yml (Kong declarative configuration)
  - docker/kong/kong-setup.sh (Kong automation script)
  - docker/kong/health-check.sh (health monitoring)
  - docker/kong/docker-compose.monitor.yml (monitoring stack)
  - docker/monitoring/prometheus.yml (metrics config)
  - docker/monitoring/alertmanager.yml (alerting config)
  - docker/monitoring/alert_rules.yml (monitoring rules)
  - docker/kong/README.md (comprehensive documentation)
  - docker-compose.yml (updated with Kong services)

Kong Services Configured:
  - auth-service (port 8001) → /api/v1/auth/* (public + protected routes)
  - ticket-service (port 8002) → /api/v1/tickets/* (JWT protected)
  - client-service (port 8003) → /api/v1/clients/* (JWT protected)
  - notification-service (port 8004) → /api/v1/notifications/* (JWT protected)
  - email-service (port 8005) → /api/v1/emails/* (JWT protected)

Features Implemented:
  - Rate limiting: 30-100 req/min per service
  - CORS: Full cross-origin support for development
  - Authentication: Key-auth plugin ready for JWT tokens
  - Security headers: X-Frame-Options, CSP, etc.
  - Monitoring: Prometheus metrics + health checks
  - Logging: Request/response logging with rotation
  - Service discovery: Automatic upstream health monitoring
```

### **Phase 2: Core Ticket System (Weeks 3-5)** ✅ COMPLETED

#### Sprint 2.1: Ticket Service Backend ✅ COMPLETED
```yaml
Timeline: Days 11-15
Status: ✅ COMPLETED (100% - All requirements met)
Tasks:
  - ✅ Create Lumen ticket service
  - ✅ Implement ticket CRUD operations
  - ✅ Design ticket status workflow
  - ✅ Create assignment logic
  - ✅ Implement priority system

API Endpoints:
  ✅ POST   /api/v1/tickets
  ✅ GET    /api/v1/tickets
  ✅ GET    /api/v1/tickets/{id}
  ✅ PUT    /api/v1/tickets/{id}
  ✅ DELETE /api/v1/tickets/{id}
  ✅ POST   /api/v1/tickets/{id}/assign
  ✅ POST   /api/v1/tickets/{id}/comments
  ✅ GET    /api/v1/tickets/{id}/history

Completed Files:
  - services/ticket-service/ (complete Lumen project)
  - app/Models/Ticket.php (313 lines)
  - app/Models/TicketComment.php (125 lines)
  - app/Models/TicketHistory.php (96 lines)
  - app/Models/Category.php (92 lines)
  - app/Http/Controllers/TicketController.php (493 lines)
  - app/Http/Controllers/CategoryController.php (252 lines)
  - database/migrations/ (4 migration files)
  - routes/web.php (all ticket routes)

Additional Features Implemented:
  - ✅ Statistics endpoint (/api/v1/tickets/stats)
  - ✅ Ticket number generation (TKT-000001 format)
  - ✅ Audit trail with automatic history logging
  - ✅ Advanced filtering and search
  - ✅ Pagination with configurable limits
  - ✅ 7 status states with transitions
  - ✅ 4 priority levels with color coding
  - ✅ Overdue ticket tracking
  - ✅ Soft delete functionality
```

#### Sprint 2.2: Client Management ✅ COMPLETED
```yaml
Timeline: Days 16-20
Status: ✅ COMPLETED (100% - All requirements met)
Tasks:
  - ✅ Create client service
  - ✅ Implement client CRUD
  - ✅ Link clients to tickets
  - ✅ Create client merge functionality
  - ✅ Implement client history tracking

API Endpoints:
  ✅ POST   /api/v1/clients
  ✅ GET    /api/v1/clients
  ✅ GET    /api/v1/clients/{id}
  ✅ PUT    /api/v1/clients/{id}
  ✅ DELETE /api/v1/clients/{id}
  ✅ POST   /api/v1/clients/merge
  ✅ POST   /api/v1/clients/merge/preview
  ✅ GET    /api/v1/clients/{id}/tickets
  ✅ POST   /api/v1/clients/{id}/block
  ✅ POST   /api/v1/clients/{id}/vip
  ✅ POST   /api/v1/clients/{id}/tags
  ✅ DELETE /api/v1/clients/{id}/tags

Client Notes Endpoints:
  ✅ GET    /api/v1/clients/{clientId}/notes
  ✅ POST   /api/v1/clients/{clientId}/notes
  ✅ GET    /api/v1/clients/{clientId}/notes/{noteId}
  ✅ PUT    /api/v1/clients/{clientId}/notes/{noteId}
  ✅ DELETE /api/v1/clients/{clientId}/notes/{noteId}
  ✅ POST   /api/v1/clients/{clientId}/notes/{noteId}/pin

Completed Files:
  - services/client-service/ (complete Lumen project)
  - app/Models/Client.php (172 lines)
  - app/Models/ClientNote.php (82 lines)
  - app/Models/ClientMerge.php (64 lines)
  - app/Http/Controllers/ClientController.php (385 lines)
  - app/Http/Controllers/ClientNoteController.php (203 lines)
  - app/Http/Controllers/ClientMergeController.php (267 lines)
  - database/migrations/ (3 migration files)
  - routes/web.php (all client routes)

Additional Features Implemented:
  - ✅ Advanced client search and filtering
  - ✅ Client merge with 3 merge strategies (keep_primary, prefer_newest, prefer_complete)
  - ✅ Client notes with pin functionality
  - ✅ VIP and blocked client management
  - ✅ Tag management for clients
  - ✅ Audit trail with client merge history
  - ✅ Soft delete functionality
  - ✅ UUID-based primary keys
  - ✅ Advanced filtering (by company, VIP status, blocked status, tags)
  - ✅ Pagination with configurable limits
  - ✅ Client notes with creation and pinning
```

#### Sprint 2.3: Frontend Foundation ✅ PARTIALLY COMPLETED
```yaml
Timeline: Days 21-25
Status: ✅ PARTIALLY COMPLETED (80% - Core structure completed)
Tasks:
  - ✅ Initialize Next.js project (Next.js 15 with TypeScript)
  - ✅ Set up Tailwind CSS (Tailwind CSS 4 with modern config)
  - ✅ Create component library structure (shadcn/ui components)
  - ✅ Implement authentication flow (login page and auth layout)
  - ✅ Build main layout components (sidebar, navigation, layouts)

Pages Implemented:
  - ✅ /login (authentication page)
  - ✅ /dashboard (main dashboard)
  - ✅ /tickets (ticket list page)
  - ✅ /tickets/[id] (detailed ticket view with 3-column layout)
  - ✅ /customers (client management)
  - ✅ /messages (messages/communication)
  - ✅ /settings (application settings)

Additional Frontend Features Implemented:
  - ✅ Modern UI components (shadcn/ui with Radix primitives)
  - ✅ Rich text editor (TipTap integration)
  - ✅ State management (Zustand + TanStack Query)
  - ✅ Form handling (React Hook Form + Zod validation)
  - ✅ Charts and analytics (Recharts)
  - ✅ Toast notifications (Sonner)
  - ✅ Theme support (next-themes)
  - ✅ Responsive design (Tailwind responsive utilities)

Completed Files:
  - frontend/package.json (38 TypeScript/React files)
  - All essential pages and layouts
  - Component library with 20+ reusable components
  - Advanced ticket detail page with Freshdesk-style layout
```

### **Phase 3: Communication Layer (Weeks 6-7)**

#### Sprint 3.1: Email Integration ✅ COMPLETED
```yaml
Timeline: Days 26-30
Status: ✅ COMPLETED
Tasks:
  - ✅ Implement IMAP email fetching (webklex/php-imap)
  - ✅ Create email-to-ticket conversion with threading
  - ✅ Set up SMTP for sending (Symfony Mailer)
  - ✅ Implement email templates with variables
  - ✅ Create email queue processing (Redis-based)
  - ✅ Add Kong API Gateway routing
  - ✅ Create Docker containerization

Features:
  - ✅ Auto-ticket creation from emails
  - ✅ Email duplicate detection (message-ID based)
  - ✅ Thread conversation tracking (In-Reply-To, References)
  - ✅ Attachment handling (base64, file validation)
  - ✅ Multi-account IMAP/SMTP support
  - ✅ Template system with variable substitution
  - ✅ Background processing with Supervisor
  - ✅ Connection testing endpoints
  - ✅ Comprehensive error handling and retry logic

Completed Files:
  - services/email-service/ (complete Lumen microservice)
  - services/email-service/app/Services/ImapService.php
  - services/email-service/app/Services/SmtpService.php
  - services/email-service/app/Services/EmailToTicketService.php
  - services/email-service/app/Models/EmailAccount.php
  - services/email-service/app/Models/EmailQueue.php
  - services/email-service/app/Models/EmailTemplate.php
  - Updated docker-compose.yml with email service
  - Updated Kong configuration for email routing
```

#### Sprint 3.2: Notification System ✅ COMPLETED
```yaml
Timeline: Days 31-35
Status: ✅ COMPLETED
Tasks:
  - ✅ Create notification service (Lumen microservice)
  - ✅ Implement real-time updates (WebSocket with Pusher)
  - ✅ Set up email notifications (with templates)
  - ✅ Create in-app notifications (via WebSocket)
  - ✅ Implement notification preferences (user/client settings)

Channels:
  - ✅ Email notifications (immediate & digest modes)
  - ✅ In-app alerts (real-time via WebSocket)
  - ✅ Browser push notifications (structure ready)
  - ✅ SMS ready (interface prepared for future implementation)

Features Implemented:
  - ✅ Multi-channel notification delivery
  - ✅ User preference management (DND, quiet hours, digest)
  - ✅ Notification templates with variable substitution
  - ✅ WebSocket authentication for private channels
  - ✅ Batch notifications and bulk sending
  - ✅ Priority-based notification handling
  - ✅ Retry mechanism for failed notifications
  - ✅ Notification grouping and threading
  - ✅ Read/unread status tracking
  - ✅ Webhook endpoints for external triggers

Completed Files:
  - services/notification-service/ (complete Lumen microservice)
  - app/Models/Notification.php
  - app/Models/NotificationPreference.php
  - app/Models/NotificationTemplate.php
  - app/Services/WebSocketService.php (Pusher integration)
  - app/Services/EmailNotificationService.php
  - app/Services/NotificationManager.php
  - app/Http/Controllers/NotificationController.php
  - database/migrations/ (3 migration files)
  - routes/web.php (all notification routes)

API Endpoints:
  ✅ GET    /api/v1/notifications (list notifications)
  ✅ GET    /api/v1/notifications/unread (unread only)
  ✅ GET    /api/v1/notifications/stats (statistics)
  ✅ POST   /api/v1/notifications (create notification)
  ✅ POST   /api/v1/notifications/bulk (bulk send)
  ✅ POST   /api/v1/notifications/{id}/read (mark as read)
  ✅ DELETE /api/v1/notifications/{id} (delete notification)
  ✅ GET    /api/v1/preferences (get preferences)
  ✅ PUT    /api/v1/preferences (update preferences)
  ✅ POST   /api/v1/websocket/auth (WebSocket authentication)
```

### **Phase 4: AI Integration Preparation (Weeks 8-9)**

#### Sprint 4.1: AI-Ready Infrastructure ✅ COMPLETED
```yaml
Timeline: Days 36-40
Status: ✅ COMPLETED
Tasks:
  - ✅ Create AI Integration Service structure
  - ✅ Build webhook endpoints for future AI services
  - ✅ Design database schema for AI suggestions (already exists)
  - ✅ Implement queue system for async AI processing
  - ✅ Create abstraction layer for AI providers
  - ✅ Create monitoring for AI service health

Deliverables:
  - ✅ Webhook infrastructure for AI services
  - ✅ Database tables for AI data storage (ai_configurations, ai_processing_queue)
  - ✅ Queue system for background processing (ProcessAIRequestJob)
  - ✅ API contracts for AI integration

Completed Files:
  - services/ai-integration-service/ (complete Lumen service)
  - services/ai-integration-service/routes/web.php
  - services/ai-integration-service/config/ai.php
  - services/ai-integration-service/config/webhooks.php
  - services/ai-integration-service/config/queue.php
  - services/ai-integration-service/app/Http/Controllers/WebhookController.php
  - services/ai-integration-service/app/Http/Controllers/MonitoringController.php
  - services/ai-integration-service/app/Services/AIProviderInterface.php
  - services/ai-integration-service/app/Services/Providers/OpenAIProvider.php
  - services/ai-integration-service/app/Jobs/ProcessAIRequestJob.php
  - services/ai-integration-service/Dockerfile
  - services/ai-integration-service/.env.example
  - Updated docker-compose.yml with ai-integration-service

Key Features Implemented:
  - Webhook endpoints for OpenAI, Anthropic, Gemini, and custom providers
  - Async job processing with retry mechanisms and backoff
  - Provider abstraction supporting multiple AI operations:
    * Ticket categorization
    * Ticket prioritization
    * Response suggestions
    * Sentiment analysis
    * Entity extraction
    * Summarization
    * KB article generation
  - Comprehensive monitoring endpoints:
    * /api/v1/monitoring/metrics
    * /api/v1/monitoring/health
    * /api/v1/monitoring/performance
    * /api/v1/monitoring/errors
  - Feature flags for gradual rollout
  - Rate limiting and security measures
  - Provider health checks

Service Configuration:
  - Port: 8006
  - Container: aidly-ai-service
  - Dependencies: PostgreSQL, Redis, RabbitMQ
```

#### Sprint 4.2: AI Integration Points ✅ COMPLETED
```yaml
Timeline: Days 41-45 ✅ DONE
Tasks:
  ✅ Add AI suggestion fields to ticket schema
  ✅ Create placeholder UI for AI suggestions
  ✅ Build configuration system for AI settings
  ✅ Implement feature flags for AI features
  ✅ Create monitoring for AI service health

Preparations:
  ✅ Ticket categorization hooks
  ✅ Priority detection interface
  ✅ Response suggestion placeholders
  ✅ Language detection readiness
  ✅ Sentiment analysis fields
  ✅ n8n webhook endpoints (for future use)

Implementation Details:
  - Enhanced ticket schema with AI fields (sentiment, language, suggestions)
  - AISuggestionsPanel React component with confidence indicators
  - AIConfiguration model with provider management (OpenAI, Claude, Gemini, n8n)
  - FeatureFlagService with Redis caching and percentage rollouts
  - AIMonitoringController with health checks and metrics
  - Feature flag API endpoints for runtime configuration
```

### **Phase 5: Analytics & Reporting (Weeks 10-11)**

#### Sprint 5.1: Analytics Service
```yaml
Timeline: Days 46-50
Tasks:
  - Create analytics microservice
  - Implement data aggregation
  - Build reporting engine
  - Create export functionality
  - Set up scheduled reports

Metrics:
  - Ticket volume trends
  - Resolution times
  - Agent performance
  - Customer satisfaction
  - SLA compliance
```

#### Sprint 5.2: Dashboard Implementation
```yaml
Timeline: Days 51-55
Tasks:
  - Build admin dashboard
  - Create agent dashboard
  - Implement real-time charts
  - Add filtering capabilities
  - Create custom reports builder

Components:
  - KPI widgets
  - Chart components
  - Data tables
  - Export tools
```

### **Phase 6: Advanced Features (Weeks 12-13)**

#### Sprint 6.1: Knowledge Base
```yaml
Timeline: Days 56-60
Tasks:
  - Create knowledge base service
  - Implement article management
  - Build search functionality
  - Create public portal
  - Implement version control

Features:
  - Article CRUD
  - Categories & tags
  - Full-text search
  - Public/Internal articles
  - Article suggestions
```

#### Sprint 6.2: Automation & Workflows
```yaml
Timeline: Days 61-65
Tasks:
  - Build automation rule engine
  - Create trigger system
  - Implement SLA management
  - Build escalation workflows
  - Create macro system

Automations:
  - Auto-assignment rules
  - Escalation triggers
  - SLA breach alerts
  - Follow-up reminders
  - Bulk operations
```

### **Phase 7: CRM Integration (Weeks 14-15)**

#### Sprint 7.1: Integration Framework
```yaml
Timeline: Days 66-70
Tasks:
  - Design integration architecture
  - Create webhook system
  - Build REST API documentation
  - Implement OAuth2 flow
  - Create sync mechanisms

Integrations:
  - Salesforce connector
  - HubSpot connector
  - Custom CRM API
  - Zapier/Make ready
```

#### Task 7.2: Performance Optimization & Security Audit
```yaml
Agent Assignment: DevOps Agent + Security Agent
Duration: 15 hours

Step-by-Step Tasks:
  1. Database optimization:
     # Add indexes
     CREATE INDEX idx_tickets_status ON tickets(status);
     CREATE INDEX idx_tickets_client_id ON tickets(client_id);
     CREATE INDEX idx_tickets_assigned_agent ON tickets(assigned_agent_id);

     # Analyze queries
     EXPLAIN ANALYZE SELECT * FROM tickets WHERE status = 'open';

  2. Implement caching:
     // Add Redis caching to ticket service
     class TicketService {
         public function getTicket($id) {
             $cached = Cache::get("ticket:{$id}");
             if ($cached) return $cached;

             $ticket = Ticket::find($id);
             Cache::put("ticket:{$id}", $ticket, 300);
             return $ticket;
         }
     }

  3. Security audit:
     # Run OWASP ZAP scan
     docker run -t owasp/zap2docker-stable zap-baseline.py \
       -t https://localhost:8000

     # Check dependencies
     composer audit
     npm audit

  4. Performance testing:
     k6 run --vus 100 --duration 30s load-test.js

  5. Documentation:
     - API documentation with Swagger
     - Deployment guide
     - User manual

Deliverables:
  - Performance improved by 50%
  - Security vulnerabilities fixed
  - Complete documentation
```

---

## 📚 Step-by-Step Implementation Commands

### **Quick Start Guide for Each Service**

#### **Authentication Service Setup**
```bash
# Step 1: Navigate to service directory
cd services/auth-service

# Step 2: Install dependencies
composer create-project --prefer-dist laravel/lumen .
composer require tymon/jwt-auth
composer require illuminate/redis

# Step 3: Configure environment
cp .env.example .env
php artisan key:generate
php artisan jwt:secret

# Step 4: Run migrations
php artisan migrate

# Step 5: Start service
php -S localhost:8001 -t public
```

#### **Ticket Service Setup**
```bash
# Step 1: Navigate to service directory
cd services/ticket-service

# Step 2: Install dependencies
composer create-project --prefer-dist laravel/lumen .

# Step 3: Configure database connection in .env
DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=aidly
DB_USERNAME=aidly_user
DB_PASSWORD=your_password

# Step 4: Create and run migrations
php artisan make:migration create_tickets_table
php artisan make:migration create_ticket_comments_table
php artisan make:migration create_ticket_history_table
php artisan migrate

# Step 5: Start service
php -S localhost:8002 -t public
```

#### **Frontend Setup**
```bash
# Step 1: Navigate to frontend directory
cd frontend

# Step 2: Initialize Next.js with TypeScript
npx create-next-app@latest . --typescript --tailwind --app

# Step 3: Install required packages
npm install axios react-query zustand
npm install @radix-ui/react-dialog @radix-ui/react-dropdown-menu
npm install react-hook-form zod @hookform/resolvers
npm install recharts date-fns

# Step 4: Configure environment variables
echo "NEXT_PUBLIC_API_URL=http://localhost:8000" > .env.local

# Step 5: Start development server
npm run dev
```

#### **Docker Services Startup**
```bash
# Step 1: Start all infrastructure services
docker-compose up -d postgres redis rabbitmq

# Step 2: Wait for services to be ready
sleep 10

# Step 3: Create databases
docker exec -it postgres psql -U postgres -c "CREATE DATABASE aidly;"
docker exec -it postgres psql -U postgres -c "CREATE USER aidly_user WITH PASSWORD 'password';"
docker exec -it postgres psql -U postgres -c "GRANT ALL PRIVILEGES ON DATABASE aidly TO aidly_user;"

# Step 4: Verify services are running
docker ps
```

---

## 🗄️ Database Schemas

### **1. Authentication Schema**

```sql
-- Users (Native authentication)
CREATE TABLE users (
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

-- Password Reset Tokens
CREATE TABLE password_resets (
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (email, token)
);

-- User Sessions
CREATE TABLE sessions (
    id VARCHAR(255) PRIMARY KEY,
    user_id UUID NOT NULL,
    ip_address INET,
    user_agent TEXT,
    payload TEXT NOT NULL,
    last_activity TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Departments/Teams
CREATE TABLE departments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    description TEXT,
    manager_id UUID,
    parent_department_id UUID,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (manager_id) REFERENCES users(id),
    FOREIGN KEY (parent_department_id) REFERENCES departments(id)
);

-- Permissions
CREATE TABLE permissions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    resource VARCHAR(255) NOT NULL,
    action VARCHAR(50) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(resource, action)
);

-- Role Permissions
CREATE TABLE role_permissions (
    role VARCHAR(50) NOT NULL,
    permission_id UUID NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (role, permission_id),
    FOREIGN KEY (permission_id) REFERENCES permissions(id)
);
```

### **2. Ticket Management Schema**

```sql
-- Ticket Statuses Enum
CREATE TYPE ticket_status AS ENUM (
    'new',
    'open',
    'pending',
    'on_hold',
    'resolved',
    'closed',
    'cancelled'
);

-- Ticket Priorities Enum
CREATE TYPE ticket_priority AS ENUM (
    'low',
    'medium',
    'high',
    'urgent'
);

-- Ticket Sources Enum
CREATE TYPE ticket_source AS ENUM (
    'email',
    'web_form',
    'chat',
    'phone',
    'social_media',
    'api',
    'internal'
);

-- Main Tickets Table
CREATE TABLE tickets (
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
    ai_provider VARCHAR(50), -- 'openai', 'claude', 'custom', etc.
    ai_model_version VARCHAR(50),
    ai_webhook_url TEXT, -- For external AI service callbacks

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

-- Ticket Comments/Replies
CREATE TABLE ticket_comments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ticket_id UUID NOT NULL,
    user_id UUID,
    client_id UUID,
    content TEXT NOT NULL,
    is_internal_note BOOLEAN DEFAULT false,
    is_ai_generated BOOLEAN DEFAULT false,
    ai_suggestion_used TEXT,
    attachments JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (client_id) REFERENCES clients(id),
    CHECK ((user_id IS NOT NULL AND client_id IS NULL) OR
           (user_id IS NULL AND client_id IS NOT NULL))
);

-- Ticket History/Audit Log
CREATE TABLE ticket_history (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ticket_id UUID NOT NULL,
    user_id UUID,
    action VARCHAR(100) NOT NULL,
    field_name VARCHAR(100),
    old_value TEXT,
    new_value TEXT,
    metadata JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Ticket Attachments
CREATE TABLE attachments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ticket_id UUID,
    comment_id UUID,
    uploaded_by_user_id UUID,
    uploaded_by_client_id UUID,
    file_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(100),
    file_size INTEGER,
    storage_path TEXT NOT NULL,
    mime_type VARCHAR(100),
    is_inline BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (comment_id) REFERENCES ticket_comments(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id),
    FOREIGN KEY (uploaded_by_client_id) REFERENCES clients(id)
);

-- Categories for Tickets
CREATE TABLE categories (
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

-- Ticket Relationships (for merging, parent-child)
CREATE TABLE ticket_relationships (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    parent_ticket_id UUID NOT NULL,
    child_ticket_id UUID NOT NULL,
    relationship_type VARCHAR(50) NOT NULL, -- 'merged', 'related', 'duplicate'
    created_by UUID NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (parent_ticket_id) REFERENCES tickets(id),
    FOREIGN KEY (child_ticket_id) REFERENCES tickets(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    UNIQUE(parent_ticket_id, child_ticket_id)
);
```

### **3. Client Management Schema**

```sql
-- Clients/Customers
CREATE TABLE clients (
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

-- Client Merge History
CREATE TABLE client_merges (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    primary_client_id UUID NOT NULL,
    merged_client_id UUID NOT NULL,
    merged_by UUID NOT NULL,
    merge_data JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (primary_client_id) REFERENCES clients(id),
    FOREIGN KEY (merged_by) REFERENCES users(id)
);

-- Client Notes
CREATE TABLE client_notes (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    client_id UUID NOT NULL,
    created_by UUID NOT NULL,
    note TEXT NOT NULL,
    is_pinned BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
);
```

### **4. Email Integration Schema**

```sql
-- Email Accounts Configuration
CREATE TABLE email_accounts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    email_address VARCHAR(255) NOT NULL,

    -- IMAP Settings
    imap_host VARCHAR(255),
    imap_port INTEGER,
    imap_username VARCHAR(255),
    imap_password_encrypted TEXT,
    imap_use_ssl BOOLEAN DEFAULT true,

    -- SMTP Settings
    smtp_host VARCHAR(255),
    smtp_port INTEGER,
    smtp_username VARCHAR(255),
    smtp_password_encrypted TEXT,
    smtp_use_tls BOOLEAN DEFAULT true,

    -- Configuration
    department_id UUID,
    auto_create_tickets BOOLEAN DEFAULT true,
    default_ticket_priority ticket_priority DEFAULT 'medium',
    default_category_id UUID,

    is_active BOOLEAN DEFAULT true,
    last_sync_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (default_category_id) REFERENCES categories(id)
);

-- Email Queue
CREATE TABLE email_queue (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email_account_id UUID NOT NULL,
    message_id VARCHAR(500) UNIQUE,
    from_address VARCHAR(255),
    to_addresses TEXT[],
    cc_addresses TEXT[],
    subject TEXT,
    body_plain TEXT,
    body_html TEXT,
    headers JSONB,
    attachments JSONB,

    -- Processing
    ticket_id UUID,
    is_processed BOOLEAN DEFAULT false,
    processed_at TIMESTAMP,
    error_message TEXT,
    retry_count INTEGER DEFAULT 0,

    received_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (email_account_id) REFERENCES email_accounts(id),
    FOREIGN KEY (ticket_id) REFERENCES tickets(id)
);

-- Email Templates
CREATE TABLE email_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    subject VARCHAR(500) NOT NULL,
    body_html TEXT NOT NULL,
    body_plain TEXT,
    category VARCHAR(100),
    variables JSONB,
    is_active BOOLEAN DEFAULT true,
    created_by UUID NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (created_by) REFERENCES users(id)
);
```

### **5. SLA & Automation Schema**

```sql
-- SLA Policies
CREATE TABLE sla_policies (
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

-- Business Hours Configuration
CREATE TABLE business_hours (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    timezone VARCHAR(50) NOT NULL,

    -- Schedule (JSONB for flexibility)
    schedule JSONB NOT NULL,
    /* Example:
    {
        "monday": {"start": "09:00", "end": "18:00"},
        "tuesday": {"start": "09:00", "end": "18:00"},
        ...
        "holidays": ["2024-12-25", "2024-01-01"]
    }
    */

    is_default BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Automation Rules
CREATE TABLE automation_rules (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    description TEXT,

    -- Trigger
    trigger_type VARCHAR(100) NOT NULL, -- 'ticket_created', 'ticket_updated', 'time_based', etc.
    trigger_conditions JSONB,

    -- Actions
    actions JSONB NOT NULL,
    /* Example:
    [
        {"type": "assign_agent", "agent_id": "..."},
        {"type": "set_priority", "priority": "high"},
        {"type": "send_email", "template_id": "..."}
    ]
    */

    -- Configuration
    execution_order INTEGER DEFAULT 100,
    stop_processing BOOLEAN DEFAULT false,
    is_active BOOLEAN DEFAULT true,

    -- Stats
    last_triggered_at TIMESTAMP,
    trigger_count INTEGER DEFAULT 0,

    created_by UUID NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Macros (Saved Action Sets)
CREATE TABLE macros (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    description TEXT,
    actions JSONB NOT NULL,
    is_public BOOLEAN DEFAULT false,
    created_by UUID NOT NULL,
    usage_count INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (created_by) REFERENCES users(id)
);
```

### **6. Knowledge Base Schema**

```sql
-- Knowledge Base Articles
CREATE TABLE kb_articles (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title VARCHAR(500) NOT NULL,
    slug VARCHAR(500) UNIQUE NOT NULL,
    content TEXT NOT NULL,
    summary TEXT,

    -- Organization
    category_id UUID,
    tags TEXT[],

    -- Visibility
    is_public BOOLEAN DEFAULT false,
    is_featured BOOLEAN DEFAULT false,

    -- SEO
    meta_title VARCHAR(255),
    meta_description TEXT,

    -- Versioning
    version INTEGER DEFAULT 1,
    parent_article_id UUID,

    -- Stats
    view_count INTEGER DEFAULT 0,
    helpful_count INTEGER DEFAULT 0,
    not_helpful_count INTEGER DEFAULT 0,

    -- Workflow
    status VARCHAR(50) DEFAULT 'draft', -- 'draft', 'review', 'published', 'archived'
    published_at TIMESTAMP,
    reviewed_by UUID,

    author_id UUID NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (category_id) REFERENCES kb_categories(id),
    FOREIGN KEY (parent_article_id) REFERENCES kb_articles(id),
    FOREIGN KEY (author_id) REFERENCES users(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id)
);

-- Knowledge Base Categories
CREATE TABLE kb_categories (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT,
    parent_category_id UUID,
    icon VARCHAR(50),
    display_order INTEGER,
    is_public BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (parent_category_id) REFERENCES kb_categories(id)
);

-- Article Feedback
CREATE TABLE kb_feedback (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    article_id UUID NOT NULL,
    client_id UUID,
    is_helpful BOOLEAN NOT NULL,
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (article_id) REFERENCES kb_articles(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id)
);
```

### **7. Analytics & Reporting Schema**

```sql
-- AI Integration Schema (For Future Use)
CREATE TABLE ai_configurations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    provider VARCHAR(50) NOT NULL, -- 'openai', 'claude', 'n8n', 'custom'

    -- Connection settings
    api_endpoint TEXT,
    api_key_encrypted TEXT,
    webhook_secret VARCHAR(255),

    -- Configuration
    model_settings JSONB,
    retry_policy JSONB,
    timeout_seconds INTEGER DEFAULT 30,

    -- Feature flags
    enable_categorization BOOLEAN DEFAULT false,
    enable_suggestions BOOLEAN DEFAULT false,
    enable_sentiment BOOLEAN DEFAULT false,
    enable_auto_assign BOOLEAN DEFAULT false,

    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- AI Processing Queue
CREATE TABLE ai_processing_queue (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ticket_id UUID NOT NULL,
    configuration_id UUID NOT NULL,

    -- Processing details
    action_type VARCHAR(50) NOT NULL, -- 'categorize', 'suggest', 'sentiment'
    request_payload JSONB,
    response_payload JSONB,

    -- Status tracking
    status VARCHAR(50) DEFAULT 'pending', -- 'pending', 'processing', 'completed', 'failed'
    attempts INTEGER DEFAULT 0,
    error_message TEXT,

    -- Timestamps
    scheduled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP,
    completed_at TIMESTAMP,

    FOREIGN KEY (ticket_id) REFERENCES tickets(id),
    FOREIGN KEY (configuration_id) REFERENCES ai_configurations(id)
);

-- Analytics Events
CREATE TABLE analytics_events (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    event_type VARCHAR(100) NOT NULL,
    event_category VARCHAR(100),

    -- Related entities
    ticket_id UUID,
    client_id UUID,
    user_id UUID,

    -- Event data
    properties JSONB,

    -- Session info
    session_id VARCHAR(255),
    ip_address INET,
    user_agent TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (ticket_id) REFERENCES tickets(id),
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Agent Performance Metrics
CREATE TABLE agent_metrics (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    agent_id UUID NOT NULL,
    date DATE NOT NULL,

    -- Ticket Metrics
    tickets_created INTEGER DEFAULT 0,
    tickets_resolved INTEGER DEFAULT 0,
    tickets_escalated INTEGER DEFAULT 0,

    -- Time Metrics (in seconds)
    avg_first_response_time INTEGER,
    avg_resolution_time INTEGER,
    total_working_time INTEGER,

    -- Quality Metrics
    customer_satisfaction_score DECIMAL(3,2),
    internal_quality_score DECIMAL(3,2),

    -- Activity Metrics
    comments_sent INTEGER DEFAULT 0,
    internal_notes INTEGER DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (agent_id) REFERENCES users(id),
    UNIQUE(agent_id, date)
);

-- Custom Reports
CREATE TABLE reports (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    description TEXT,
    report_type VARCHAR(100) NOT NULL,

    -- Configuration
    query TEXT,
    filters JSONB,
    columns JSONB,
    chart_config JSONB,

    -- Scheduling
    schedule_config JSONB,
    recipients TEXT[],

    -- Access
    is_public BOOLEAN DEFAULT false,
    created_by UUID NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (created_by) REFERENCES users(id)
);
```

### **8. Integration & Webhook Schema**

```sql
-- External Integrations
CREATE TABLE integrations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    type VARCHAR(100) NOT NULL, -- 'crm', 'chat', 'email', 'custom'

    -- Configuration
    config JSONB NOT NULL,
    credentials_encrypted TEXT,

    -- Mappings
    field_mappings JSONB,

    -- Status
    is_active BOOLEAN DEFAULT true,
    last_sync_at TIMESTAMP,
    last_error TEXT,

    created_by UUID NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Webhooks
CREATE TABLE webhooks (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    url TEXT NOT NULL,

    -- Events
    events TEXT[] NOT NULL,

    -- Security
    secret_key VARCHAR(255),

    -- Headers
    custom_headers JSONB,

    -- Status
    is_active BOOLEAN DEFAULT true,
    last_triggered_at TIMESTAMP,
    failure_count INTEGER DEFAULT 0,

    created_by UUID NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Webhook Logs
CREATE TABLE webhook_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    webhook_id UUID NOT NULL,
    event_type VARCHAR(100) NOT NULL,

    -- Request/Response
    request_payload JSONB,
    response_status INTEGER,
    response_body TEXT,

    -- Timing
    duration_ms INTEGER,

    -- Status
    is_success BOOLEAN,
    error_message TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (webhook_id) REFERENCES webhooks(id) ON DELETE CASCADE
);
```

---

## 🔄 API Design Pattern

### **RESTful API Structure**

```yaml
Base URL: https://api.aidly.com/v1

Authentication:
  Type: Bearer Token (JWT from Keycloak)
  Header: Authorization: Bearer <token>

Response Format:
  Success:
    {
      "success": true,
      "data": {},
      "meta": {
        "page": 1,
        "limit": 20,
        "total": 100
      }
    }

  Error:
    {
      "success": false,
      "error": {
        "code": "VALIDATION_ERROR",
        "message": "Validation failed",
        "details": []
      }
    }

Standard Endpoints Pattern:
  GET    /resource          - List with pagination
  GET    /resource/{id}     - Get single item
  POST   /resource          - Create new item
  PUT    /resource/{id}     - Full update
  PATCH  /resource/{id}     - Partial update
  DELETE /resource/{id}     - Delete item

Filtering & Sorting:
  GET /tickets?status=open&priority=high&sort=-created_at&page=2&limit=25

Relationships:
  GET /tickets/{id}/comments
  GET /clients/{id}/tickets
  POST /tickets/{id}/assign

Batch Operations:
  POST /tickets/bulk
  {
    "action": "update",
    "ids": ["id1", "id2"],
    "data": {"status": "resolved"}
  }
```

---

## 🏗️ Microservice Communication

### **Inter-Service Communication Pattern**

```yaml
Synchronous (REST):
  - Used for: Direct user requests
  - Protocol: HTTP/HTTPS
  - Format: JSON
  - Example: Frontend -> API Gateway -> Service

Asynchronous (Message Queue):
  - Used for: Background processing, notifications
  - Protocol: AMQP (RabbitMQ)
  - Pattern: Publish/Subscribe
  - Example: Ticket Created -> Queue -> Email Service

Event Sourcing:
  - All state changes emit events
  - Events stored in event store
  - Services subscribe to relevant events
  - Enables audit trail and replay

Service Discovery:
  - Services register with API Gateway
  - Health checks every 30 seconds
  - Auto-removal of unhealthy services
```

---

## 📈 Scaling Strategy

### **Horizontal Scaling Plan**

```yaml
Phase 1 (0-1000 tickets/day):
  - Single instance per service
  - Shared PostgreSQL
  - Redis for caching

Phase 2 (1000-10,000 tickets/day):
  - 2-3 instances per service
  - Read replicas for PostgreSQL
  - Dedicated Redis cluster
  - CDN for static assets

Phase 3 (10,000+ tickets/day):
  - Auto-scaling based on metrics
  - Database sharding
  - Multiple Redis instances
  - Elasticsearch for search
  - Dedicated analytics database

Caching Strategy:
  - User sessions: 30 minutes
  - Ticket lists: 5 minutes
  - Client data: 15 minutes
  - Analytics: 1 hour
  - Static content: 24 hours
```

---

## 🚀 Deployment Pipeline

### **CI/CD Workflow**

```yaml
Development:
  Branch: feature/* -> develop
  Environment: Local Docker
  Tests: Unit tests
  Deploy: Automatic to dev server

Staging:
  Branch: develop -> staging
  Environment: Staging cluster
  Tests: Unit + Integration + E2E
  Deploy: Automatic after tests pass

Production:
  Branch: staging -> main
  Environment: Production cluster
  Tests: Full test suite + smoke tests
  Deploy: Manual approval required
  Rollback: Automatic on failure

Monitoring:
  - Health checks every 30 seconds
  - Error rate monitoring
  - Performance metrics
  - Custom alerts for SLA breaches
```

---

## 📊 Success Metrics & KPIs

### **Technical Metrics**

```yaml
Performance:
  - API response time < 200ms (p95)
  - Page load time < 2 seconds
  - Database query time < 100ms
  - WebSocket latency < 50ms

Reliability:
  - Uptime > 99.9%
  - Error rate < 0.1%
  - Successful deployments > 95%

Scalability:
  - Support 10,000 concurrent users
  - Handle 100,000 tickets/day
  - Process 1M emails/day
```

### **Business Metrics**

```yaml
Efficiency:
  - Average first response time < 2 hours
  - Average resolution time < 24 hours
  - Ticket backlog < 100
  - Agent utilization > 70%

Quality:
  - Customer satisfaction > 4.5/5
  - First contact resolution > 60%
  - Escalation rate < 10%
  - AI suggestion accuracy > 80%

Growth:
  - New tickets growth rate
  - Active users growth
  - Knowledge base usage
  - Self-service resolution rate > 30%
```

---

## 🎯 Milestones & Deliverables

### **MVP (Month 1)**
- Basic ticket CRUD
- Email integration
- Simple dashboard
- User authentication

### **Beta (Month 2)**
- AI suggestions
- Knowledge base
- Advanced reporting
- Mobile responsive

### **V1.0 (Month 3)**
- Full automation suite
- CRM integrations
- Custom workflows
- White-label ready

### **V2.0 (Month 6)**
- Multi-tenant SaaS
- Advanced AI features
- Marketplace for integrations
- Enterprise features

---

## 📝 Technical Debt Management

### **Code Quality Standards**

```yaml
Code Coverage:
  - Unit tests: > 80%
  - Integration tests: > 60%
  - E2E tests: Critical paths

Documentation:
  - API documentation (OpenAPI/Swagger)
  - Code comments for complex logic
  - README for each service
  - Architecture decision records

Security:
  - Regular dependency updates
  - Security scanning in CI/CD
  - Penetration testing quarterly
  - OWASP compliance

Performance:
  - Regular performance audits
  - Database query optimization
  - Code profiling
  - Load testing before major releases
```

---

## 🔐 Security Implementation

### **Security Layers**

```yaml
Network Security:
  - WAF (Web Application Firewall)
  - DDoS protection
  - SSL/TLS everywhere
  - VPN for admin access

Application Security:
  - Input validation
  - SQL injection prevention
  - XSS protection
  - CSRF tokens
  - Rate limiting
  - JWT token validation
  - Refresh token rotation

Data Security:
  - Encryption at rest (AES-256)
  - Encryption in transit (TLS 1.3)
  - PII data masking
  - Regular backups
  - GDPR compliance
  - Password hashing (bcrypt)

Access Control:
  - Native authentication system
  - Multi-factor authentication
  - Role-based permissions (RBAC)
  - API key management
  - Session management
  - Account lockout policies
  - Audit logging
```

---

## 🌍 Internationalization Plan

### **i18n Strategy**

```yaml
Supported Languages (Phase 1):
  - English (en)
  - Spanish (es)
  - French (fr)
  - German (de)

Implementation:
  - Frontend: next-i18next
  - Backend: Laravel localization
  - Database: UTF-8 encoding
  - Timezone handling: Store in UTC

Localization Features:
  - Date/time formatting
  - Currency display
  - Number formatting
  - RTL language support ready
```

---

## 🔄 Data Migration Strategy

### **From Legacy Systems**

```yaml
Supported Imports:
  - Zendesk
  - Freshdesk
  - Intercom
  - CSV/Excel files

Migration Process:
  1. Data mapping configuration
  2. Validation and cleanup
  3. Test migration (sample)
  4. Full migration
  5. Verification
  6. Rollback plan

Data Mapping:
  - Tickets
  - Customers
  - Agents
  - Knowledge base
  - Historical data
```

---

## ✅ Summary

This comprehensive roadmap and schema document provides:

- **Microservices architecture** with clear service boundaries
- **Frontend with Next.js**, UI Kit + Tailwind/CSS-in-JS
- **Lumen backend microservices** for scalability
- **PostgreSQL database** with detailed schemas
- **Custom JWT authentication** with RBAC (no Keycloak dependency)
- **AI-ready infrastructure** with webhook support and database structure
- **CRM integration readiness** through API design
- **Complete ticket management system** with clients, attachments, history, email automation
- **Agent and admin dashboards** with analytics
- **Scalable, secure, and maintainable design**

### **Key Differentiators:**

1. **AI Integration Preparedness**: Infrastructure is ready for future AI integration without current implementation complexity
2. **Flexible Authentication**: Native auth system that can be extended with SSO later
3. **Webhook-Based AI**: Can integrate with n8n, OpenAI, Claude, or custom AI services when needed
4. **Feature Flags**: Enable/disable AI features without code changes
5. **Progressive Enhancement**: Start simple, add AI capabilities as the platform grows

The roadmap allows building a solid foundation first, with AI integration points ready when business needs arise.