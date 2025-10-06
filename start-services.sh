#!/bin/bash

# Start AidlY microservices on ports 8001-8005 and 8007
echo "Starting AidlY microservices..."

# Kill any existing PHP servers
pkill -f "php -S" 2>/dev/null

# Start Auth Service (port 8001)
echo "Starting Auth Service on port 8001..."
cd /root/AidlY/services/auth-service && php -S 0.0.0.0:8001 -t public/ > /dev/null 2>&1 &

# Start Ticket Service (port 8002)
echo "Starting Ticket Service on port 8002..."
cd /root/AidlY/services/ticket-service && php -S 0.0.0.0:8002 -t public/ > /dev/null 2>&1 &

# Start Client Service (port 8003)
echo "Starting Client Service on port 8003..."
cd /root/AidlY/services/client-service && php -S 0.0.0.0:8003 -t public/ > /dev/null 2>&1 &

# Start Notification Service (port 8004)
echo "Starting Notification Service on port 8004..."
cd /root/AidlY/services/notification-service && php -S 0.0.0.0:8004 -t public/ > /dev/null 2>&1 &

# Start Email Service (port 8005)
echo "Starting Email Service on port 8005..."
cd /root/AidlY/services/email-service && php -S 0.0.0.0:8005 -t public/ > /dev/null 2>&1 &

# Start Analytics Service (port 8007)
echo "Starting Analytics Service on port 8007..."
cd /root/AidlY/services/analytics-service && php -S 0.0.0.0:8007 -t public/ > /dev/null 2>&1 &

# Wait a moment for services to start
sleep 2

# Check service health
echo ""
echo "Checking service health..."
echo -n "Auth Service (8001): "
curl -s http://localhost:8001/health | grep -q "healthy" && echo "✓ Running" || echo "✗ Failed"

echo -n "Ticket Service (8002): "
curl -s http://localhost:8002/health | grep -q "healthy" && echo "✓ Running" || echo "✗ Failed"

echo -n "Client Service (8003): "
curl -s http://localhost:8003/health | grep -q "healthy" && echo "✓ Running" || echo "✗ Failed"

echo -n "Notification Service (8004): "
curl -s http://localhost:8004/health | grep -q "healthy" && echo "✓ Running" || echo "✗ Failed"

echo -n "Email Service (8005): "
curl -s http://localhost:8005/health | grep -q "healthy" && echo "✓ Running" || echo "✗ Failed"

echo -n "Analytics Service (8007): "
curl -s http://localhost:8007/health | grep -q "healthy" && echo "✓ Running" || echo "✗ Failed"

echo ""
echo "All services started! Frontend can now connect to the backend services."
echo ""
echo "Service URLs:"
echo "  Auth Service:         http://localhost:8001"
echo "  Ticket Service:       http://localhost:8002"
echo "  Client Service:       http://localhost:8003"
echo "  Notification Service: http://localhost:8004"
echo "  Email Service:        http://localhost:8005"
echo "  Analytics Service:    http://localhost:8007"
echo ""
echo "To stop all services, run: pkill -f 'php -S'"