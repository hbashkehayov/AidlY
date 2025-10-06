# Email Processing with Single Shared Mailbox Architecture

## Your Actual Configuration

### Architecture Overview

```
┌──────────────────────────────────────────────────────┐
│   CUSTOMER EMAILS                                    │
│   customer1@gmail.com                                │
│   customer2@yahoo.com          ─────────┐           │
│   customer3@outlook.com                 │           │
└─────────────────────────────────────────┼───────────┘
                                          │
                                          ↓
┌──────────────────────────────────────────────────────┐
│   SINGLE SHARED MAILBOX                              │
│   support@company.com (ONE EMAIL ACCOUNT)            │
│                                                      │
│   All customer emails arrive here                    │
└────────────────┬─────────────────────────────────────┘
                 │
                 ↓ IMAP Fetch (Every 5 minutes)
                 │
┌────────────────▼─────────────────────────────────────┐
│   EMAIL SERVICE                                      │
│   - Fetch from ONE account only                      │
│   - Convert to tickets                               │
│   - Auto-assign OR manual assign                     │
└────────────────┬─────────────────────────────────────┘
                 │
                 ↓ Distribution
                 │
┌────────────────▼─────────────────────────────────────┐
│   TICKETS DISTRIBUTED TO AGENTS                      │
│                                                      │
│   Agent 1 (John)    Agent 2 (Sarah)   Agent 3 (Mike)│
│   10 tickets        8 tickets          12 tickets    │
│                                                      │
│   Each agent replies from their own email OR         │
│   from shared mailbox                                │
└──────────────────────────────────────────────────────┘
```

---

## How It Actually Works (Single Mailbox)

### Phase 1: Email Fetching - SIMPLIFIED

**Since you have ONLY 1 email account:**

```php
// File: /services/email-service/app/Services/ImapService.php

// This runs for only 1 account (support@company.com)
public function fetchAllEmails(): array
{
    $accounts = EmailAccount::active()->get();  // Returns 1 account

    foreach ($accounts as $account) {  // Only runs ONCE
        $result = $this->fetchEmailsFromAccount($account);
    }
}
```

**What happens:**
1. Connect to **support@company.com** IMAP
2. Fetch ALL unseen emails (from all customers)
3. For each email:
   - Extract headers, body, attachments
   - Check for duplicates
   - Save to `email_queue` table (status=pending)
4. Disconnect

**No iteration through multiple accounts** - just ONE connection!

---

## Performance Analysis: 50 Emails (Single Mailbox)

### Revised Time Estimates

#### Phase 1: Fetching (Much Faster!)

| Operation | Time per Email | 50 Emails Total |
|-----------|---------------|-----------------|
| IMAP connection | 500ms | **500ms (only 1 account!)** |
| Fetch email headers | 100ms | 5 seconds |
| Download email body | 200ms | 10 seconds |
| Download attachments | 300ms | 15 seconds |
| Extract data | 50ms | 2.5 seconds |
| Duplicate check | 20ms | 1 second |
| Save to email_queue | 30ms | 1.5 seconds |
| Mark as Seen | 50ms | 2.5 seconds |
| **TOTAL PHASE 1** | | **~38 seconds** |

**Note:** No multiple account connection overhead!

#### Phase 2: Converting to Tickets (Same)

| Operation | Time per Email | 50 Emails Total |
|-----------|---------------|-----------------|
| Fetch pending from queue | N/A | 200ms |
| System email check | 10ms | 0.5 seconds |
| Find existing ticket | 300ms | 15 seconds |
| Find/create client | 400ms | 20 seconds |
| Create ticket/comment | 500ms | 25 seconds |
| **Auto-assign to agent** | 100ms | 5 seconds |
| Update email_queue | 30ms | 1.5 seconds |
| **TOTAL PHASE 2** | | **~67 seconds** |

### **Grand Total: ~105 seconds (1 min 45 sec)**

---

## Your Workflow in Detail

### Complete Process for 50 Customer Emails

