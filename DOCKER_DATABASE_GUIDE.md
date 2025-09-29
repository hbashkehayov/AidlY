# AidlY Docker Database Access Guide

## Quick Start Commands

### Start the Database Container
```bash
cd /root/AidlY
docker compose up -d postgres redis rabbitmq minio
```

### Stop the Database Container
```bash
docker compose down
```

### Reset Database (Delete all data)
```bash
docker compose down -v
docker compose up -d postgres redis rabbitmq minio
```

## Database Connection Details

### For DBeaver (Local VPS Access)
- **Host:** `localhost`
- **Port:** `5432`
- **Database:** `aidly`
- **Username:** `aidly_user`
- **Password:** `aidly_secret_2024`

### For Remote Desktop Access
- **Host:** `89.25.76.90` (VPS Public IP)
- **Port:** `5432`
- **Database:** `aidly`
- **Username:** `aidly_user`
- **Password:** `aidly_secret_2024`

## Container Status Commands

### Check if containers are running
```bash
docker compose ps
```

### View container logs
```bash
# PostgreSQL logs
docker compose logs postgres

# All services logs
docker compose logs
```

### Test database connection
```bash
# From within VPS
docker compose exec postgres psql -U aidly_user -d aidly -c "SELECT COUNT(*) FROM users;"

# Test port connectivity
timeout 5 bash -c 'cat < /dev/null > /dev/tcp/localhost/5432' && echo "Port 5432 is accessible"
```

## Database Management

### Access PostgreSQL CLI
```bash
docker compose exec postgres psql -U aidly_user -d aidly
```

### Common SQL Commands
```sql
-- List all tables
\dt

-- Describe table structure
\d users
\d tickets

-- Count records
SELECT 'users' as table_name, COUNT(*) FROM users
UNION ALL
SELECT 'tickets', COUNT(*) FROM tickets;

-- Exit PostgreSQL CLI
\q
```

### Export Database
```bash
docker compose exec postgres pg_dump -U aidly_user -d aidly > backup.sql
```

### Import Database
```bash
docker compose exec -T postgres psql -U aidly_user -d aidly < backup.sql
```

## Service URLs

When containers are running, these services are available:

- **PostgreSQL:** `localhost:5432` or `89.25.76.90:5432`
- **Redis:** `localhost:6379` or `89.25.76.90:6379`
- **RabbitMQ Management:** `http://localhost:15672` or `http://89.25.76.90:15672`
  - Username: `aidly_admin`
  - Password: `rabbitmq_secret_2024`
- **MinIO Console:** `http://localhost:9001` or `http://89.25.76.90:9001`
  - Username: `aidly_minio_admin`
  - Password: `minio_secret_2024`

## Troubleshooting

### Port already in use error
```bash
# Stop conflicting services
systemctl stop postgresql redis-server

# Or kill specific processes
pkill postgres
pkill redis-server

# Then restart containers
docker compose up -d postgres redis rabbitmq minio
```

### Container won't start
```bash
# Check container status
docker compose ps

# Check logs for errors
docker compose logs postgres

# Restart specific service
docker compose restart postgres
```

### Reset everything
```bash
# Stop and remove all containers and volumes
docker compose down -v

# Remove any stopped containers
docker system prune -f

# Start fresh
docker compose up -d postgres redis rabbitmq minio
```

### Database is empty after restart
The database persists data in Docker volumes. If you used `-v` flag, you deleted the data.
The database will automatically recreate tables and seed test data on first startup.

## Test Data

The database comes pre-loaded with:
- **6 test users** (password: `password123`)
- **5 test tickets**
- **5 client records**
- **4 departments**
- **Sample SLA policies**

### Test User Accounts
| Email | Role | Description |
|-------|------|-------------|
| admin@aidly.com | admin | Full system access |
| supervisor@aidly.com | supervisor | Department supervisor |
| agent1@aidly.com | agent | Support agent |
| customer@example.com | customer | Example customer |

## Remote Access from Desktop

### Option 1: Direct Connection
Use VPS public IP `89.25.76.90:5432` directly in DBeaver.

### Option 2: SSH Tunnel (Recommended)
```bash
# On your desktop, create SSH tunnel
ssh -L 5432:localhost:5432 root@89.25.76.90

# Then connect DBeaver to localhost:5432
```

### Option 3: DBeaver SSH Tunnel
1. In DBeaver, create new PostgreSQL connection
2. Enable "Use SSH tunnel" in SSH tab
3. SSH Host: `89.25.76.90`, User: `root`
4. Database Host: `localhost:5432`

## Notes

- Replace `89.25.76.90` with your actual VPS IP if it changes
- Default passwords are for development only - change for production
- Containers automatically restart unless stopped with `docker compose down`
- Data persists between container restarts (unless volumes are deleted)