#!/bin/bash

# Test script for Analytics Service endpoints
# This script tests all the newly implemented endpoints

echo "=========================================="
echo "Analytics Service Endpoint Testing"
echo "=========================================="

# Configuration
ANALYTICS_PORT=8007
BASE_URL="http://localhost:$ANALYTICS_PORT/api/v1"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test counter
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

# Function to test an endpoint
test_endpoint() {
    local METHOD=$1
    local ENDPOINT=$2
    local DESCRIPTION=$3
    local DATA=$4

    TOTAL_TESTS=$((TOTAL_TESTS + 1))

    echo -n "Testing: $DESCRIPTION... "

    if [ -z "$DATA" ]; then
        RESPONSE=$(curl -s -w "\n%{http_code}" -X $METHOD "$BASE_URL$ENDPOINT" 2>/dev/null)
    else
        RESPONSE=$(curl -s -w "\n%{http_code}" -X $METHOD "$BASE_URL$ENDPOINT" \
            -H "Content-Type: application/json" \
            -d "$DATA" 2>/dev/null)
    fi

    HTTP_CODE=$(echo "$RESPONSE" | tail -n 1)
    BODY=$(echo "$RESPONSE" | sed '$d')

    if [[ "$HTTP_CODE" -ge 200 && "$HTTP_CODE" -lt 300 ]]; then
        echo -e "${GREEN}✓${NC} (HTTP $HTTP_CODE)"
        PASSED_TESTS=$((PASSED_TESTS + 1))
        return 0
    else
        echo -e "${RED}✗${NC} (HTTP $HTTP_CODE)"
        FAILED_TESTS=$((FAILED_TESTS + 1))
        echo "  Response: $BODY"
        return 1
    fi
}

# Check if analytics service is running
echo "Checking if Analytics Service is running..."
if ! curl -s "http://localhost:$ANALYTICS_PORT/health" > /dev/null 2>&1; then
    echo -e "${RED}Analytics Service is not running on port $ANALYTICS_PORT${NC}"
    echo "Please start the service first with: docker-compose up analytics-service"
    exit 1
fi
echo -e "${GREEN}Analytics Service is running${NC}"
echo ""

# Test Dashboard endpoints
echo "=== Dashboard Controller ==="
test_endpoint "GET" "/dashboard/stats" "Dashboard statistics"
test_endpoint "GET" "/dashboard/trends" "Ticket trends"
test_endpoint "GET" "/dashboard/activity" "Activity feed"
test_endpoint "GET" "/dashboard/sla-compliance" "SLA compliance"
test_endpoint "GET" "/dashboard/agent-performance" "Agent performance"
echo ""

# Test Metrics endpoints
echo "=== Metrics Controller ==="
test_endpoint "POST" "/metrics/aggregate/daily" "Aggregate daily metrics" '{"date":"2024-09-26"}'
test_endpoint "POST" "/metrics/aggregate/agent/123e4567-e89b-12d3-a456-426614174000" "Aggregate agent metrics"
test_endpoint "GET" "/metrics/ticket-metrics" "Get ticket metrics"
test_endpoint "GET" "/metrics/agent-metrics" "Get agent metrics"
test_endpoint "GET" "/metrics/client-metrics" "Get client metrics"
echo ""

# Test Event endpoints
echo "=== Event Controller ==="
test_endpoint "POST" "/events" "Track single event" '{
    "event_type": "ticket_created",
    "event_category": "ticket",
    "properties": {"ticket_id": "123", "priority": "high"}
}'
test_endpoint "POST" "/events/batch" "Track batch events" '{
    "events": [
        {"event_type": "page_view", "event_category": "interaction"},
        {"event_type": "button_click", "event_category": "interaction"}
    ]
}'
test_endpoint "GET" "/events/types" "Get event types"
test_endpoint "GET" "/events/statistics" "Get event statistics"
echo ""

# Test Realtime endpoints
echo "=== Realtime Controller ==="
test_endpoint "GET" "/realtime/current-stats" "Current real-time statistics"
test_endpoint "GET" "/realtime/active-agents" "Active agents list"
test_endpoint "GET" "/realtime/queue-status" "Queue status"
echo ""

# Test Report endpoints
echo "=== Report Controller ==="
test_endpoint "GET" "/reports" "List reports"
test_endpoint "POST" "/reports" "Create report" '{
    "name": "Test Report",
    "description": "Test report description",
    "report_type": "dashboard",
    "query_sql": "SELECT COUNT(*) as total FROM tickets",
    "columns": ["total"],
    "chart_config": {},
    "filters": {},
    "is_public": true,
    "is_active": true
}'

# Store report ID if creation was successful
if [ $? -eq 0 ]; then
    # Extract ID from last response (assuming JSON format)
    REPORT_ID=$(echo "$BODY" | grep -o '"id":"[^"]*' | cut -d'"' -f4 | head -1)
    if [ ! -z "$REPORT_ID" ]; then
        test_endpoint "GET" "/reports/$REPORT_ID" "Get specific report"
        test_endpoint "POST" "/reports/$REPORT_ID/execute" "Execute report"
        test_endpoint "POST" "/reports/$REPORT_ID/schedule" "Schedule report" '{
            "frequency": "daily",
            "time_of_day": "09:00",
            "recipients": ["test@example.com"]
        }'
        test_endpoint "GET" "/reports/$REPORT_ID/executions" "Get report executions"
    fi
fi
echo ""

# Test Export endpoints
echo "=== Export Controller ==="
test_endpoint "POST" "/exports/tickets" "Export tickets" '{
    "format": "csv",
    "start_date": "2024-01-01",
    "end_date": "2024-12-31"
}'
test_endpoint "POST" "/exports/agents" "Export agents" '{
    "format": "json",
    "start_date": "2024-01-01",
    "end_date": "2024-12-31"
}'
test_endpoint "POST" "/exports/custom" "Custom export" '{
    "query": "SELECT * FROM tickets LIMIT 10",
    "format": "csv"
}'
echo ""

# Summary
echo "=========================================="
echo "Test Summary"
echo "=========================================="
echo -e "Total Tests: $TOTAL_TESTS"
echo -e "Passed: ${GREEN}$PASSED_TESTS${NC}"
echo -e "Failed: ${RED}$FAILED_TESTS${NC}"

if [ $FAILED_TESTS -eq 0 ]; then
    echo -e "\n${GREEN}All tests passed successfully!${NC}"
    exit 0
else
    echo -e "\n${YELLOW}Some tests failed. Please review the output above.${NC}"
    exit 1
fi