```
┌─────────────────────────────────────────────────────┐
│ CUSTOMERS SEND EMAILS                               │
│                                                     │
│ customer1@gmail.com   → support@company.com         │
│ customer2@yahoo.com   → support@company.com         │
│ customer3@hotmail.com → support@company.com         │
│ ... (50 emails total in shared inbox)              │
└────────────┬────────────────────────────────────────┘
             │
┌────────────▼────────────────────────────────────────┐
│ CRON JOB: Every 5 minutes                          │
│ POST /api/v1/emails/fetch                          │
└────────────┬────────────────────────────────────────┘
             │
┌────────────▼────────────────────────────────────────┐
│ PHASE 1: Fetch from support@company.com            │
│                                                     │
│ 1. Connect to IMAP (support@company.com)           │
│    Time: 500ms                                     │
│                                                     │
│ 2. Query: Get unseen emails                        │
│    Result: 50 unseen emails                        │
│    Time: 2 seconds                                 │
│                                                     │
│ 3. For each of 50 emails (sequential):             │
│    ├─ Email 1 (customer1@gmail.com)                │
│    │  ├─ Download body + attachments (700ms)       │
│    │  ├─ Extract Message-ID, In-Reply-To (50ms)    │
│    │  ├─ Check duplicate in DB (20ms)              │
│    │  └─ INSERT INTO email_queue (30ms)            │
│    │                                                │
│    ├─ Email 2 (customer2@yahoo.com)                │
│    │  └─ Same process... (800ms)                   │
│    │                                                │
│    └─ ... repeat for all 50 emails                 │
│                                                     │
│ 4. Mark all as "Seen" (2.5 seconds)                │
│ 5. Disconnect from IMAP                            │
│                                                     │
│ Result: 50 rows in email_queue (status=pending)    │
│ Total Time: ~38 seconds                            │
└────────────┬────────────────────────────────────────┘
             │
┌────────────▼────────────────────────────────────────┐
│ CRON JOB: Every 1 minute                           │
│ POST /api/v1/emails/process                        │
└────────────┬────────────────────────────────────────┘
             │
┌────────────▼────────────────────────────────────────┐
│ PHASE 2: Convert Queue to Tickets                  │
│                                                     │
│ 1. SELECT * FROM email_queue                       │
│    WHERE status='pending'                          │
│    ORDER BY received_at                            │
│    Result: 50 emails                               │
│                                                     │
│ 2. For each email (sequential):                    │
│                                                     │
│    Email 1 (from customer1@gmail.com):             │
│    ├─ Skip system email? No                        │
│    ├─ Check In-Reply-To header                     │
│    ├─ API: Find existing ticket (300ms)            │
│    │  GET /tickets/by-message-id                   │
│    │  Result: No match (new conversation)          │
│    │                                                │
│    ├─ API: Find client by email (400ms)            │
│    │  GET /clients?email=customer1@gmail.com       │
│    │  Result: Found client (or create new)         │
│    │                                                │
│    ├─ Check if client is blocked? No               │
│    │                                                │
│    ├─ API: Create ticket (500ms)                   │
│    │  POST /tickets                                │
│    │  {                                            │
│    │    client_id: "uuid",                         │
│    │    subject: "Need help with login",           │
│    │    description: "Email body...",              │
│    │    source: "email",                           │
│    │    priority: "medium"                         │
│    │  }                                            │
│    │  Result: Ticket TKT-001234 created            │
│    │                                                │
│    ├─ AUTO-ASSIGN to agent (100ms)                 │
│    │  - Calculate agent workload                   │
│    │  - Assign to least loaded agent               │
│    │  - POST /tickets/{id}/assign                  │
│    │  - Result: Assigned to Agent 1 (John)         │
│    │                                                │
│    ├─ Notify agent (in-app + email) (200ms)        │
│    │                                                │
│    └─ UPDATE email_queue                           │
│       SET status='processed',                      │
│           ticket_id='ticket-uuid'                  │
│       Time: 30ms                                   │
│                                                     │
│    Email 2 (from customer2@yahoo.com):             │
│    ├─ Check In-Reply-To: TKT-001100                │
│    ├─ API: Find ticket by number (300ms)           │
│    │  Result: Found TKT-001100                     │
│    │                                                │
│    ├─ API: Add comment to ticket (500ms)           │
│    │  POST /tickets/TKT-001100/comments            │
│    │  Result: Comment added                        │
│    │                                                │
│    ├─ Notify assigned agent (200ms)                │
│    │                                                │
│    └─ UPDATE email_queue (30ms)                    │
│                                                     │
│    ... repeat for all 50 emails ...                │
│                                                     │
│ Total Time: ~67 seconds                            │
└─────────────────────────────────────────────────────┘

GRAND TOTAL: 38 + 67 = 105 seconds (1 min 45 sec)
```

---

## Database Flow

### email_queue Table After Fetching

```sql
-- After Phase 1 (Fetch), you have 50 rows:

id          | email_account_id | from_address           | subject              | status
------------|------------------|------------------------|----------------------|----------
uuid-1      | shared-inbox-id  | customer1@gmail.com    | Need help with login | pending
uuid-2      | shared-inbox-id  | customer2@yahoo.com    | Re: TKT-001100       | pending
uuid-3      | shared-inbox-id  | customer3@hotmail.com  | Payment issue        | pending
... (47 more rows)
```

### After Processing (Phase 2)

```sql
-- After Phase 2 (Convert), status updated:

id     | from_address        | subject         | status    | ticket_id
-------|---------------------|-----------------|-----------|-------------
uuid-1 | customer1@gmail.com | Need help...    | processed | ticket-uuid-1
uuid-2 | customer2@yahoo.com | Re: TKT-001100  | processed | ticket-uuid-2
uuid-3 | customer3@hotmail   | Payment issue   | processed | ticket-uuid-3
... (all 50 updated to 'processed')
```

