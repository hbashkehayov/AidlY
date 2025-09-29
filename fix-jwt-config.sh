#!/bin/bash

echo "🔧 Fixing JWT configuration across all services..."

# Standard JWT secret to use across all services
JWT_SECRET="your_jwt_secret_key_change_this_in_production_minimum_32_chars"

# List of all services
services=(
    "auth-service"
    "ticket-service"
    "client-service"
    "notification-service"
    "email-service"
    "ai-integration-service"
    "analytics-service"
)

echo "Setting JWT_SECRET for all services..."

for service in "${services[@]}"; do
    env_file="/root/AidlY/services/$service/.env"

    if [ -f "$env_file" ]; then
        echo "Updating $service..."

        # Remove existing JWT_SECRET line if it exists
        sed -i '/^JWT_SECRET=/d' "$env_file"

        # Add the JWT_SECRET
        echo "JWT_SECRET=$JWT_SECRET" >> "$env_file"

        echo "✓ $service updated"
    else
        echo "⚠️  $env_file not found"
    fi
done

echo ""
echo "🔄 Restarting services to pick up new configuration..."

# Stop all PHP services
pkill -f "php -S" 2>/dev/null

sleep 2

# Restart services
echo "Starting services..."

# Start services in background
cd /root/AidlY/services/auth-service && nohup php -S 0.0.0.0:8001 -t public/ > /tmp/auth-service.log 2>&1 &
cd /root/AidlY/services/ticket-service && nohup php -S 0.0.0.0:8002 -t public/ > /tmp/ticket-service.log 2>&1 &
cd /root/AidlY/services/client-service && nohup php -S 0.0.0.0:8003 -t public/ > /tmp/client-service.log 2>&1 &
cd /root/AidlY/services/notification-service && nohup php -S 0.0.0.0:8004 -t public/ > /tmp/notification-service.log 2>&1 &
cd /root/AidlY/services/email-service && nohup php -S 0.0.0.0:8005 -t public/ > /tmp/email-service.log 2>&1 &
cd /root/AidlY/services/ai-integration-service && nohup php -S 0.0.0.0:8006 -t public/ > /tmp/ai-service.log 2>&1 &
cd /root/AidlY/services/analytics-service && nohup php -S 0.0.0.0:8007 -t public/ > /tmp/analytics-service.log 2>&1 &

sleep 5

echo ""
echo "✅ JWT configuration fixed and services restarted!"
echo ""
echo "Testing authentication across services..."

# Test login and get token
echo -n "Testing login: "
RESPONSE=$(curl -s -X POST http://localhost:8001/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@aidly.com","password":"password123"}')

if echo "$RESPONSE" | grep -q "access_token"; then
    echo "✓ Login successful"

    # Extract token
    TOKEN=$(echo "$RESPONSE" | grep -o '"access_token":"[^"]*"' | cut -d'"' -f4)

    # Test token with ticket service
    echo -n "Testing token with ticket service: "
    TICKET_TEST=$(curl -s -H "Authorization: Bearer $TOKEN" \
                      -H "Content-Type: application/json" \
                      "http://localhost:8002/api/v1/tickets?per_page=1")

    if echo "$TICKET_TEST" | grep -q "success.*true"; then
        echo "✅ Token validation working!"
    else
        echo "❌ Token validation failed"
        echo "Response: $TICKET_TEST"
    fi
else
    echo "❌ Login failed"
    echo "Response: $RESPONSE"
fi

echo ""
echo "🎉 JWT configuration fix complete!"