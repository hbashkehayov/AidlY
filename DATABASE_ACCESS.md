# AidlY Database Access Guide

## Quick Start

### 1. Start the Database
```bash
# Option 1: Use the startup script
./scripts/start-db.sh

# Option 2: Use the interactive helper
./scripts/db-helper.sh
# Then select option 1

# Option 3: Manual with docker-compose
docker-compose up -d postgres redis rabbitmq minio
```

### 2. Connect with DBeaver

#### Connection Settings:
| Setting | Value |
|---------|-------|
| **Host** | localhost |
| **Port** | 5432 |
| **Database** | aidly |
| **Username** | aidly_user |
| **Password** | aidly_secret_2024 |
| **Driver** | PostgreSQL |

#### Steps in DBeaver:
1. Click "New Database Connection" (or press Ctrl+Shift+N)
2. Select "PostgreSQL" and click Next
3. Enter the connection settings above
4. Click "Test Connection" to verify
5. Click "Finish" to save

## Database Schema Overview

### Users & Authentication
- **users** - User accounts with JWT authentication support
- **departments** - Organizational structure
- **permissions** - Granular permission definitions
- **role_permissions** - Maps roles to permissions
- **sessions** - Active user sessions
- **password_resets** - Password reset tokens

### Ticketing System
- **tickets** - Support tickets with full workflow
- **ticket_comments** - Replies and internal notes
- **ticket_history** - Audit trail for tickets
- **ticket_relationships** - Links between tickets
- **attachments** - File attachments

### Clients & Contacts
- **clients** - Customer records
- **client_notes** - Notes about clients
- **client_merges** - Client merge history

### Configuration
- **categories** - Ticket categorization
- **business_hours** - Working hours configuration
- **sla_policies** - SLA definitions

## Test Data

The database comes with pre-seeded test data:

### Test Users (password: `password123`)
| Email | Role | Description |
|-------|------|-------------|
| admin@aidly.com | admin | Full system access |
| supervisor@aidly.com | supervisor | Department supervisor |
| agent1@aidly.com | agent | Support agent (Customer Support) |
| agent2@aidly.com | agent | Support agent (Technical Support) |
| agent3@aidly.com | agent | Support agent (Sales Support) |
| customer@example.com | customer | Example customer |

### Sample Data Includes:
- 5 Test Tickets with various statuses
- 5 Client records
- 4 Departments
- 5 Categories
- Comments and ticket history
- SLA policies

## Useful Database Commands

### Connect to PostgreSQL CLI
```bash
# Using helper script
./scripts/db-helper.sh
# Select option 5

# Direct connection
docker-compose exec postgres psql -U aidly_user -d aidly
```

### Common SQL Queries

#### View all tables:
```sql
\dt
```

#### Describe a table structure:
```sql
\d users
\d tickets
```

#### Count records in tables:
```sql
SELECT
    'users' as table_name, COUNT(*) as count FROM users
UNION ALL
SELECT 'tickets', COUNT(*) FROM tickets
UNION ALL
SELECT 'clients', COUNT(*) FROM clients;
```

#### View recent tickets:
```sql
SELECT
    ticket_number,
    subject,
    status,
    priority,
    created_at
FROM tickets
ORDER BY created_at DESC
LIMIT 10;
```

#### Check user roles:
```sql
SELECT
    name,
    email,
    role,
    is_active,
    last_login_at
FROM users
ORDER BY role, name;
```

## Database Management

### Backup Database
```bash
# Using helper script
./scripts/db-helper.sh
# Select option 8

# Manual backup
docker-compose exec postgres pg_dump -U aidly_user -d aidly > backup.sql
```

### Restore Database
```bash
# Restore from backup
docker-compose exec -T postgres psql -U aidly_user -d aidly < backup.sql
```

### Reset Database (WARNING: Deletes all data)
```bash
# Using helper script
./scripts/db-helper.sh
# Select option 7

# Manual reset
docker-compose down -v
docker-compose up -d postgres
```

### Re-seed Test Data
```bash
# Using helper script
./scripts/db-helper.sh
# Select option 6

# Manual seeding
docker-compose exec -T postgres psql -U aidly_user -d aidly < docker/init-scripts/02-seed-test-data.sql
```

## Other Services

### Redis (Cache)
- **Host:** localhost
- **Port:** 6379
- **Password:** redis_secret_2024

### RabbitMQ (Message Queue)
- **URL:** http://localhost:15672
- **Username:** aidly_admin
- **Password:** rabbitmq_secret_2024

### MinIO (Object Storage)
- **Console URL:** http://localhost:9001
- **Username:** aidly_minio_admin
- **Password:** minio_secret_2024

## Troubleshooting

### Cannot connect to database
1. Ensure Docker is running
2. Check if PostgreSQL container is up: `docker-compose ps`
3. Verify port 5432 is not in use: `netstat -an | grep 5432`

### Permission denied errors
```bash
# Grant execute permission to scripts
chmod +x scripts/*.sh
```

### Database is empty
```bash
# The schema is automatically created on first startup
# To add test data:
./scripts/db-helper.sh
# Select option 6 (Seed test data)
```

### Port already in use
```bash
# Stop any existing PostgreSQL service
sudo service postgresql stop

# Or change the port in docker-compose.yml
```

## Visual Database Tools

### Recommended Tools:
1. **DBeaver** (Free, Cross-platform) - Best overall
2. **pgAdmin** (Free, Web-based)
3. **TablePlus** (Paid, Modern UI)
4. **DataGrip** (Paid, JetBrains)
5. **Postico** (Mac only)

### DBeaver Features for AidlY:
- ER Diagram visualization
- SQL auto-completion with AidlY tables
- Data export/import
- Query history
- Multiple simultaneous connections

## Security Notes

⚠️ **Important**: The credentials in this guide are for development only.

For production:
- Use strong, unique passwords
- Store credentials in environment variables
- Use SSL/TLS connections
- Implement IP whitelisting
- Regular backups
- Audit logging

## Next Steps

1. Connect to the database with DBeaver
2. Explore the schema and test data
3. Test the auth service endpoints with the seeded users
4. Start building the ticket service (Sprint 2.1)

## Quick Commands Reference

```bash
# Start services
./scripts/start-db.sh

# Interactive management
./scripts/db-helper.sh

# Direct PostgreSQL access
docker-compose exec postgres psql -U aidly_user -d aidly

# View logs
docker-compose logs -f postgres

# Stop services
docker-compose down

# Reset everything
docker-compose down -v
```