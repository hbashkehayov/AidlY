# Kong API Gateway Setup for AidlY

This directory contains the configuration and setup scripts for Kong API Gateway in the AidlY platform.

## 🦍 Overview

Kong acts as the central API Gateway for all microservices in the AidlY platform, providing:
- **Service Routing**: Route requests to appropriate microservices
- **Authentication**: JWT token validation and API key management
- **Rate Limiting**: Prevent API abuse and ensure fair usage
- **CORS**: Cross-Origin Resource Sharing configuration
- **Monitoring**: Prometheus metrics collection
- **Security**: Request/response transformation and security headers

## 📁 Files Structure

```
kong/
├── kong.yml                    # Kong declarative configuration
├── kong-setup.sh              # Kong configuration script
├── health-check.sh            # Health check and monitoring script
├── docker-compose.monitor.yml  # Additional monitoring services
└── README.md                  # This file

monitoring/
├── prometheus.yml             # Prometheus scraping configuration
├── alertmanager.yml          # Alert management configuration
└── alert_rules.yml           # Alerting rules for services
```

## 🚀 Quick Start

### 1. Start Infrastructure Services

First, ensure all base services are running:

```bash
# From the project root
docker-compose up -d postgres redis rabbitmq minio kong-database kong-migration kong
```

### 2. Configure Kong

Run the setup script to configure services and routes:

```bash
# Make scripts executable (if not already)
chmod +x docker/kong/*.sh

# Run Kong setup
./docker/kong/kong-setup.sh
```

### 3. Verify Configuration

Check that everything is working:

```bash
# Run health checks
./docker/kong/health-check.sh

# Check Kong status directly
curl http://localhost:8001/status
```

### 4. Start Monitoring (Optional)

For advanced monitoring and alerting:

```bash
# Start monitoring stack
docker-compose -f docker/kong/docker-compose.monitor.yml up -d

# Access monitoring interfaces
open http://localhost:9090    # Prometheus
open http://localhost:3001    # Grafana (admin/admin_password_2024)
open http://localhost:9093    # Alertmanager
```

## 🔧 Configuration Details

### Services and Routes

Kong is configured with the following services:

| Service | Port | Routes | Authentication |
|---------|------|--------|----------------|
| auth-service | 8001 | `/api/v1/auth/*` | Public + Protected |
| ticket-service | 8002 | `/api/v1/tickets/*` | JWT Required |
| client-service | 8003 | `/api/v1/clients/*` | JWT Required |
| notification-service | 8004 | `/api/v1/notifications/*` | JWT Required |
| email-service | 8005 | `/api/v1/emails/*` | JWT Required |

### Rate Limiting

- **Auth Service**: 30 requests/minute, 200/hour
- **Other Services**: 60 requests/minute, 1000/hour
- **Global Rate Limiting**: Applied per service

### CORS Configuration

CORS is configured to allow:
- Origins: `localhost:3000`, `127.0.0.1:3000`
- Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS, HEAD
- Headers: Standard headers + Authorization
- Credentials: Enabled

### Security Features

- **JWT Authentication**: For protected endpoints
- **Security Headers**: X-Frame-Options, X-Content-Type-Options, etc.
- **Request Size Limiting**: 5-10MB based on service
- **IP Restrictions**: Local networks allowed

## 📊 Monitoring

### Kong Metrics

Kong exposes Prometheus metrics at:
- **Admin API**: `http://localhost:8001/metrics`
- **Proxy Metrics**: Available through Prometheus plugin

### Health Checks

The health check script monitors:
- Kong Admin API availability
- Service registration status
- Route configuration
- Database connectivity
- Redis connectivity

### Alerting

Configured alerts include:
- Kong service down
- High latency (>1s)
- High error rate (5xx responses)
- Database connectivity issues
- High resource usage

## 🛠️ Development Usage

### Testing Routes

```bash
# Test auth service (public)
curl http://localhost:8000/api/v1/auth/login \
  -X POST \
  -H "Content-Type: application/json" \
  -d '{"email": "test@example.com", "password": "password"}'

# Test protected route (requires JWT)
curl http://localhost:8000/api/v1/tickets \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### Managing Kong Configuration

```bash
# List all services
curl http://localhost:8001/services

# List all routes
curl http://localhost:8001/routes

# List all plugins
curl http://localhost:8001/plugins

# Get Kong status
curl http://localhost:8001/status
```

### Updating Configuration

1. **Declarative Mode**: Update `kong.yml` and restart Kong
2. **Imperative Mode**: Use Kong Admin API directly
3. **Setup Script**: Re-run `kong-setup.sh` to apply changes

## 🔍 Troubleshooting

### Common Issues

1. **Kong won't start**
   ```bash
   # Check database connection
   docker-compose logs kong-database
   docker-compose logs kong-migration
   ```

2. **Routes not working**
   ```bash
   # Verify service registration
   curl http://localhost:8001/services

   # Check route configuration
   curl http://localhost:8001/routes
   ```

3. **CORS issues**
   ```bash
   # Check CORS plugin configuration
   curl http://localhost:8001/plugins | jq '.data[] | select(.name == "cors")'
   ```

4. **Authentication failing**
   ```bash
   # Verify JWT plugin configuration
   curl http://localhost:8001/plugins | jq '.data[] | select(.name == "key-auth")'
   ```

### Log Access

```bash
# Kong logs
docker-compose logs -f kong

# Kong access logs (if file logging enabled)
docker exec -it aidly-kong tail -f /tmp/access.log

# All monitoring services
docker-compose -f docker/kong/docker-compose.monitor.yml logs -f
```

## 📈 Performance Tuning

### Kong Configuration

For production environments, consider:

```yaml
# In docker-compose.yml kong service
environment:
  KONG_WORKER_PROCESSES: "auto"
  KONG_WORKER_CONNECTIONS: 4096
  KONG_PROXY_BUFFER_SIZE: "32k"
  KONG_PROXY_BUFFERS: "8 32k"
```

### Database Optimization

```sql
-- Kong database optimization
CREATE INDEX CONCURRENTLY idx_services_name ON services(name);
CREATE INDEX CONCURRENTLY idx_routes_service_id ON routes(service_id);
```

## 🔒 Security Considerations

### Production Checklist

- [ ] Change default Kong database password
- [ ] Enable Kong Admin API authentication
- [ ] Restrict Kong Admin API access by IP
- [ ] Use HTTPS in production
- [ ] Implement proper JWT secret rotation
- [ ] Configure proper CORS origins (remove wildcards)
- [ ] Enable request/response logging
- [ ] Set up proper monitoring and alerting
- [ ] Regular security updates

### Environment Variables

```bash
# Production environment variables
KONG_DB_PASSWORD=strong_production_password
KONG_ADMIN_ACCESS_LOG=/var/log/kong/admin_access.log
KONG_PROXY_ACCESS_LOG=/var/log/kong/access.log
KONG_ERROR_LOG=/var/log/kong/error.log
KONG_ADMIN_LISTEN=127.0.0.1:8001
```

## 📚 Additional Resources

- [Kong Documentation](https://docs.konghq.com/)
- [Kong Plugin Hub](https://docs.konghq.com/hub/)
- [Prometheus Monitoring](https://prometheus.io/docs/)
- [Grafana Dashboards](https://grafana.com/dashboards/)

---

**Note**: This setup is configured for development. For production deployment, ensure all security recommendations are implemented and passwords are changed from defaults.