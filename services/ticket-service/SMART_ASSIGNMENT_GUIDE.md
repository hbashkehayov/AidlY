# Smart Ticket Assignment System

## Overview

The AidlY Ticket Service includes a comprehensive **Smart Assignment System** that automatically assigns incoming tickets to agents based on:

- **Workload**: Number of open/unresolved tickets
- **Availability**: Agent capacity limits
- **Department**: Department-based routing
- **Priority**: Priority-based intelligent routing
- **Strategy**: Multiple assignment algorithms

---

## Features

### ✅ Automatic Assignment on Ticket Creation
- New tickets are automatically assigned when created
- Works seamlessly with email-to-ticket conversion
- Configurable per-ticket or globally

### ✅ Multiple Assignment Strategies

1. **Least Busy** (Default)
   - Assigns to agent with fewest open tickets
   - Considers agent capacity limits
   - Prefers recently active agents

2. **Round Robin**
   - Distributes tickets evenly across agents
   - Cached for performance
   - Wraps around when reaching last agent

3. **Priority-Based**
   - High-priority tickets → Senior agents (managers/admins)
   - Normal tickets → Least busy agents
   - Considers agent's high-priority workload

4. **Skill-Based** (Future)
   - Match ticket category to agent expertise
   - Consider resolution rate by category
   - Currently falls back to least busy

### ✅ Agent Workload Management
- Real-time workload tracking
- Configurable capacity limits (default: 20 tickets)
- Automatic workload rebalancing
- Overload prevention

### ✅ Department-Based Routing
- Automatic department filtering
- Fallback to cross-department if no agents available
- Department-specific assignment rules

---

## API Endpoints

### 1. Create Ticket with Auto-Assignment

**Endpoint:** `POST /api/v1/tickets`

**Request Body:**
```json
{
  "subject": "Cannot login to account",
  "description": "I'm getting an error when trying to login",
  "client_id": "uuid-here",
  "priority": "high",
  "source": "email",
  "category_id": "uuid-here",
  "assigned_department_id": "uuid-here",
  "auto_assign": true,
  "assignment_strategy": "least_busy"
}
```

**Parameters:**
- `auto_assign` (boolean, optional): Enable auto-assignment (default: true if no agent specified)
- `assignment_strategy` (string, optional): Strategy to use
  - `least_busy` (default)
  - `round_robin`
  - `priority_based`
  - `skill_based`

**Response:**
```json
{
  "success": true,
  "data": {
    "id": "ticket-uuid",
    "ticket_number": "TKT-000123",
    "subject": "Cannot login to account",
    "status": "open",
    "assigned_agent_id": "agent-uuid",
    "priority": "high",
    ...
  },
  "message": "Ticket created successfully"
}
```

---

### 2. Get Agent Workload Statistics

**Endpoint:** `GET /api/v1/assignments/agents/workload`

**Query Parameters:**
- `department_id` (uuid, optional): Filter by department

**Request:**
```bash
GET /api/v1/assignments/agents/workload?department_id=dept-uuid
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "agent-uuid-1",
      "name": "John Doe",
      "email": "john@example.com",
      "role": "agent",
      "open_tickets": 5,
      "resolved_today": 3,
      "total_tickets": 45,
      "avg_resolution_hours": 2.5
    },
    {
      "id": "agent-uuid-2",
      "name": "Jane Smith",
      "email": "jane@example.com",
      "role": "manager",
      "open_tickets": 12,
      "resolved_today": 7,
      "total_tickets": 120,
      "avg_resolution_hours": 1.8
    }
  ]
}
```

---

### 3. Get Available Agents

**Endpoint:** `GET /api/v1/assignments/agents/available`

**Query Parameters:**
- `department_id` (uuid, optional): Filter by department
- `priority` (string, optional): Filter by ticket priority

**Request:**
```bash
GET /api/v1/assignments/agents/available?department_id=dept-uuid&priority=high
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "agent-uuid",
      "name": "John Doe",
      "email": "john@example.com",
      "role": "agent",
      "open_ticket_count": 5,
      "is_available": true
    }
  ]
}
```

---

### 4. Bulk Auto-Assign Unassigned Tickets

**Endpoint:** `POST /api/v1/assignments/auto-assign`

Manually trigger auto-assignment for multiple unassigned tickets.

**Request Body:**
```json
{
  "department_id": "uuid-here",
  "strategy": "least_busy",
  "limit": 10
}
```

**Parameters:**
- `department_id` (uuid, optional): Filter tickets by department
- `strategy` (string, optional): Assignment strategy (default: least_busy)
- `limit` (integer, optional): Max tickets to assign (default: 10, max: 100)

