#!/bin/bash

# Test Script for Smart Ticket Assignment System
# This script tests the auto-assignment functionality

BASE_URL="http://localhost:8002/api/v1"
TICKET_SERVICE_URL="$BASE_URL/tickets"
ASSIGNMENT_URL="$BASE_URL/assignments"

echo "======================================"
echo "Smart Ticket Assignment - Test Script"
echo "======================================"
echo ""

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Function to print section headers
print_section() {
    echo -e "\n${BLUE}========== $1 ==========${NC}\n"
}

# Function to print success
print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

# Function to print info
print_info() {
    echo -e "${YELLOW}→ $1${NC}"
}

# Function to print error
print_error() {
    echo -e "${RED}✗ $1${NC}"
}

# Test 1: Get Agent Workload
print_section "Test 1: Agent Workload Statistics"
print_info "Fetching current agent workloads..."

WORKLOAD_RESPONSE=$(curl -s -X GET "$ASSIGNMENT_URL/agents/workload")
echo "$WORKLOAD_RESPONSE" | jq '.'

if [[ $(echo "$WORKLOAD_RESPONSE" | jq -r '.success') == "true" ]]; then
    print_success "Successfully fetched agent workloads"
    AGENT_COUNT=$(echo "$WORKLOAD_RESPONSE" | jq '.data | length')
    print_info "Found $AGENT_COUNT agents"
else
    print_error "Failed to fetch agent workloads"
fi

# Test 2: Get Available Agents
print_section "Test 2: Available Agents"
print_info "Fetching available agents..."

AVAILABLE_RESPONSE=$(curl -s -X GET "$ASSIGNMENT_URL/agents/available")
echo "$AVAILABLE_RESPONSE" | jq '.'

if [[ $(echo "$AVAILABLE_RESPONSE" | jq -r '.success') == "true" ]]; then
    print_success "Successfully fetched available agents"
    AVAILABLE_COUNT=$(echo "$AVAILABLE_RESPONSE" | jq '[.data[] | select(.is_available == true)] | length')
    print_info "$AVAILABLE_COUNT agents available for assignment"
else
    print_error "Failed to fetch available agents"
fi

# Test 3: Create Ticket with Auto-Assignment (Least Busy Strategy)
print_section "Test 3: Create Ticket with Auto-Assignment (Least Busy)"
print_info "Creating a test ticket with least_busy strategy..."

# You'll need to replace these UUIDs with real ones from your database
CLIENT_ID="550e8400-e29b-41d4-a716-446655440000" # Replace with a real client ID

TICKET_DATA=$(cat <<EOF
{
  "subject": "Test Auto-Assignment - Least Busy",
  "description": "This ticket should be automatically assigned to the least busy agent",
  "client_id": "$CLIENT_ID",
  "priority": "medium",
  "source": "api",
  "auto_assign": true,
  "assignment_strategy": "least_busy"
}
EOF
)

TICKET_RESPONSE=$(curl -s -X POST "$TICKET_SERVICE_URL" \
  -H "Content-Type: application/json" \
  -d "$TICKET_DATA")

echo "$TICKET_RESPONSE" | jq '.'

if [[ $(echo "$TICKET_RESPONSE" | jq -r '.success') == "true" ]]; then
    TICKET_NUMBER=$(echo "$TICKET_RESPONSE" | jq -r '.data.ticket_number')
    ASSIGNED_AGENT=$(echo "$TICKET_RESPONSE" | jq -r '.data.assigned_agent_id')

    if [[ "$ASSIGNED_AGENT" != "null" ]]; then
        print_success "Ticket $TICKET_NUMBER created and auto-assigned to agent: $ASSIGNED_AGENT"
    else
        print_error "Ticket $TICKET_NUMBER created but NOT assigned (no available agent)"
    fi
else
    print_error "Failed to create ticket"
fi

# Test 4: Create High Priority Ticket (Priority-Based Strategy)
print_section "Test 4: Create High Priority Ticket (Priority-Based)"
print_info "Creating a high-priority ticket with priority_based strategy..."

