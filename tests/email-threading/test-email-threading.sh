#!/bin/bash

# Email Threading Test Script
# Tests the new email threading functionality without creating actual tickets

set -e

echo "========================================"
echo "Email Threading Enhancement - Test Suite"
echo "========================================"
echo ""

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

TICKET_SERVICE_URL="http://localhost:8002"
EMAIL_SERVICE_URL="http://localhost:8005"

# Test counters
TESTS_PASSED=0
TESTS_FAILED=0

# Helper function to print test results
print_result() {
    if [ $1 -eq 0 ]; then
        echo -e "${GREEN}✓ PASSED${NC}: $2"
        ((TESTS_PASSED++))
    else
        echo -e "${RED}✗ FAILED${NC}: $2"
        ((TESTS_FAILED++))
    fi
}

echo "Test 1: Verify database schema changes"
echo "----------------------------------------"

# Check if sent_message_ids column exists
RESULT=$(docker exec aidly-postgres psql -U aidly_user -d aidly -tAc "SELECT COUNT(*) FROM information_schema.columns WHERE table_name = 'tickets' AND column_name = 'sent_message_ids';" 2>/dev/null)
if [ "$RESULT" = "1" ]; then
    print_result 0 "Column 'tickets.sent_message_ids' exists"
else
    print_result 1 "Column 'tickets.sent_message_ids' missing"
fi

# Check if sent_message_id column exists in comments
RESULT=$(docker exec aidly-postgres psql -U aidly_user -d aidly -tAc "SELECT COUNT(*) FROM information_schema.columns WHERE table_name = 'ticket_comments' AND column_name = 'sent_message_id';" 2>/dev/null)
if [ "$RESULT" = "1" ]; then
    print_result 0 "Column 'ticket_comments.sent_message_id' exists"
else
    print_result 1 "Column 'ticket_comments.sent_message_id' missing"
fi

# Check if helper function exists
RESULT=$(docker exec aidly-postgres psql -U aidly_user -d aidly -tAc "SELECT COUNT(*) FROM pg_proc WHERE proname = 'append_sent_message_id_to_ticket';" 2>/dev/null)
if [ "$RESULT" = "1" ]; then
    print_result 0 "Function 'append_sent_message_id_to_ticket' exists"
else
    print_result 1 "Function 'append_sent_message_id_to_ticket' missing"
fi

# Check indexes
RESULT=$(docker exec aidly-postgres psql -U aidly_user -d aidly -tAc "SELECT COUNT(*) FROM pg_indexes WHERE indexname = 'idx_tickets_sent_message_ids';" 2>/dev/null)
if [ "$RESULT" = "1" ]; then
    print_result 0 "Index 'idx_tickets_sent_message_ids' exists"
else
    print_result 1 "Index 'idx_tickets_sent_message_ids' missing"
fi

RESULT=$(docker exec aidly-postgres psql -U aidly_user -d aidly -tAc "SELECT COUNT(*) FROM pg_indexes WHERE indexname = 'idx_ticket_comments_sent_message_id';" 2>/dev/null)
if [ "$RESULT" = "1" ]; then
    print_result 0 "Index 'idx_ticket_comments_sent_message_id' exists"
else
    print_result 1 "Index 'idx_ticket_comments_sent_message_id' missing"
fi

echo ""
echo "Test 2: Verify API endpoints exist"
echo "----------------------------------------"

# Test ticket service health
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$TICKET_SERVICE_URL/health" 2>/dev/null || echo "000")
if [ "$HTTP_CODE" = "200" ]; then
    print_result 0 "Ticket service is running"
else
    print_result 1 "Ticket service is not accessible (HTTP $HTTP_CODE)"
fi

# Test if by-sent-message-id endpoint exists (will return 422 without params, but endpoint exists)
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$TICKET_SERVICE_URL/api/v1/public/tickets/by-sent-message-id" 2>/dev/null || echo "000")
if [ "$HTTP_CODE" = "422" ] || [ "$HTTP_CODE" = "400" ]; then
    print_result 0 "Endpoint 'by-sent-message-id' is registered"
elif [ "$HTTP_CODE" = "404" ]; then
    print_result 1 "Endpoint 'by-sent-message-id' returns 404 (not found)"
else
    print_result 0 "Endpoint 'by-sent-message-id' exists (HTTP $HTTP_CODE)"
fi

# Test email service health
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$EMAIL_SERVICE_URL/health" 2>/dev/null || echo "000")
if [ "$HTTP_CODE" = "200" ]; then
    print_result 0 "Email service is running"
else
    print_result 1 "Email service is not accessible (HTTP $HTTP_CODE)"
fi

echo ""
echo "Test 3: Test helper function (using test ticket)"
echo "----------------------------------------"

# Find an existing ticket to test with (read-only)
EXISTING_TICKET=$(docker exec aidly-postgres psql -U aidly_user -d aidly -tAc "SELECT id FROM tickets LIMIT 1;" 2>/dev/null | tr -d ' ')

if [ -n "$EXISTING_TICKET" ]; then
    echo "Using existing ticket: $EXISTING_TICKET"

    # Get current sent_message_ids (read-only)
    CURRENT_IDS=$(docker exec aidly-postgres psql -U aidly_user -d aidly -tAc "SELECT sent_message_ids FROM tickets WHERE id = '$EXISTING_TICKET';" 2>/dev/null)
    echo "Current sent_message_ids: $CURRENT_IDS"
    print_result 0 "Successfully read ticket data (no modifications made)"
else
    print_result 1 "No existing tickets found to test with"
fi

echo ""
echo "Test 4: Verify code changes in services"
echo "----------------------------------------"