**Response:**
```json
{
  "success": true,
  "data": {
    "total_processed": 10,
    "assigned": 8,
    "failed": 2,
    "tickets": [
      {
        "ticket_id": "uuid",
        "ticket_number": "TKT-000123",
        "assigned_agent_id": "agent-uuid",
        "status": "assigned"
      },
      {
        "ticket_id": "uuid",
        "ticket_number": "TKT-000124",
        "status": "failed",
        "reason": "No available agent"
      }
    ]
  },
  "message": "Assigned 8 out of 10 tickets"
}
```

---

### 5. Rebalance Workload

**Endpoint:** `POST /api/v1/assignments/rebalance`

Redistribute tickets from overloaded agents to available agents.

**Request Body:**
```json
{
  "department_id": "uuid-here"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "reassigned": 5,
    "agents_balanced": 2,
    "errors": []
  },
  "message": "Rebalanced 5 tickets across 2 agents"
}
```

---

## Configuration

### Environment Variables

Add to your `.env` file:

```env
# Assignment Configuration
TICKET_ASSIGNMENT_STRATEGY=least_busy
TICKET_ASSIGNMENT_CAPACITY=20
TICKET_ASSIGNMENT_CONSIDER_DEPARTMENT=true
TICKET_ASSIGNMENT_CONSIDER_PRIORITY=true

# Notification Service (for assignment notifications)
NOTIFICATION_SERVICE_URL=http://localhost:8004
```

### Database Configuration

The system uses existing tables:
- `users` - Agent information
- `departments` - Department structure
- `tickets` - Ticket data with assignment fields
- `ticket_history` - Assignment audit trail

**Key Fields:**
- `tickets.assigned_agent_id` - Assigned agent UUID
- `tickets.assigned_department_id` - Assigned department UUID
- `tickets.status` - Ticket status
- `tickets.priority` - Ticket priority

---

## Usage Examples

### Example 1: Email-to-Ticket Auto-Assignment

When an email is converted to a ticket:

```php
// In Email Service
$response = Http::post('http://ticket-service:8002/api/v1/tickets', [
    'subject' => $email->subject,
    'description' => $email->body,
    'client_id' => $clientId,
    'source' => 'email',
    'priority' => 'medium',
    'auto_assign' => true, // Enable auto-assignment
    'assignment_strategy' => 'priority_based'
]);
```

### Example 2: Web Form Auto-Assignment

```javascript
// Frontend ticket creation
const response = await api.tickets.create({
  subject: formData.subject,
  description: formData.description,
  client_id: user.id,
  source: 'web_form',
  priority: formData.priority,
  category_id: formData.category_id,
  auto_assign: true, // Auto-assign to available agent
  assignment_strategy: 'least_busy'
});
```

### Example 3: Scheduled Auto-Assignment Job

Create a cron job to auto-assign unassigned tickets:

```bash
# Run every 5 minutes
*/5 * * * * curl -X POST http://localhost:8002/api/v1/assignments/auto-assign \
  -H "Content-Type: application/json" \
  -d '{"strategy":"least_busy","limit":50}'
```

### Example 4: Department-Specific Assignment

```json
POST /api/v1/tickets
{
  "subject": "Billing issue",
  "description": "I was charged twice",
  "client_id": "client-uuid",
  "source": "chat",
  "assigned_department_id": "billing-dept-uuid",
  "auto_assign": true
}
```

The system will automatically assign to an agent in the billing department.

### Example 5: High-Priority Ticket Assignment

```json
POST /api/v1/tickets
{
  "subject": "URGENT: System down",
  "description": "Production system is completely down",
  "client_id": "client-uuid",
  "source": "phone",
  "priority": "urgent",
  "auto_assign": true,
  "assignment_strategy": "priority_based"
}
```

With `priority_based` strategy, this will be assigned to a manager/admin or agent with fewer high-priority tickets.

---

## Agent Capacity Management

### Default Capacity: 20 Tickets

Each agent can handle up to 20 open tickets by default. When an agent reaches capacity:
- They won't receive new assignments
- System will assign to next available agent
- Rebalancing can redistribute their workload

### Checking Agent Capacity

```bash
GET /api/v1/assignments/agents/workload
```

Response shows `open_tickets` count per agent. Agents with `open_tickets >= 20` are at capacity.

### Manual Rebalancing

When agents are overloaded, manually trigger rebalancing:

```bash
POST /api/v1/assignments/rebalance
{
  "department_id": "dept-uuid"
}
```

This will:
1. Find agents with > 20 tickets
2. Select oldest, lowest-priority tickets
3. Reassign to least busy agents
4. Log all changes in ticket history

---

## Notifications

When a ticket is auto-assigned, the assigned agent receives:

### In-App Notification
```json
{
  "type": "ticket_assigned",
  "title": "New Ticket Assigned",
  "message": "Ticket #TKT-000123 has been automatically assigned to you: Cannot login to account",
  "data": {
    "ticket_id": "uuid",
    "ticket_number": "TKT-000123",
    "priority": "high",
    "subject": "Cannot login to account"
  }
}
```

### Email Notification
Subject: "New Ticket Assigned: #TKT-000123"

Body includes:
- Ticket number and subject
- Priority and status
- Link to view ticket
- Client information

---

## Monitoring & Analytics

### Track Assignment Performance

```sql
-- Tickets assigned per agent today
SELECT
  u.name,
  COUNT(th.id) as assignments_today
FROM ticket_history th
JOIN users u ON th.new_value = u.id::text
WHERE th.action IN ('assigned', 'auto_assigned')
  AND DATE(th.created_at) = CURRENT_DATE
GROUP BY u.name
ORDER BY assignments_today DESC;

-- Average time to assignment
SELECT
  AVG(EXTRACT(EPOCH FROM (th.created_at - t.created_at))) as avg_seconds_to_assign
FROM tickets t
JOIN ticket_history th ON t.id = th.ticket_id
WHERE th.action IN ('assigned', 'auto_assigned');

-- Assignment success rate
SELECT
  COUNT(CASE WHEN assigned_agent_id IS NOT NULL THEN 1 END) * 100.0 / COUNT(*) as assignment_rate
FROM tickets
WHERE status != 'closed';
```

---

## Troubleshooting

### No Agents Available

**Issue:** Tickets not being assigned

**Solutions:**
1. Check agent capacity: `GET /api/v1/assignments/agents/workload`
2. Verify agents are active: `SELECT * FROM users WHERE role IN ('agent','manager') AND is_active = true`
3. Check department assignment: Ensure department has active agents
4. Try rebalancing: `POST /api/v1/assignments/rebalance`

### Uneven Distribution

**Issue:** Some agents have too many tickets

**Solutions:**
1. Use `round_robin` strategy instead of `least_busy`
2. Run workload rebalancing regularly
3. Adjust agent capacity limits
4. Review department assignments

### Assignment Not Working

**Issue:** Auto-assignment disabled

**Check:**
1. Verify `auto_assign: true` in request
2. Check if agent was manually specified
3. Review logs: `tail -f storage/logs/lumen.log | grep "auto-assign"`
4. Verify assignment service is initialized

---

## Best Practices

### 1. Enable Auto-Assignment by Default
```javascript
// Set as default in frontend
const defaultTicketData = {
  auto_assign: true,
  assignment_strategy: 'least_busy'
};
```

### 2. Use Priority-Based for Critical Tickets
```javascript
if (ticket.priority === 'urgent' || ticket.priority === 'high') {
  ticket.assignment_strategy = 'priority_based';
}
```

### 3. Monitor Agent Workload Daily
```bash
# Daily cron job
0 9 * * * curl http://localhost:8002/api/v1/assignments/agents/workload | mail -s "Daily Agent Workload" admin@company.com
```

### 4. Rebalance Weekly
```bash
# Weekly rebalancing
0 0 * * 0 curl -X POST http://localhost:8002/api/v1/assignments/rebalance
```

### 5. Track Assignment Metrics
- Assignment success rate
- Time to assignment
- Agent utilization rates
- Tickets per agent
- Resolution times by agent

---

## Advanced Configuration

### Custom Capacity per Agent

In the future, you can extend this to support custom capacity per agent by adding a `max_tickets` field to the `users` table:

```sql
ALTER TABLE users ADD COLUMN max_ticket_capacity INTEGER DEFAULT 20;
```

Then update the service to use this value instead of the global default.

### Skill-Based Assignment

To implement skill-based routing:

1. Create a `user_skills` table:
```sql
CREATE TABLE user_skills (
  id UUID PRIMARY KEY,
  user_id UUID REFERENCES users(id),
  category_id UUID REFERENCES categories(id),
  skill_level INTEGER, -- 1-5
  avg_resolution_time INTEGER -- in minutes
);
```

2. Update `assignBySkill()` method in `TicketAssignmentService.php`

3. Match ticket categories to agent skills

---

## API Reference Summary

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v1/tickets` | POST | Create ticket (with auto-assignment) |
| `/api/v1/assignments/agents/workload` | GET | Get agent workload statistics |
| `/api/v1/assignments/agents/available` | GET | Get available agents |
| `/api/v1/assignments/auto-assign` | POST | Bulk auto-assign unassigned tickets |
| `/api/v1/assignments/rebalance` | POST | Rebalance agent workload |

---

## Support

For issues or questions:
- Check logs: `tail -f storage/logs/lumen.log`
- Review ticket history: `GET /api/v1/tickets/{id}/history`
- Contact development team

---

**Last Updated:** 2025-09-30
**Version:** 1.0.0