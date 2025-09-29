#!/bin/bash

# Start all AidlY microservices
echo "Starting AidlY microservices..."

# Kill any existing PHP servers
pkill -f "php -S" 2>/dev/null

# Start Auth Service
echo "Starting Auth Service on port 8011..."
cd /root/AidlY/services/auth-service && php -S localhost:8011 -t public/ > /dev/null 2>&1 &

# Start Ticket Service
echo "Starting Ticket Service on port 8012..."
cd /root/AidlY/services/ticket-service && php -S localhost:8012 -t public/ > /dev/null 2>&1 &

# Start Client Service
echo "Starting Client Service on port 8003..."
cd /root/AidlY/services/client-service && php -S localhost:8003 -t public/ > /dev/null 2>&1 &

# Wait a moment for services to start
sleep 2

# Check service health
echo ""
echo "Checking service health..."
echo -n "Auth Service: "
curl -s http://localhost:8011/health | grep -q "healthy" && echo "✓ Running" || echo "✗ Failed"

echo -n "Ticket Service: "
curl -s http://localhost:8012/health | grep -q "healthy" && echo "✓ Running" || echo "✗ Failed"

echo -n "Client Service: "
curl -s http://localhost:8003/health | grep -q "healthy" && echo "✓ Running" || echo "✗ Failed"

echo ""
echo "All services started! Frontend can now connect to the backend services."
echo ""
echo "Service URLs:"
echo "  Auth Service:   http://localhost:8011"
echo "  Ticket Service: http://localhost:8012"
echo "  Client Service: http://localhost:8003"
echo ""
echo "To stop all services, run: pkill -f 'php -S'"