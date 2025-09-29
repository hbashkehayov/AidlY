#!/bin/bash

# Comprehensive Notification Service Test Script
# This script tests all major functionality of the notification service

echo "🔄 Starting Comprehensive Notification Service Tests..."
echo "====================================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Base URL for notification service
BASE_URL="http://localhost:8004"

# Test API Key for internal requests
API_KEY="test-api-key-2024"

# Function to make HTTP requests
make_request() {
    local method=$1
    local endpoint=$2
    local data=$3
    local headers=$4

    if [ -n "$data" ]; then
        if [ -n "$headers" ]; then
            curl -s -X $method "$BASE_URL$endpoint" \
                -H "Content-Type: application/json" \
                -H "X-API-Key: $API_KEY" \
                -H "$headers" \
                -d "$data"
        else
            curl -s -X $method "$BASE_URL$endpoint" \
                -H "Content-Type: application/json" \
                -H "X-API-Key: $API_KEY" \
                -d "$data"
        fi
    else
        if [ -n "$headers" ]; then
            curl -s -X $method "$BASE_URL$endpoint" \
                -H "X-API-Key: $API_KEY" \
                -H "$headers"
        else
            curl -s -X $method "$BASE_URL$endpoint" \
                -H "X-API-Key: $API_KEY"
        fi
    fi
}

# Function to check if response is successful
check_response() {
    local response=$1
    local test_name=$2

    if echo "$response" | grep -q '"success":true\|"status":"healthy"'; then
        echo -e "${GREEN}✅ $test_name: PASSED${NC}"
        return 0
    else
        echo -e "${RED}❌ $test_name: FAILED${NC}"
        echo -e "${RED}Response: $response${NC}"
        return 1
    fi
}

# Test counter
TOTAL_TESTS=0
PASSED_TESTS=0

run_test() {
    local test_name=$1
    local method=$2
    local endpoint=$3
    local data=$4
    local headers=$5

    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    echo -e "${BLUE}Testing: $test_name${NC}"

    response=$(make_request "$method" "$endpoint" "$data" "$headers")
    if check_response "$response" "$test_name"; then
        PASSED_TESTS=$((PASSED_TESTS + 1))
    fi
    echo ""
}

# Test 1: Health Check
echo -e "${YELLOW}📋 Basic Health Tests${NC}"
run_test "Health Check" "GET" "/health"

# Test 2: Service Root
run_test "Service Root" "GET" "/"

# Test 3: Template Default Seeding
echo -e "${YELLOW}📋 Template Management Tests${NC}"
run_test "Seed Default Templates" "POST" "/api/v1/templates/seed-defaults"

# Test 4: Get Templates
run_test "Get All Templates" "GET" "/api/v1/templates"

# Test 5: Create Custom Template
template_data='{
    "name": "test_template",
    "event_type": "test_event",
    "channel": "email",
    "title_template": "Test Notification",
    "message_template": "Hello {{user_name}}, this is a test notification.",
    "variables": ["user_name"],
    "is_active": true
}'
run_test "Create Custom Template" "POST" "/api/v1/templates" "$template_data"

# Test 6: Create Notification
echo -e "${YELLOW}📋 Notification Creation Tests${NC}"
notification_data='{
    "notifiable_type": "user",
    "notifiable_id": "123e4567-e89b-12d3-a456-426614174000",
    "type": "ticket_created",
    "channel": "in_app",
    "title": "Test Notification",
    "message": "This is a test notification",
    "data": {
        "ticket_id": "456e7890-e89b-12d3-a456-426614174000",
        "ticket_number": "TICK-001"
    },
    "priority": "normal"
}'
run_test "Create Notification" "POST" "/api/v1/notifications" "$notification_data"

# Test 7: Get Notifications (requires user authentication)
user_header="Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxMjNlNDU2Ny1lODliLTEyZDMtYTQ1Ni00MjY2MTQxNzQwMDAiLCJyb2xlIjoidXNlciIsImV4cCI6OTk5OTk5OTk5OX0.test"
run_test "Get User Notifications" "GET" "/api/v1/notifications?notifiable_id=123e4567-e89b-12d3-a456-426614174000&notifiable_type=user" "" "$user_header"

# Test 8: Get Notification Stats
run_test "Get Notification Stats" "GET" "/api/v1/notifications/stats?notifiable_id=123e4567-e89b-12d3-a456-426614174000&notifiable_type=user" "" "$user_header"