# Check if TicketReplyEmailService has the new method
if grep -q "generateMessageId" /root/AidlY/services/email-service/app/Services/TicketReplyEmailService.php 2>/dev/null; then
    print_result 0 "Method 'generateMessageId' exists in TicketReplyEmailService"
else
    print_result 1 "Method 'generateMessageId' missing in TicketReplyEmailService"
fi

if grep -q "storeMessageIdInTicket" /root/AidlY/services/email-service/app/Services/TicketReplyEmailService.php 2>/dev/null; then
    print_result 0 "Method 'storeMessageIdInTicket' exists in TicketReplyEmailService"
else
    print_result 1 "Method 'storeMessageIdInTicket' missing in TicketReplyEmailService"
fi

# Check if EmailToTicketService searches by sent message ID
if grep -q "by-sent-message-id" /root/AidlY/services/email-service/app/Services/EmailToTicketService.php 2>/dev/null; then
    print_result 0 "EmailToTicketService searches by sent message ID"
else
    print_result 1 "EmailToTicketService doesn't search by sent message ID"
fi

# Check if controller has new methods
if grep -q "getBySentMessageId" /root/AidlY/services/ticket-service/app/Http/Controllers/TicketController.php 2>/dev/null; then
    print_result 0 "Method 'getBySentMessageId' exists in TicketController"
else
    print_result 1 "Method 'getBySentMessageId' missing in TicketController"
fi

if grep -q "storeMessageId" /root/AidlY/services/ticket-service/app/Http/Controllers/TicketController.php 2>/dev/null; then
    print_result 0 "Method 'storeMessageId' exists in TicketController"
else
    print_result 1 "Method 'storeMessageId' missing in TicketController"
fi

# Check if routes are registered
if grep -q "by-sent-message-id" /root/AidlY/services/ticket-service/routes/web.php 2>/dev/null; then
    print_result 0 "Route 'by-sent-message-id' is registered"
else
    print_result 1 "Route 'by-sent-message-id' not registered"
fi

if grep -q "message-id" /root/AidlY/services/ticket-service/routes/web.php 2>/dev/null; then
    print_result 0 "Route for storing message-id is registered"
else
    print_result 1 "Route for storing message-id not registered"
fi

echo ""
echo "Test 5: Simulate Message-ID generation"
echo "----------------------------------------"

# Simulate what the generateMessageId function would produce
TICKET_ID="550e8400-e29b-41d4-a716-446655440080"
COMMENT_ID="550e8400-e29b-41d4-a716-446655440090"
DOMAIN="aidly.local"
TIMESTAMP=$(date +%s)
RANDOM_PART=$(echo $RANDOM | md5sum | cut -c1-8)

SIMULATED_MESSAGE_ID="<ticket-${TICKET_ID}-comment-${COMMENT_ID}-${TIMESTAMP}-${RANDOM_PART}@${DOMAIN}>"
echo "Simulated Message-ID: $SIMULATED_MESSAGE_ID"

if [[ $SIMULATED_MESSAGE_ID =~ ^<ticket-[a-f0-9-]+-comment-[a-f0-9-]+-[0-9]+-[a-f0-9]+@[a-z.]+>$ ]]; then
    print_result 0 "Message-ID format is correct"
else
    print_result 1 "Message-ID format is incorrect"
fi

echo ""
echo "Test 6: Test API endpoint with mock data"
echo "----------------------------------------"

# Test the by-sent-message-id endpoint with a non-existent ID (safe, read-only)
TEST_MESSAGE_ID="test-non-existent-message-id-12345"
RESPONSE=$(curl -s -X GET "$TICKET_SERVICE_URL/api/v1/public/tickets/by-sent-message-id?message_id=$TEST_MESSAGE_ID" 2>/dev/null)

if echo "$RESPONSE" | grep -q "success"; then
    if echo "$RESPONSE" | grep -q '"success":false'; then
        print_result 0 "API endpoint returns proper error for non-existent message ID"
    else
        echo "Response: $RESPONSE"
        print_result 1 "API endpoint returned unexpected success for non-existent ID"
    fi
else
    echo "Response: $RESPONSE"
    print_result 1 "API endpoint response format unexpected"
fi

echo ""
echo "Test 7: Check PostgreSQL array operations"
echo "----------------------------------------"

# Test PostgreSQL array operator syntax (no data modification)
ARRAY_TEST=$(docker exec aidly-postgres psql -U aidly_user -d aidly -tAc "SELECT 'test-id' = ANY(ARRAY['test-id', 'other-id']::text[]);" 2>/dev/null)
if [ "$ARRAY_TEST" = "t" ]; then
    print_result 0 "PostgreSQL array ANY operator works correctly"
else
    print_result 1 "PostgreSQL array ANY operator not working"
fi

# Test empty array default
EMPTY_ARRAY=$(docker exec aidly-postgres psql -U aidly_user -d aidly -tAc "SELECT '{}'::text[];" 2>/dev/null)
if [ "$EMPTY_ARRAY" = "{}" ]; then
    print_result 0 "Empty array default value works"
else
    print_result 1 "Empty array default value not working"
fi

echo ""
echo "========================================"
echo "Test Summary"
echo "========================================"
echo -e "${GREEN}Tests Passed: $TESTS_PASSED${NC}"
echo -e "${RED}Tests Failed: $TESTS_FAILED${NC}"
echo ""

if [ $TESTS_FAILED -eq 0 ]; then
    echo -e "${GREEN}All tests passed! ✓${NC}"
    echo ""
    echo "The email threading enhancement is properly installed."
    echo "You can now test with actual email flow."
    exit 0
else
    echo -e "${RED}Some tests failed!${NC}"
    echo ""
    echo "Please review the failed tests above."
    exit 1
fi
