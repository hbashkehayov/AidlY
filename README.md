# AidlY - Modern Customer Support Platform

A comprehensive, microservices-based customer support platform built with scalability and AI-readiness in mind.

## 🚀 Quick Start

### Prerequisites
- Docker & Docker Compose
- Node.js 18+ (for local development)
- Git

### Initial Setup

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/aidly.git
   cd aidly
   ```

2. **Set up environment variables**
   ```bash
   cp .env.example .env
   # Edit .env with your configuration
   ```

3. **Start infrastructure services**
   ```bash
   docker-compose up -d postgres redis rabbitmq minio
   ```

4. **Verify services are running**
   ```bash
   docker-compose ps
   ```

5. **Access service UIs**
   - MinIO Console: http://localhost:9001
   - RabbitMQ Management: http://localhost:15672
   - Kong Admin: http://localhost:8001

## 🏗️ Architecture

### Microservices
- **Auth Service**: JWT-based authentication with RBAC
- **Ticket Service**: Core ticketing functionality
- **Client Service**: Customer management
- **Email Service**: IMAP/SMTP integration
- **Notification Service**: Multi-channel notifications
- **AI Integration Service**: Webhook-ready for future AI

### Infrastructure
- **PostgreSQL**: Primary database
- **Redis**: Caching and sessions
- **RabbitMQ**: Message queue for async processing
- **MinIO**: S3-compatible object storage
- **Kong**: API Gateway

## 📦 Project Structure

```
aidly/
├── docker/                 # Docker configuration files
│   ├── init-scripts/      # Database initialization
│   ├── kong/              # Kong API Gateway config
│   └── nginx/             # Nginx config (alternative)
├── services/              # Microservices (to be created)
│   ├── auth-service/      # Authentication service
│   ├── ticket-service/    # Ticket management
│   ├── client-service/    # Client management
│   ├── email-service/     # Email integration
│   └── notification-service/
├── frontend/              # Next.js frontend (to be created)
├── docker-compose.yml     # Docker services configuration
├── .env.example          # Environment variables template
└── README.md             # This file
```

## 🔐 Default Credentials

### PostgreSQL
- Database: `aidly`
- Username: `aidly_user`
- Password: `aidly_secret_2024`

### Redis
- Password: `redis_secret_2024`

### RabbitMQ
- Username: `aidly_admin`
- Password: `rabbitmq_secret_2024`
- Management UI: http://localhost:15672

### MinIO
- Username: `aidly_minio_admin`
- Password: `minio_secret_2024`
- Console: http://localhost:9001

## 🛠️ Development

### Starting Services
```bash
# Start all services
docker-compose up -d

# Start specific services
docker-compose up -d postgres redis

# View logs
docker-compose logs -f [service-name]

# Stop services
docker-compose down
```

### Database Migrations
The database schema is automatically created when PostgreSQL starts up using the initialization script in `docker/init-scripts/`.

### Stopping Services
```bash
# Stop services but keep data
docker-compose stop

# Stop and remove containers (keeps volumes)
docker-compose down

# Stop and remove everything including volumes
docker-compose down -v
```

## 📊 Sprint 1.1 Status - Infrastructure Setup ✅

### Completed Tasks:
- ✅ Initialize Git repository with .gitignore
- ✅ Create Docker Compose configuration
- ✅ Set up PostgreSQL with initial database schema
- ✅ Configure Redis for caching
- ✅ Set up MinIO for file storage
- ✅ Configure development environment variables

### Infrastructure Ready:
- PostgreSQL 15 with UUID support and custom types
- Redis 7 for caching and sessions
- RabbitMQ 3.12 for message queuing
- MinIO for S3-compatible object storage
- Kong API Gateway with PostgreSQL backend
- Database initialization scripts with core tables

## 🎯 Next Steps

### Sprint 1.2: Authentication Foundation (Days 4-7)
- [ ] Build custom JWT authentication service in Lumen
- [ ] Implement user registration and login
- [ ] Create password reset functionality
- [ ] Set up role-based access control (RBAC)
- [ ] Implement refresh token mechanism
- [ ] Add session management

## 📝 License

MIT License - See LICENSE file for details

## 🤝 Contributing

Please read CONTRIBUTING.md for details on our code of conduct and the process for submitting pull requests.

## 📧 Contact

For questions or support, please contact the development team.

---

**Note**: This is a development setup. For production deployment, please refer to the deployment guide and ensure all passwords are changed from their default values.