HIGH_PRIORITY_DATA=$(cat <<EOF
{
  "subject": "URGENT: System Down - Auto-Assignment Test",
  "description": "This high-priority ticket should be assigned to a senior agent",
  "client_id": "$CLIENT_ID",
  "priority": "urgent",
  "source": "api",
  "auto_assign": true,
  "assignment_strategy": "priority_based"
}
EOF
)

HIGH_PRIORITY_RESPONSE=$(curl -s -X POST "$TICKET_SERVICE_URL" \
  -H "Content-Type: application/json" \
  -d "$HIGH_PRIORITY_DATA")

echo "$HIGH_PRIORITY_RESPONSE" | jq '.'

if [[ $(echo "$HIGH_PRIORITY_RESPONSE" | jq -r '.success') == "true" ]]; then
    HP_TICKET_NUMBER=$(echo "$HIGH_PRIORITY_RESPONSE" | jq -r '.data.ticket_number')
    HP_ASSIGNED_AGENT=$(echo "$HIGH_PRIORITY_RESPONSE" | jq -r '.data.assigned_agent_id')

    if [[ "$HP_ASSIGNED_AGENT" != "null" ]]; then
        print_success "High-priority ticket $HP_TICKET_NUMBER auto-assigned to: $HP_ASSIGNED_AGENT"
    else
        print_error "High-priority ticket $HP_TICKET_NUMBER created but NOT assigned"
    fi
else
    print_error "Failed to create high-priority ticket"
fi

# Test 5: Bulk Auto-Assign Unassigned Tickets
print_section "Test 5: Bulk Auto-Assign Unassigned Tickets"
print_info "Running bulk auto-assignment for unassigned tickets..."

BULK_ASSIGN_DATA=$(cat <<EOF
{
  "strategy": "least_busy",
  "limit": 5
}
EOF
)

BULK_RESPONSE=$(curl -s -X POST "$ASSIGNMENT_URL/auto-assign" \
  -H "Content-Type: application/json" \
  -d "$BULK_ASSIGN_DATA")

echo "$BULK_RESPONSE" | jq '.'

if [[ $(echo "$BULK_RESPONSE" | jq -r '.success') == "true" ]]; then
    PROCESSED=$(echo "$BULK_RESPONSE" | jq -r '.data.total_processed')
    ASSIGNED=$(echo "$BULK_RESPONSE" | jq -r '.data.assigned')
    FAILED=$(echo "$BULK_RESPONSE" | jq -r '.data.failed')

    print_success "Bulk assignment completed"
    print_info "Processed: $PROCESSED | Assigned: $ASSIGNED | Failed: $FAILED"
else
    print_error "Bulk auto-assignment failed"
fi

# Test 6: Check Final Workload
print_section "Test 6: Final Agent Workload"
print_info "Fetching updated agent workloads after assignments..."

FINAL_WORKLOAD=$(curl -s -X GET "$ASSIGNMENT_URL/agents/workload")
echo "$FINAL_WORKLOAD" | jq '.'

if [[ $(echo "$FINAL_WORKLOAD" | jq -r '.success') == "true" ]]; then
    print_success "Final workload report retrieved"

    echo ""
    print_info "Agent Summary:"
    echo "$FINAL_WORKLOAD" | jq -r '.data[] | "  - \(.name): \(.open_tickets) open tickets (resolved today: \(.resolved_today))"'
else
    print_error "Failed to fetch final workload"
fi

# Summary
print_section "Test Summary"
echo -e "${GREEN}✓ All tests completed!${NC}"
echo ""
echo "Next Steps:"
echo "  1. Review the agent workload distribution"
echo "  2. Check the ticket-service logs: tail -f storage/logs/lumen.log"
echo "  3. Verify assignments in the database"
echo "  4. Test the rebalancing feature: POST /api/v1/assignments/rebalance"
echo ""
echo "======================================"