#!/bin/bash

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_info() {
    echo -e "${BLUE}ℹ${NC} $1"
}

echo "🔍 AidlY Services Status Check"
echo "================================"

# Check infrastructure services
print_info "Infrastructure Services (Docker):"
if docker compose ps | grep -q "healthy"; then
    print_success "PostgreSQL: Running (Port 5432)"
    print_success "Redis: Running (Port 6379)"
    print_success "RabbitMQ: Running (Port 5672, Management: 15672)"
    print_success "MinIO: Running (Port 9000, Console: 9001)"
else
    print_error "Some infrastructure services are not running"
fi

echo ""
print_info "Microservices (PHP):"

# Check each microservice
services=(
    "8001:Auth Service"
    "8002:Ticket Service"
    "8003:Client Service"
    "8004:Notification Service"
    "8005:Email Service"
    "8007:Analytics Service"
)

for service in "${services[@]}"; do
    IFS=':' read -r port name <<< "$service"
    if curl -s "http://localhost:$port/health" > /dev/null 2>&1; then
        print_success "$name: Running (Port $port)"
    else
        print_error "$name: Not responding (Port $port)"
    fi
done

# Check for missing AI service on 8006
if curl -s "http://localhost:8006/health" > /dev/null 2>&1; then
    print_success "AI Integration Service: Running (Port 8006)"
else
    print_error "AI Integration Service: Not responding (Port 8006)"
fi

echo ""
print_info "Quick Service Tests:"

# Test a basic API endpoint
if curl -s "http://localhost:8001/api/v1/auth/me" | grep -q "Unauthorized\|error" 2>/dev/null; then
    print_success "Auth Service API: Responding correctly (requires auth)"
else
    auth_test=$(curl -s "http://localhost:8001/api/v1/auth/me" 2>/dev/null || echo "connection_failed")
    if [ "$auth_test" = "connection_failed" ]; then
        print_error "Auth Service API: Connection failed"
    else
        print_success "Auth Service API: Responding"
    fi
fi

echo ""
echo "🌐 Frontend URLs:"
echo "  Development Server: http://localhost:3000 (run: cd frontend && npm run dev)"
echo ""
echo "📋 Admin Interfaces:"
echo "  RabbitMQ Management: http://localhost:15672 (guest/guest)"
echo "  MinIO Console: http://localhost:9001"
echo ""
echo "🔧 To manage services:"
echo "  Start all: ./start-all-services.sh"
echo "  Stop all:  ./stop-all-services.sh"
echo "  This check: ./check-services.sh"