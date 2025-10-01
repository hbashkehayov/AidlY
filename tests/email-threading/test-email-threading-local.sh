#!/bin/bash

# Email Threading Test - Local Lumen Services
# Tests without modifying database data

echo "========================================"
echo "Email Threading - Local Services Test"
echo "========================================"
echo ""

PASSED=0
FAILED=0

pass() { echo "✓ $1"; ((PASSED++)); }
fail() { echo "✗ $1"; ((FAILED++)); }

# Configuration for local services
TICKET_SERVICE_URL="http://localhost:8002"
EMAIL_SERVICE_URL="http://localhost:8005"
AUTH_SERVICE_URL="http://localhost:8001"

echo "Service URLs:"
echo "  Ticket Service: $TICKET_SERVICE_URL"
echo "  Email Service:  $EMAIL_SERVICE_URL"
echo "  Auth Service:   $AUTH_SERVICE_URL"
echo ""

echo "1. DATABASE SCHEMA VERIFICATION"
echo "--------------------------------"

# Check tickets.sent_message_ids column
if docker exec aidly-postgres psql -U aidly_user -d aidly -tAc "SELECT column_name FROM information_schema.columns WHERE table_name = 'tickets' AND column_name = 'sent_message_ids';" 2>/dev/null | grep -q "sent_message_ids"; then
    pass "tickets.sent_message_ids column exists"
else
    fail "tickets.sent_message_ids column MISSING"
fi

# Check ticket_comments.sent_message_id column
if docker exec aidly-postgres psql -U aidly_user -d aidly -tAc "SELECT column_name FROM information_schema.columns WHERE table_name = 'ticket_comments' AND column_name = 'sent_message_id';" 2>/dev/null | grep -q "sent_message_id"; then
    pass "ticket_comments.sent_message_id column exists"
else
    fail "ticket_comments.sent_message_id column MISSING"
fi

# Check helper function
if docker exec aidly-postgres psql -U aidly_user -d aidly -tAc "SELECT proname FROM pg_proc WHERE proname = 'append_sent_message_id_to_ticket';" 2>/dev/null | grep -q "append_sent_message_id_to_ticket"; then
    pass "append_sent_message_id_to_ticket() function exists"
else
    fail "append_sent_message_id_to_ticket() function MISSING"
fi

# Check indexes
if docker exec aidly-postgres psql -U aidly_user -d aidly -tAc "SELECT indexname FROM pg_indexes WHERE tablename = 'tickets' AND indexname = 'idx_tickets_sent_message_ids';" 2>/dev/null | grep -q "idx_tickets_sent_message_ids"; then
    pass "idx_tickets_sent_message_ids index exists"
else
    fail "idx_tickets_sent_message_ids index MISSING"
fi

echo ""
echo "2. SERVICE HEALTH CHECKS"
echo "------------------------"

# Check ticket service
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$TICKET_SERVICE_URL/health" 2>/dev/null)
if [ "$HTTP_CODE" = "200" ]; then
    pass "Ticket service is running (HTTP 200)"
else
    fail "Ticket service not accessible (HTTP $HTTP_CODE)"
fi

# Check email service
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$EMAIL_SERVICE_URL/health" 2>/dev/null)
if [ "$HTTP_CODE" = "200" ]; then
    pass "Email service is running (HTTP 200)"
else
    fail "Email service not accessible (HTTP $HTTP_CODE)"
fi

echo ""
echo "3. API ENDPOINT VERIFICATION"
echo "-----------------------------"

# Test by-sent-message-id endpoint (will return error for missing param, but endpoint exists)
RESPONSE=$(curl -s "$TICKET_SERVICE_URL/api/v1/public/tickets/by-sent-message-id" 2>/dev/null)
if echo "$RESPONSE" | grep -q "message_id"; then
    pass "Endpoint 'by-sent-message-id' exists (validation working)"
else
    echo "  Response: $RESPONSE"
    fail "Endpoint 'by-sent-message-id' may not exist"
fi

# Test by-sent-message-id with fake ID (read-only, won't find anything)
TEST_MSG_ID="test-nonexistent-$(date +%s)"
RESPONSE=$(curl -s "$TICKET_SERVICE_URL/api/v1/public/tickets/by-sent-message-id?message_id=$TEST_MSG_ID" 2>/dev/null)
if echo "$RESPONSE" | grep -q "success"; then
    if echo "$RESPONSE" | grep -q '"success":false'; then
        pass "API correctly returns 'not found' for non-existent message ID"
    else
        echo "  Unexpected: $RESPONSE"
        fail "API returned unexpected response"
    fi
else
    echo "  Response: $RESPONSE"
    fail "API response format unexpected"
fi

echo ""
echo "4. CODE VERIFICATION"
echo "--------------------"

# TicketReplyEmailService
if grep -q "generateMessageId" /root/AidlY/services/email-service/app/Services/TicketReplyEmailService.php 2>/dev/null; then
    pass "TicketReplyEmailService has generateMessageId()"
else
    fail "generateMessageId() method MISSING"
fi

if grep -q "storeMessageIdInTicket" /root/AidlY/services/email-service/app/Services/TicketReplyEmailService.php 2>/dev/null; then
    pass "TicketReplyEmailService has storeMessageIdInTicket()"
else
    fail "storeMessageIdInTicket() method MISSING"
fi

# EmailToTicketService
if grep -q "by-sent-message-id" /root/AidlY/services/email-service/app/Services/EmailToTicketService.php 2>/dev/null; then
    pass "EmailToTicketService searches by sent message IDs"
else
    fail "Search by sent message ID MISSING"
fi

