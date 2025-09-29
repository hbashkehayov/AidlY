#!/bin/bash

echo "🚀 Starting AidlY Complete Platform..."

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to check if a port is in use
check_port() {
    netstat -tuln | grep ":$1 " > /dev/null 2>&1
}

# Function to wait for service health
wait_for_service() {
    local url=$1
    local service_name=$2
    local max_attempts=30
    local attempt=0

    print_status "Waiting for $service_name to be healthy..."
    while [ $attempt -lt $max_attempts ]; do
        if curl -s "$url" > /dev/null 2>&1; then
            print_success "$service_name is healthy!"
            return 0
        fi
        attempt=$((attempt + 1))
        sleep 2
        echo -n "."
    done
    print_error "$service_name failed to start within 60 seconds"
    return 1
}

# Kill any existing PHP servers
print_status "Stopping any existing PHP servers..."
pkill -f "php -S" 2>/dev/null || true
pkill -f "php artisan serve" 2>/dev/null || true

print_status "Stopping any running Docker services..."
docker compose down > /dev/null 2>&1 || true

# Start infrastructure services
print_status "Starting infrastructure services (PostgreSQL, Redis, RabbitMQ, MinIO)..."
docker compose up -d postgres redis rabbitmq minio

# Wait for infrastructure services to be ready
sleep 10

# Check infrastructure services
print_status "Checking infrastructure services..."
if docker compose ps | grep -q "healthy"; then
    print_success "Infrastructure services are starting up"
else
    print_warning "Some infrastructure services may not be fully ready yet"
fi

# Start microservices with PHP built-in server
print_status "Starting PHP microservices..."

# Start Auth Service
print_status "Starting Auth Service on port 8001..."
if check_port 8001; then
    print_warning "Port 8001 is already in use"
else
    cd /root/AidlY/services/auth-service && nohup php -S 0.0.0.0:8001 -t public/ > /tmp/auth-service.log 2>&1 &
    sleep 2
fi

# Start Ticket Service
print_status "Starting Ticket Service on port 8002..."
if check_port 8002; then
    print_warning "Port 8002 is already in use"
else
    cd /root/AidlY/services/ticket-service && nohup php -S 0.0.0.0:8002 -t public/ > /tmp/ticket-service.log 2>&1 &
    sleep 2
fi

# Start Client Service
print_status "Starting Client Service on port 8003..."
if check_port 8003; then
    print_warning "Port 8003 is already in use"
else
    cd /root/AidlY/services/client-service && nohup php -S 0.0.0.0:8003 -t public/ > /tmp/client-service.log 2>&1 &
    sleep 2
fi

# Start Notification Service
print_status "Starting Notification Service on port 8004..."
if check_port 8004; then
    print_warning "Port 8004 is already in use"
else
    cd /root/AidlY/services/notification-service && nohup php -S 0.0.0.0:8004 -t public/ > /tmp/notification-service.log 2>&1 &
    sleep 2
fi

# Start Email Service
print_status "Starting Email Service on port 8005..."
if check_port 8005; then
    print_warning "Port 8005 is already in use"
else
    cd /root/AidlY/services/email-service && nohup php -S 0.0.0.0:8005 -t public/ > /tmp/email-service.log 2>&1 &
    sleep 2
fi

# Start AI Integration Service
print_status "Starting AI Integration Service on port 8006..."
if check_port 8006; then
    print_warning "Port 8006 is already in use"
else
    cd /root/AidlY/services/ai-integration-service && nohup php -S 0.0.0.0:8006 -t public/ > /tmp/ai-service.log 2>&1 &
    sleep 2
fi

# Start Analytics Service
print_status "Starting Analytics Service on port 8007..."
if check_port 8007; then
    print_warning "Port 8007 is already in use"
else
    cd /root/AidlY/services/analytics-service && nohup php -S 0.0.0.0:8007 -t public/ > /tmp/analytics-service.log 2>&1 &
    sleep 2
fi

# Wait a moment for all services to initialize
sleep 5

# Check service health
print_status "Checking microservice health..."

services=(
    "http://localhost:8001/health:Auth Service"
    "http://localhost:8002/health:Ticket Service"
    "http://localhost:8003/health:Client Service"
    "http://localhost:8004/health:Notification Service"
    "http://localhost:8005/health:Email Service"
    "http://localhost:8006/health:AI Integration Service"
    "http://localhost:8007/health:Analytics Service"
)

all_healthy=true

for service in "${services[@]}"; do
    IFS=':' read -r url name <<< "$service"
    if curl -s "$url" | grep -q "healthy\|ok\|operational" 2>/dev/null; then
        print_success "$name ✓"
    else
        print_error "$name ✗"
        all_healthy=false
    fi
done

# Summary
echo ""
echo "========================================="
if $all_healthy; then
    print_success "🎉 All AidlY services are running!"
else
    print_warning "⚠️  Some services may have issues. Check logs for details."
fi

echo ""
echo "📋 Service Status:"
echo "Infrastructure Services (Docker):"
echo "  PostgreSQL:     localhost:5432"
echo "  Redis:          localhost:6379"
echo "  RabbitMQ:       localhost:5672 (Management: localhost:15672)"
echo "  MinIO:          localhost:9000 (Console: localhost:9001)"
echo ""
echo "Microservices (PHP):"
echo "  Auth Service:        http://localhost:8001"
echo "  Ticket Service:      http://localhost:8002"
echo "  Client Service:      http://localhost:8003"
echo "  Notification Service: http://localhost:8004"
echo "  Email Service:       http://localhost:8005"
echo "  AI Integration:      http://localhost:8006"
echo "  Analytics Service:   http://localhost:8007"
echo ""
echo "📝 Service Logs:"
echo "  Auth:         tail -f /tmp/auth-service.log"
echo "  Ticket:       tail -f /tmp/ticket-service.log"
echo "  Client:       tail -f /tmp/client-service.log"
echo "  Notification: tail -f /tmp/notification-service.log"
echo "  Email:        tail -f /tmp/email-service.log"
echo "  AI:           tail -f /tmp/ai-service.log"
echo "  Analytics:    tail -f /tmp/analytics-service.log"
echo ""
echo "🛑 To stop all services:"
echo "  ./stop-all-services.sh"
echo ""
echo "🌐 Frontend Development:"
echo "  cd frontend && npm run dev"
echo "========================================="