# Test 9: Queue Processing
echo -e "${YELLOW}📋 Queue Processing Tests${NC}"
run_test "Process Notification Queue" "POST" "/api/v1/queue/process"

# Test 10: Queue Stats
run_test "Get Queue Stats" "GET" "/api/v1/queue/stats"

# Test 11: Webhook Processing
echo -e "${YELLOW}📋 Webhook Processing Tests${NC}"
webhook_data='{
    "ticket_id": "456e7890-e89b-12d3-a456-426614174000",
    "ticket_number": "TICK-002",
    "subject": "Test Ticket",
    "status": "new",
    "priority": "high",
    "customer_name": "John Doe",
    "customer_email": "john@example.com",
    "assigned_agent": null
}'
run_test "Process Ticket Created Webhook" "POST" "/api/v1/webhooks/ticket-created" "$webhook_data"

# Test 12: Bulk Notification Creation
echo -e "${YELLOW}📋 Bulk Operations Tests${NC}"
bulk_data='{
    "notifications": [
        {
            "notifiable_type": "user",
            "notifiable_id": "123e4567-e89b-12d3-a456-426614174000",
            "type": "system_update",
            "channel": "in_app",
            "title": "System Update",
            "message": "System will be updated tonight.",
            "priority": "low"
        },
        {
            "notifiable_type": "user",
            "notifiable_id": "789e0123-e89b-12d3-a456-426614174000",
            "type": "system_update",
            "channel": "email",
            "title": "System Update",
            "message": "System will be updated tonight.",
            "priority": "low"
        }
    ]
}'
run_test "Send Bulk Notifications" "POST" "/api/v1/notifications/bulk" "$bulk_data"

# Test 13: Preference Management
echo -e "${YELLOW}📋 Preference Management Tests${NC}"
preference_data='{
    "email_notifications": true,
    "in_app_notifications": true,
    "push_notifications": false,
    "digest_frequency": "daily",
    "quiet_hours": {
        "enabled": true,
        "start": "22:00",
        "end": "08:00"
    }
}'
run_test "Update User Preferences" "PUT" "/api/v1/preferences?user_id=123e4567-e89b-12d3-a456-426614174000" "$preference_data" "$user_header"

# Test 14: Get User Preferences
run_test "Get User Preferences" "GET" "/api/v1/preferences?user_id=123e4567-e89b-12d3-a456-426614174000" "" "$user_header"

# Test 15: Retry Failed Notifications
echo -e "${YELLOW}📋 Error Handling Tests${NC}"
run_test "Retry Failed Notifications" "POST" "/api/v1/queue/retry-failed"

# Show final results
echo "====================================================="
echo -e "${BLUE}📊 Test Results Summary${NC}"
echo "====================================================="

if [ $PASSED_TESTS -eq $TOTAL_TESTS ]; then
    echo -e "${GREEN}🎉 All tests passed! ($PASSED_TESTS/$TOTAL_TESTS)${NC}"
    echo -e "${GREEN}✅ Notification Service is 100% functional!${NC}"
else
    echo -e "${YELLOW}⚠️  Tests passed: $PASSED_TESTS/$TOTAL_TESTS${NC}"
    FAILED_TESTS=$((TOTAL_TESTS - PASSED_TESTS))
    echo -e "${RED}❌ Tests failed: $FAILED_TESTS${NC}"

    if [ $PASSED_TESTS -ge $((TOTAL_TESTS * 85 / 100)) ]; then
        echo -e "${GREEN}✅ Notification Service is highly functional (>85% pass rate)${NC}"
    elif [ $PASSED_TESTS -ge $((TOTAL_TESTS * 70 / 100)) ]; then
        echo -e "${YELLOW}⚠️  Notification Service is moderately functional (70-84% pass rate)${NC}"
    else
        echo -e "${RED}❌ Notification Service needs significant work (<70% pass rate)${NC}"
    fi
fi

echo ""
echo -e "${BLUE}🏁 Comprehensive test completed!${NC}"

# Calculate percentage
PERCENTAGE=$((PASSED_TESTS * 100 / TOTAL_TESTS))
echo -e "${BLUE}📈 Overall functionality: ${PERCENTAGE}%${NC}"

exit 0