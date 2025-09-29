#!/bin/bash

echo "🛑 Stopping AidlY Complete Platform..."

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

# Stop PHP microservices
print_status "Stopping PHP microservices..."
pkill -f "php -S" 2>/dev/null && print_success "PHP services stopped" || print_status "No PHP services running"

# Stop Docker infrastructure services
print_status "Stopping Docker infrastructure services..."
docker compose down

# Clean up log files
print_status "Cleaning up log files..."
rm -f /tmp/*-service.log 2>/dev/null

print_success "🎉 All AidlY services have been stopped!"

echo ""
echo "To restart everything, run:"
echo "  ./start-all-services.sh"