---

## Agent Email Configuration

### Two Scenarios for Replies

#### Scenario A: All Agents Reply from Shared Mailbox

```
Ticket assigned to Agent 1 (John)
  ↓
John replies to customer
  ↓
Email sent FROM: support@company.com
  ↓
Customer receives: support@company.com
  ↓
Customer replies TO: support@company.com
  ↓
Email arrives in shared inbox
  ↓
Threading works! (In-Reply-To matches)
```

**Configuration:**
- All agents use same SMTP account (support@company.com)
- Emails sent from shared mailbox
- Customer always sees support@company.com

#### Scenario B: Agents Reply from Personal Emails

```
Ticket assigned to Agent 1 (John - john@company.com)
  ↓
John replies to customer
  ↓
Email sent FROM: john@company.com
  ↓
Customer receives: john@company.com
  ↓
Customer replies TO: john@company.com
  ↓
Email arrives in JOHN'S inbox (NOT shared inbox!)
  ↓
PROBLEM: Email not fetched! Threading breaks!
```

**Configuration:**
- Each agent has IMAP/SMTP configured
- Need multiple email accounts in system
- More complex but personalized

### Your Current Setup (Likely Scenario A)

Based on your description "1 shared mailbox", you're using:

```
email_accounts table:
┌──────────────────────────────────────────────────┐
│ id   | name               | email_address        │
├──────┼────────────────────┼─────────────────────┤
│ uuid | Support Mailbox    | support@company.com  │
│      | (ONLY 1 ACCOUNT)   |                      │
└──────────────────────────────────────────────────┘

Configuration:
- auto_create_tickets: true
- All agents send from this account
- All customer replies come to this account
```

---

## Performance Implications (Single Mailbox)

### Advantages ✅

1. **Faster Fetching**:
   - Only 1 IMAP connection
   - No account iteration overhead
   - Simpler error handling

2. **Better Threading**:
   - All emails in one place
   - No cross-account confusion
   - In-Reply-To always works

3. **Simpler Configuration**:
   - One set of credentials
   - Easier monitoring
   - Less complexity

### Disadvantages ⚠️

1. **Single Point of Failure**:
   - If shared mailbox down, everything stops
   - No redundancy

2. **Scalability Limit**:
   - IMAP servers have rate limits
   - Large email volumes may be throttled
   - Gmail: ~10 connections/day limit

3. **No Agent-Specific Emails**:
   - Can't track which agent sent what
   - Less personalization
   - Generic "support@" email

---

## Revised Performance Table (Single Mailbox)

| Emails | Fetch Time | Convert Time | Total Time | Notes |
|--------|-----------|--------------|------------|-------|
| 10 | **~8 sec** | ~13 sec | **~21 sec** | Very fast |
| 25 | **~15 sec** | ~33 sec | **~48 sec** | Under 1 min |
| 50 | **~38 sec** | ~67 sec | **~105 sec** | ~1.75 min |
| 100 | **~70 sec** | ~134 sec | **~204 sec** | ~3.4 min |
| 200 | **~140 sec** | ~270 sec | **~410 sec** | ~6.8 min |

**Throughput: ~30-35 emails per minute**

---

## Auto-Assignment Logic

### How Tickets Get Distributed to Agents

After ticket is created, auto-assignment happens:

```php
// File: /services/ticket-service/app/Http/Controllers/TicketController.php

// When ticket created from email:
1. Get all active agents
2. Calculate current workload per agent
3. Assign to least loaded agent

// Example:
SELECT id, name,
       (SELECT COUNT(*) FROM tickets
        WHERE assigned_agent_id = users.id
        AND status IN ('new', 'open', 'pending')
       ) as workload
FROM users
WHERE role = 'agent' AND is_active = true
ORDER BY workload ASC
LIMIT 1;

// Result: Agent with fewest open tickets
```

**Distribution Example:**

```
Before batch of 50 emails:
- Agent 1 (John):  12 tickets
- Agent 2 (Sarah): 10 tickets
- Agent 3 (Mike):  15 tickets

After 50 emails processed:
- Agent 1 (John):  29 tickets (+17)
- Agent 2 (Sarah): 27 tickets (+17)
- Agent 3 (Mike):  31 tickets (+16)

System balances load automatically!
```

---

## Cron Job Configuration (Recommended)

### For Single Shared Mailbox

```bash
# Fetch emails every 5 minutes (only 1 account)
*/5 * * * * curl -X POST http://localhost:8005/api/v1/emails/fetch

# Process queue every 1 minute (faster processing)
* * * * * curl -X POST http://localhost:8005/api/v1/emails/process

# Alternative: Process immediately after fetch
*/5 * * * * curl -X POST http://localhost:8005/api/v1/emails/fetch && \
            curl -X POST http://localhost:8005/api/v1/emails/process
```

