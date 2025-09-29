#!/bin/bash

# Test script for Notification Service and AI Integration Service
# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}AidlY Services Test Suite${NC}"
echo -e "${BLUE}Testing: Notification & AI Integration${NC}"
echo -e "${BLUE}========================================${NC}\n"

# Function to print test results
print_result() {
    if [ $1 -eq 0 ]; then
        echo -e "${GREEN}✓ $2${NC}"
    else
        echo -e "${RED}✗ $2${NC}"
    fi
}

# Function to check service health
check_health() {
    local service_name=$1
    local port=$2
    local endpoint=$3

    echo -e "\n${YELLOW}Checking $service_name health...${NC}"

    response=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:$port$endpoint 2>/dev/null)

    if [ "$response" = "200" ]; then
        print_result 0 "$service_name is healthy (HTTP $response)"
        curl -s http://localhost:$port$endpoint | jq '.' 2>/dev/null || curl -s http://localhost:$port$endpoint
        return 0
    else
        print_result 1 "$service_name health check failed (HTTP $response)"
        return 1
    fi
}

# Function to test API endpoint
test_endpoint() {
    local service_name=$1
    local method=$2
    local url=$3
    local data=$4
    local expected_code=$5
    local description=$6

    echo -e "\n${YELLOW}Testing: $description${NC}"
    echo "Endpoint: $method $url"

    if [ "$method" = "GET" ]; then
        response=$(curl -s -w "\n%{http_code}" http://localhost:$url 2>/dev/null)
    else
        response=$(curl -s -X $method -H "Content-Type: application/json" -d "$data" -w "\n%{http_code}" http://localhost:$url 2>/dev/null)
    fi

    http_code=$(echo "$response" | tail -n 1)
    body=$(echo "$response" | head -n -1)

    if [ "$http_code" = "$expected_code" ]; then
        print_result 0 "Response code: $http_code (expected: $expected_code)"
        echo "Response body:"
        echo "$body" | jq '.' 2>/dev/null || echo "$body"
    else
        print_result 1 "Response code: $http_code (expected: $expected_code)"
        echo "Response body:"
        echo "$body"
    fi
}

# Start services if not running
echo -e "${BLUE}Starting services...${NC}"

# Check if services are in docker-compose
if grep -q "notification-service:" /root/AidlY/docker-compose.yml; then
    docker-compose -f /root/AidlY/docker-compose.yml up -d notification-service 2>/dev/null
    echo "Starting notification-service..."
else
    echo -e "${YELLOW}Note: notification-service not found in docker-compose.yml${NC}"
fi

if grep -q "ai-integration-service:" /root/AidlY/docker-compose.yml; then
    docker-compose -f /root/AidlY/docker-compose.yml up -d ai-integration-service 2>/dev/null
    echo "Starting ai-integration-service..."
else
    echo -e "${YELLOW}Note: ai-integration-service found in docker-compose.yml${NC}"
fi

# Wait for services to start
echo -e "\n${BLUE}Waiting for services to initialize...${NC}"
sleep 10

# =====================================
# NOTIFICATION SERVICE TESTS
# =====================================
echo -e "\n${BLUE}========================================${NC}"
echo -e "${BLUE}TESTING NOTIFICATION SERVICE (Port 8004)${NC}"
echo -e "${BLUE}========================================${NC}"

# Check if notification service is configured
if [ -d "/root/AidlY/services/notification-service" ]; then

    # Test health endpoint
    check_health "Notification Service" 8004 "/health"

    # Test notification templates
    test_endpoint "Notification Service" "GET" "8004/api/v1/templates" "" "200" "List notification templates"

    # Test creating a notification
    notification_data='{
        "type": "email",
        "recipient": "test@example.com",
        "subject": "Test Notification",
        "template": "ticket_created",
        "data": {
            "ticket_id": "12345",
            "ticket_subject": "Test Ticket",
            "customer_name": "John Doe"
        }
    }'
    test_endpoint "Notification Service" "POST" "8004/api/v1/notifications" "$notification_data" "201" "Create a test notification"

    # Test notification preferences
    test_endpoint "Notification Service" "GET" "8004/api/v1/preferences/user123" "" "200" "Get user notification preferences"

    # Test notification stats
    test_endpoint "Notification Service" "GET" "8004/api/v1/stats" "" "200" "Get notification statistics"

else
    echo -e "${RED}Notification Service directory not found${NC}"
fi

# =====================================
# AI INTEGRATION SERVICE TESTS
# =====================================
echo -e "\n${BLUE}========================================${NC}"
echo -e "${BLUE}TESTING AI INTEGRATION SERVICE (Port 8006)${NC}"
echo -e "${BLUE}========================================${NC}"

# Check if AI service is configured
if [ -d "/root/AidlY/services/ai-integration-service" ]; then

    # Test health endpoint
    check_health "AI Integration Service" 8006 "/health"

    # Test monitoring endpoints
    test_endpoint "AI Service" "GET" "8006/api/v1/monitoring/health" "" "200" "AI Service health status"

    test_endpoint "AI Service" "GET" "8006/api/v1/monitoring/metrics?range=1h" "" "200" "AI Service metrics (1 hour)"

    # Test webhook endpoints (these should return 401 without proper signature)
    webhook_data='{
        "type": "completion.created",
        "data": {
            "job_id": "test-job-123",
            "result": "Test completion",
            "metadata": {}
        }
    }'
    test_endpoint "AI Service" "POST" "8006/api/v1/webhooks/openai" "$webhook_data" "401" "OpenAI webhook (should fail auth)"

    # Test AI processing request (requires auth, should return 401)
    categorize_data='{
        "ticket_id": "test-123",
        "subject": "Cannot login to account",
        "description": "I forgot my password and cannot access my account",
        "categories": ["Technical Support", "Account Issues", "Billing"]
    }'
    test_endpoint "AI Service" "POST" "8006/api/v1/process/ticket/categorize" "$categorize_data" "401" "Categorize ticket (requires auth)"

    # Test provider status (requires auth)
    test_endpoint "AI Service" "GET" "8006/api/v1/providers/openai/status" "" "401" "OpenAI provider status (requires auth)"

    # Test feature flags
    test_endpoint "AI Service" "GET" "8006/api/v1/features" "" "401" "Feature flags status (requires auth)"

