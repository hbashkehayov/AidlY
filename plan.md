Customer Support System – Comprehensive Project Plan
1️⃣ Project Objectives

Build a microservice-based system, starting with the Support/Ticket module.

Frontend: Next.js with unified design system (UI Kit + Tailwind / CSS-in-JS).

Backend: Lumen microservice for API + PostgreSQL.

Centralized authentication & roles via Keycloak.

AI-assisted response suggestions using n8n.

CRM integration-ready for future expansion.

2️⃣ Architecture
2.1 Frontend

Next.js project for each module.

Shared component library (UI Kit) + Tailwind/CSS-in-JS for design consistency.

Pages:

/login, /dashboard, /tickets, /tickets/:id, /clients, /reports

Reusable components: TicketCard, TicketForm, CommentThread, AI Suggestions panel, Sidebar, Header, SearchBar.

2.2 Backend

Lumen microservice for Support module (API-only).

Cron jobs and queues for:

Checking new emails (every 5 minutes)

Running AI workflows via n8n

Microservice structure: Each module (Support, future CRM, etc.) as a separate Lumen project.

API-first: RESTful endpoints (optionally GraphQL for CRM integration).

2.3 Authentication & Roles

Keycloak as central OIDC provider.

Roles: agent, admin, supervisor.

Each backend service integrates with Keycloak to validate JWTs.

Role-based access control enforced on all endpoints.

2.4 Database

PostgreSQL with separate schemas per module.

Shared tables: users, roles, permissions

Support schema: tickets, clients, ticket_history, attachments, email_queue

3️⃣ Functional Requirements
3.1 Tickets

Automatic ticket creation from emails via cron job (every 5 min).

Duplicate email detection and ticket merging.

Ticket fields:

client_id, subject, description, status, priority (low, medium, high), assigned_agent_id, created_at, updated_at

File attachments (stored locally).

Manual assignment to agents and reassignment capability.

Full history of all changes and responses.

3.2 Clients

Automatically created upon new email.

Identification via email and phone.

Merge multiple clients into one.

Full ticket history per client.

3.3 AI / n8n Integration

Cron job scans new tickets without responses.

Sends ticket text to n8n workflow.

Receives suggested reply → stored in new field.

Agent can accept, edit, or write a new response.

History logs AI suggestions and final responses.

3.4 Dashboard & Views

Agent view: Inbox-style ticket list with filters by status, priority, client, agent, date.

Admin dashboard: Number of tickets per day, average response time, active agents, logs, and statistics.

4️⃣ Non-Functional Requirements

Microservices readiness: Each module as a separate Lumen project.

API-first architecture: REST or GraphQL.

Cron jobs / queues: For email processing and AI suggestions.

Security: JWT from Keycloak, role-based access control.

Scalability: Support module handles ~1000 tickets/day.

Logging & History: All actions are recorded for audit.

5️⃣ Database Schema (Support Module)
Table	Columns
users	id, name, keycloak_id, role, created_at, updated_at
roles	id, name, permissions
clients	id, name, email, phone, created_at, updated_at
tickets	id, client_id, subject, description, status, priority, assigned_agent_id, created_at, updated_at
ticket_history	id, ticket_id, action, old_value, new_value, user_id, created_at
attachments	id, ticket_id, file_path, file_type, created_at
email_queue	id, ticket_id, email_id, processed, created_at
6️⃣ Workflow Overview

Email arrives → check for duplicates → create/merge ticket → associate with client.

Ticket unassigned → agent assigns → status set to “In Progress.”

Cron job → sends ticket text to n8n workflow → returns suggested reply → stores in ticket.

Agent reviews suggestion → sends final reply → history is updated with AI suggestion and final answer.

7️⃣ Frontend Implementation Details

Component Library: UI Kit for buttons, forms, modals, tables.

Design: Tailwind + CSS-in-JS for dynamic styling and consistent theme.

Pages & Views:

Agent Dashboard (ticket inbox, AI suggestions)

Admin Dashboard (analytics, SLA tracking)

Ticket Details (comments, attachments, AI reply suggestions)

Client Details (ticket history, contact info)

State Management: React Query for async fetches, Redux Toolkit optional.

8️⃣ Backend Implementation Details

Lumen microservice endpoints:

POST /tickets – create ticket

GET /tickets – list tickets (filterable)

PUT /tickets/:id – update status, priority, assignment

GET /clients/:id – fetch client info and ticket history

POST /attachments – upload attachment

Cron jobs:

Email polling (every 5 min)

AI suggestion triggers

Queue management (optional): RabbitMQ/Kafka for async tasks

9️⃣ AI / n8n Integration

Workflow receives ticket text → performs classification / response suggestion.

Returns suggested reply → stored in tickets.ai_suggestion.

Agent can accept, edit, or ignore suggestion.

History (ticket_history) logs both AI suggestion and final reply.

10️⃣ Security

Keycloak JWT authentication.

Role-based access enforced on all APIs.

Sensitive data access restricted per role.

Audit logging of all actions.

11️⃣ Scalability & Performance

Designed for ~1000 tickets/day.

Separate Lumen services allow horizontal scaling.

Cron jobs and queues handle asynchronous AI processing without blocking user requests.

12️⃣ Deployment & DevOps

Docker for frontend & backend services.

CI/CD pipelines for testing and deployment.

Environment variables for DB, Keycloak, and n8n.

Monitoring & Logging: Centralized logs, alerts for cron job failures or SLA breaches.

✅ Summary

This plan covers all requirements:

Microservices architecture

Frontend with Next.js, UI Kit + Tailwind/CSS-in-JS

Lumen backend microservices

PostgreSQL database per module

Keycloak authentication & role management

AI response suggestions via n8n

CRM integration readiness

Support module with tickets, clients, attachments, history, email automation

Dashboard for agents and admins

Scalable, secure, and maintainable design