### Monitoring

```sql
-- Check email queue status
SELECT
    status,
    COUNT(*) as count,
    MAX(received_at) as latest_email
FROM email_queue
GROUP BY status;

-- Result:
-- status     | count | latest_email
-- pending    | 5     | 2025-10-06 10:30:00
-- processed  | 1245  | 2025-10-06 10:32:00
-- failed     | 2     | 2025-10-06 09:15:00
```

---

## Real-World Example: 50 Emails

### Scenario

Your shared mailbox receives **50 customer emails** between 9:00 AM and 9:05 AM:

```
9:00 AM - Customer1: "Can't login" → support@company.com
9:01 AM - Customer2: "Re: TKT-001050" → support@company.com
9:01 AM - Customer3: "Billing question" → support@company.com
... (47 more emails)
9:05 AM - Customer50: "Feature request" → support@company.com
```

### Timeline

```
9:05 AM - Cron triggers email fetch
9:05:00 - Connect to support@company.com IMAP
9:05:01 - Found 50 unseen emails
9:05:01 - Start downloading email 1/50
9:05:02 - Email 1 saved to queue (pending)
9:05:03 - Email 2 saved to queue (pending)
... (processing continues)
9:05:38 - Email 50 saved to queue (pending)
9:05:38 - Disconnect from IMAP
9:05:39 - Fetch complete!

9:06 AM - Cron triggers queue processing
9:06:00 - Found 50 pending emails in queue
9:06:00 - Processing email 1: Create ticket → Assign to Agent 2
9:06:02 - Processing email 2: Add comment to TKT-001050
9:06:03 - Processing email 3: Create ticket → Assign to Agent 1
... (processing continues)
9:07:07 - Processing email 50: Create ticket → Assign to Agent 3
9:07:07 - Processing complete!

TOTAL TIME: 2 minutes 7 seconds
```

### Result

- 50 emails processed
- ~35 new tickets created
- ~15 comments added to existing tickets
- Distributed across 3 agents automatically
- All customers notified via email

---

## Optimization Recommendations (Single Mailbox)

### 1. Immediate Process After Fetch

Instead of waiting for next cron:

```bash
# Combined command
*/5 * * * * curl -X POST http://localhost:8005/api/v1/emails/fetch && \
            sleep 2 && \
            curl -X POST http://localhost:8005/api/v1/emails/process
```

**Benefit**: Tickets created within 2 minutes instead of 6 minutes

### 2. Increase Fetch Frequency

For urgent support:

```bash
# Fetch every 2 minutes
*/2 * * * * curl -X POST http://localhost:8005/api/v1/emails/fetch && \
            curl -X POST http://localhost:8005/api/v1/emails/process
```

**Benefit**: Near real-time ticket creation

### 3. Add Email Webhook (Advanced)

Use Gmail push notifications instead of polling:

```
Gmail Webhook → Your Server → Instant Processing
```

**Benefit**: Instant ticket creation (0 delay)

### 4. Optimize API Calls

Cache client lookups:

```php
// Current: API call every time
$client = Http::get('/api/v1/clients?email=customer@example.com');

// Optimized: Cache for 5 minutes
$client = Cache::remember("client:{$email}", 300, function() {
    return Http::get('/api/v1/clients?email=' . $email);
});
```

**Benefit**: 20-30% faster processing

---

## Summary

### Your Architecture

| Aspect | Configuration |
|--------|--------------|
| **Email Accounts** | 1 (shared mailbox) |
| **Receiving Emails** | All to support@company.com |
| **Ticket Distribution** | Auto-assigned to agents |
| **Agent Replies** | From shared mailbox (recommended) |
| **Threading** | Works perfectly (single source) |

### Performance (50 Emails)

- **Fetch**: ~38 seconds (single connection)
- **Convert**: ~67 seconds (API calls)
- **Total**: ~105 seconds (~1.75 minutes)
- **Throughput**: ~30 emails/minute

### Key Differences from Multi-Account

| Aspect | Single Mailbox | Multiple Accounts |
|--------|---------------|-------------------|
| IMAP Connections | 1 | N (one per account) |
| Fetch Time (50 emails) | ~38 sec | ~50 sec |
| Threading Complexity | Simple | Complex |
| Configuration | Easy | Moderate |
| Failure Impact | High | Low |

### Your System Works Best For:

✅ Small to medium teams (5-20 agents)
✅ Centralized support operations
✅ Consistent email branding
✅ Simple setup and maintenance
✅ <200 emails/hour volume

---

**Document Version**: 1.0 (Single Mailbox Edition)
**Last Updated**: October 2025
