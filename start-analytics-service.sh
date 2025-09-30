#!/bin/bash

# Start AidlY Analytics Service
# This script starts the analytics service on port 8007

cd /root/AidlY/services/analytics-service

# Kill any existing process on port 8007
lsof -ti:8007 | xargs kill -9 2>/dev/null || true

# Start the service
echo "Starting AidlY Analytics Service on port 8007..."
php -S localhost:8007 -t public > storage/logs/service.log 2>&1 &

# Save the PID
echo $! > storage/analytics-service.pid

echo "✅ Analytics Service started successfully!"
echo "📊 Access at: http://localhost:8007"
echo "📝 Logs: storage/logs/service.log"
echo "🔧 PID: $(cat storage/analytics-service.pid)"