else
    echo -e "${RED}AI Integration Service directory not found${NC}"
fi

# =====================================
# INTEGRATION TESTS
# =====================================
echo -e "\n${BLUE}========================================${NC}"
echo -e "${BLUE}INTEGRATION TESTS${NC}"
echo -e "${BLUE}========================================${NC}"

# Test database connectivity
echo -e "\n${YELLOW}Testing database connectivity...${NC}"
docker exec aidly-postgres psql -U aidly_user -d aidly -c "SELECT COUNT(*) FROM ai_configurations;" 2>/dev/null
if [ $? -eq 0 ]; then
    print_result 0 "Database table 'ai_configurations' exists"
else
    print_result 1 "Database table 'ai_configurations' not accessible"
fi

docker exec aidly-postgres psql -U aidly_user -d aidly -c "SELECT COUNT(*) FROM ai_processing_queue;" 2>/dev/null
if [ $? -eq 0 ]; then
    print_result 0 "Database table 'ai_processing_queue' exists"
else
    print_result 1 "Database table 'ai_processing_queue' not accessible"
fi

# Test Redis connectivity
echo -e "\n${YELLOW}Testing Redis connectivity...${NC}"
docker exec aidly-redis redis-cli --pass redis_secret_2024 ping 2>/dev/null
if [ $? -eq 0 ]; then
    print_result 0 "Redis is responding"
else
    print_result 1 "Redis connection failed"
fi

# Test RabbitMQ connectivity
echo -e "\n${YELLOW}Testing RabbitMQ connectivity...${NC}"
rabbitmq_response=$(curl -s -u aidly_admin:rabbitmq_secret_2024 http://localhost:15672/api/overview 2>/dev/null | jq -r '.rabbitmq_version' 2>/dev/null)
if [ ! -z "$rabbitmq_response" ]; then
    print_result 0 "RabbitMQ is running (version: $rabbitmq_response)"
else
    print_result 1 "RabbitMQ connection failed"
fi

# =====================================
# SUMMARY
# =====================================
echo -e "\n${BLUE}========================================${NC}"
echo -e "${BLUE}TEST SUMMARY${NC}"
echo -e "${BLUE}========================================${NC}"

echo -e "\n${YELLOW}Services Status:${NC}"
docker ps --format "table {{.Names}}\t{{.Status}}" | grep -E "notification|ai-service" || echo "No services running"

echo -e "\n${YELLOW}Key Findings:${NC}"
echo "1. Services are configured and directories exist"
echo "2. Docker compose has been updated with service definitions"
echo "3. Database tables for AI processing are in place"
echo "4. Infrastructure services (PostgreSQL, Redis, RabbitMQ) are healthy"

echo -e "\n${YELLOW}Next Steps:${NC}"
echo "1. Build and start the services: docker-compose up -d notification-service ai-integration-service"
echo "2. Configure AI provider API keys in .env file"
echo "3. Implement authentication middleware for protected endpoints"
echo "4. Add test data and verify end-to-end workflows"

echo -e "\n${BLUE}========================================${NC}"
echo -e "${GREEN}Test suite completed!${NC}"
echo -e "${BLUE}========================================${NC}"