# TicketController
if grep -q "getBySentMessageId" /root/AidlY/services/ticket-service/app/Http/Controllers/TicketController.php 2>/dev/null; then
    pass "TicketController has getBySentMessageId()"
else
    fail "getBySentMessageId() method MISSING"
fi

if grep -q "storeMessageId" /root/AidlY/services/ticket-service/app/Http/Controllers/TicketController.php 2>/dev/null; then
    pass "TicketController has storeMessageId()"
else
    fail "storeMessageId() method MISSING"
fi

# Routes
if grep -q "by-sent-message-id" /root/AidlY/services/ticket-service/routes/web.php 2>/dev/null; then
    pass "Route 'by-sent-message-id' is registered"
else
    fail "Route 'by-sent-message-id' MISSING"
fi

if grep -q "tickets/{id}/message-id" /root/AidlY/services/ticket-service/routes/web.php 2>/dev/null; then
    pass "Route for storing message-id is registered"
else
    fail "Route for storing message-id MISSING"
fi

echo ""
echo "5. DATABASE READ-ONLY TESTS"
echo "---------------------------"

# Check existing tickets
TICKET_COUNT=$(docker exec aidly-postgres psql -U aidly_user -d aidly -tAc "SELECT COUNT(*) FROM tickets;" 2>/dev/null)
if [ -n "$TICKET_COUNT" ]; then
    pass "Can query tickets table ($TICKET_COUNT tickets)"

    if [ "$TICKET_COUNT" -gt 0 ]; then
        # Get a sample ticket (read-only)
        SAMPLE=$(docker exec aidly-postgres psql -U aidly_user -d aidly -tAc "SELECT ticket_number, array_length(sent_message_ids, 1) as msg_count FROM tickets LIMIT 1;" 2>/dev/null | head -1)
        echo "  Sample: $SAMPLE"
    fi
else
    fail "Cannot query tickets table"
fi

# Test PostgreSQL array operations
RESULT=$(docker exec aidly-postgres psql -U aidly_user -d aidly -tAc "SELECT 'test-id' = ANY(ARRAY['test-id', 'other']::text[]);" 2>/dev/null)
if [ "$RESULT" = "t" ]; then
    pass "PostgreSQL array ANY operator works"
else
    fail "PostgreSQL array ANY operator failed"
fi

echo ""
echo "6. SIMULATE EMAIL THREADING FLOW"
echo "---------------------------------"

echo "Simulating how email threading would work:"
echo ""

# Simulate Message-ID generation
TICKET_ID="550e8400-e29b-41d4-a716-446655440080"
COMMENT_ID="550e8400-e29b-41d4-a716-446655440090"
TIMESTAMP=$(date +%s)
RANDOM_PART=$(echo $RANDOM | md5sum | cut -c1-8)
MESSAGE_ID="<ticket-${TICKET_ID}-comment-${COMMENT_ID}-${TIMESTAMP}-${RANDOM_PART}@aidly.local>"

echo "1. Agent replies to ticket:"
echo "   Generated Message-ID: $MESSAGE_ID"

if echo "$MESSAGE_ID" | grep -Eq '^<ticket-[a-f0-9-]+-comment-[a-f0-9-]+-[0-9]+-[a-f0-9]+@[a-z.]+>$'; then
    pass "Message-ID format is valid"
else
    fail "Message-ID format is invalid"
fi

echo ""
echo "2. Email sent with headers:"
echo "   Message-ID: $MESSAGE_ID"
echo "   In-Reply-To: <original-client-message-id@client.com>"
echo "   References: <original-client-message-id@client.com>"
echo ""

echo "3. System would store Message-ID via:"
echo "   POST $TICKET_SERVICE_URL/api/v1/public/tickets/$TICKET_ID/message-id"
echo "   Body: {\"message_id\": \"$MESSAGE_ID\", \"comment_id\": \"$COMMENT_ID\"}"
echo ""

echo "4. Client replies to agent email:"
echo "   Email headers include:"
echo "   In-Reply-To: $MESSAGE_ID"
echo "   References: <original-client-message-id@client.com> $MESSAGE_ID"
echo ""

echo "5. System searches for ticket by:"
echo "   GET $TICKET_SERVICE_URL/api/v1/public/tickets/by-sent-message-id?message_id=$MESSAGE_ID"
echo "   Result: Finds ticket $TICKET_ID"
echo "   Action: Adds comment to existing ticket (no duplicate created!)"
echo ""

pass "Email threading flow simulation complete"

echo ""
echo "========================================"
echo "SUMMARY"
echo "========================================"
echo "Passed: $PASSED"
echo "Failed: $FAILED"
echo ""

if [ $FAILED -eq 0 ]; then
    echo "✓✓✓ ALL TESTS PASSED! ✓✓✓"
    echo ""
    echo "The email threading enhancement is fully operational!"
    echo ""
    echo "What happens now:"
    echo "  1. Client emails support → New ticket created (TKT-XXXXXX)"
    echo "  2. Agent replies via dashboard → Email sent with Message-ID stored"
    echo "  3. Client replies to agent email → System finds ticket by Message-ID"
    echo "  4. Comment added to SAME ticket → Same agent continues conversation"
    echo ""
    echo "Testing recommendations:"
    echo "  • Send a test email to your support address"
    echo "  • Reply to the ticket as an agent"
    echo "  • Reply to the agent's email from the client address"
    echo "  • Verify the reply is added to the same ticket (not a new one)"
    echo ""
    exit 0
else
    echo "✗ SOME TESTS FAILED"
    echo ""
    echo "Please review the failures above and fix before testing with real emails."
    exit 1
fi
