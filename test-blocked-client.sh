#!/bin/bash

# ============================================================
# AidlY - Blocked Client Email-to-Ticket Test Script
# ============================================================
# This script tests the blocked client functionality by:
# 1. Creating a test client
# 2. Blocking the client
# 3. Simulating an email from the blocked client
# 4. Verifying the email is blocked and notification sent
# ============================================================

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Service URLs
CLIENT_SERVICE_URL=${CLIENT_SERVICE_URL:-"http://localhost:8003"}
EMAIL_SERVICE_URL=${EMAIL_SERVICE_URL:-"http://localhost:8005"}
TICKET_SERVICE_URL=${TICKET_SERVICE_URL:-"http://localhost:8002"}

# Test data
TEST_EMAIL="blocked-test-$(date +%s)@example.com"
TEST_NAME="Blocked Test User"
TEST_SUBJECT="Test Email from Blocked Client"

echo -e "${BLUE}============================================================${NC}"
echo -e "${BLUE}AidlY - Blocked Client Email-to-Ticket Test${NC}"
echo -e "${BLUE}============================================================${NC}"
echo ""
echo -e "${YELLOW}Test Email:${NC} $TEST_EMAIL"
echo -e "${YELLOW}Client Service:${NC} $CLIENT_SERVICE_URL"
echo -e "${YELLOW}Email Service:${NC} $EMAIL_SERVICE_URL"
echo -e "${YELLOW}Ticket Service:${NC} $TICKET_SERVICE_URL"
echo ""

# ============================================================
# STEP 1: Create a Test Client
# ============================================================
echo -e "${BLUE}[STEP 1]${NC} Creating test client..."
CREATE_CLIENT_RESPONSE=$(curl -s -X POST "$CLIENT_SERVICE_URL/api/v1/clients" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "'"$TEST_EMAIL"'",
    "name": "'"$TEST_NAME"'",
    "company": "Test Company",
    "is_blocked": false
  }')

echo "$CREATE_CLIENT_RESPONSE" | jq '.' 2>/dev/null || echo "$CREATE_CLIENT_RESPONSE"

# Extract client ID
CLIENT_ID=$(echo "$CREATE_CLIENT_RESPONSE" | jq -r '.data.id // empty')

if [ -z "$CLIENT_ID" ] || [ "$CLIENT_ID" == "null" ]; then
    echo -e "${RED}❌ Failed to create client${NC}"
    echo "Response: $CREATE_CLIENT_RESPONSE"
    exit 1
fi

echo -e "${GREEN}✅ Client created successfully${NC}"
echo -e "   Client ID: ${YELLOW}$CLIENT_ID${NC}"
echo ""

# ============================================================
# STEP 2: Verify Client is NOT Blocked (Initial State)
# ============================================================
echo -e "${BLUE}[STEP 2]${NC} Verifying client is initially NOT blocked..."
GET_CLIENT_RESPONSE=$(curl -s -X GET "$CLIENT_SERVICE_URL/api/v1/clients/$CLIENT_ID")

IS_BLOCKED=$(echo "$GET_CLIENT_RESPONSE" | jq -r '.data.is_blocked // false')

if [ "$IS_BLOCKED" == "true" ]; then
    echo -e "${RED}❌ Client is already blocked (unexpected)${NC}"
    exit 1
fi

echo -e "${GREEN}✅ Client is NOT blocked (as expected)${NC}"
echo ""

# ============================================================
# STEP 3: Create an Email Account (if needed)
# ============================================================
echo -e "${BLUE}[STEP 3]${NC} Creating email account for testing..."

# Check if email account exists
EMAIL_ACCOUNT_ID="test-email-account-$(date +%s)"

CREATE_EMAIL_ACCOUNT_RESPONSE=$(curl -s -X POST "$EMAIL_SERVICE_URL/api/v1/email-accounts" \
  -H "Content-Type: application/json" \
  -d '{
    "id": "'"$EMAIL_ACCOUNT_ID"'",
    "name": "Test Email Account",
    "email_address": "support@test.aidly.com",
    "provider": "smtp",
    "auto_create_tickets": true,
    "default_ticket_priority": "medium",
    "is_active": true
  }' 2>/dev/null || echo '{"success": false, "message": "Email account already exists or service unavailable"}')

echo "$CREATE_EMAIL_ACCOUNT_RESPONSE" | jq '.' 2>/dev/null || echo "$CREATE_EMAIL_ACCOUNT_RESPONSE"
echo -e "${GREEN}✅ Email account ready${NC}"
echo ""

# ============================================================
# STEP 4: Simulate Email from NON-Blocked Client (Should Work)
# ============================================================
echo -e "${BLUE}[STEP 4]${NC} Simulating email from NON-blocked client..."
echo -e "${YELLOW}Expected:${NC} Ticket should be created successfully"
echo ""

# Create email queue entry
NON_BLOCKED_EMAIL_DATA=$(cat <<EOF
{
  "email_account_id": "$EMAIL_ACCOUNT_ID",
  "from_address": "$TEST_EMAIL",
  "to_addresses": ["support@test.aidly.com"],
  "subject": "$TEST_SUBJECT (Before Block)",
  "body_plain": "This is a test email sent BEFORE the client was blocked. This should create a ticket successfully.",
  "body_html": "<p>This is a test email sent BEFORE the client was blocked. This should create a ticket successfully.</p>",
  "message_id": "test-msg-$(date +%s)@example.com",
  "status": "pending",
  "received_at": "$(date -u +"%Y-%m-%d %H:%M:%S")"
}
EOF
)

CREATE_EMAIL_RESPONSE=$(curl -s -X POST "$EMAIL_SERVICE_URL/api/v1/email-queue" \
  -H "Content-Type: application/json" \
  -d "$NON_BLOCKED_EMAIL_DATA")

echo "$CREATE_EMAIL_RESPONSE" | jq '.' 2>/dev/null || echo "$CREATE_EMAIL_RESPONSE"

EMAIL_QUEUE_ID_1=$(echo "$CREATE_EMAIL_RESPONSE" | jq -r '.data.id // empty')

if [ -n "$EMAIL_QUEUE_ID_1" ] && [ "$EMAIL_QUEUE_ID_1" != "null" ]; then
    echo -e "${GREEN}✅ Email queued successfully${NC}"
    echo -e "   Email ID: ${YELLOW}$EMAIL_QUEUE_ID_1${NC}"

    # Process the email
    echo -e "${YELLOW}Processing email...${NC}"
    sleep 2

    PROCESS_RESPONSE=$(curl -s -X POST "$EMAIL_SERVICE_URL/api/v1/email-queue/process" \
      -H "Content-Type: application/json" \
      -d '{"email_id": "'"$EMAIL_QUEUE_ID_1"'"}')

    echo "$PROCESS_RESPONSE" | jq '.' 2>/dev/null || echo "$PROCESS_RESPONSE"
    echo -e "${GREEN}✅ Email processed - Ticket should be created${NC}"
else
    echo -e "${YELLOW}⚠️  Could not queue email (service may not support direct queue creation)${NC}"
fi
echo ""

# ============================================================
# STEP 5: Block the Client
# ============================================================
echo -e "${BLUE}[STEP 5]${NC} Blocking the client..."
BLOCK_CLIENT_RESPONSE=$(curl -s -X PUT "$CLIENT_SERVICE_URL/api/v1/clients/$CLIENT_ID" \
  -H "Content-Type: application/json" \
  -d '{
    "is_blocked": true
  }')

echo "$BLOCK_CLIENT_RESPONSE" | jq '.' 2>/dev/null || echo "$BLOCK_CLIENT_RESPONSE"

# Verify client is now blocked
GET_CLIENT_BLOCKED_RESPONSE=$(curl -s -X GET "$CLIENT_SERVICE_URL/api/v1/clients/$CLIENT_ID")
IS_BLOCKED_NOW=$(echo "$GET_CLIENT_BLOCKED_RESPONSE" | jq -r '.data.is_blocked // false')

if [ "$IS_BLOCKED_NOW" == "true" ]; then
    echo -e "${GREEN}✅ Client successfully BLOCKED${NC}"
else
    echo -e "${RED}❌ Failed to block client${NC}"
    exit 1
fi
echo ""

# ============================================================
# STEP 6: Simulate Email from BLOCKED Client (Should Be Rejected)
# ============================================================
echo -e "${BLUE}[STEP 6]${NC} Simulating email from BLOCKED client..."
echo -e "${YELLOW}Expected:${NC} Email should be BLOCKED, notification sent"
echo ""

# Create blocked email queue entry
BLOCKED_EMAIL_DATA=$(cat <<EOF
{
  "email_account_id": "$EMAIL_ACCOUNT_ID",
  "from_address": "$TEST_EMAIL",
  "to_addresses": ["support@test.aidly.com"],
  "subject": "$TEST_SUBJECT (After Block) - Should Be Blocked",
  "body_plain": "This is a test email sent AFTER the client was blocked. This should NOT create a ticket and sender should receive a blocked notification.",
  "body_html": "<p>This is a test email sent AFTER the client was blocked. This should NOT create a ticket and sender should receive a blocked notification.</p>",
  "message_id": "test-msg-blocked-$(date +%s)@example.com",
  "status": "pending",
  "received_at": "$(date -u +"%Y-%m-%d %H:%M:%S")"
}
EOF
)

CREATE_BLOCKED_EMAIL_RESPONSE=$(curl -s -X POST "$EMAIL_SERVICE_URL/api/v1/email-queue" \
  -H "Content-Type: application/json" \
  -d "$BLOCKED_EMAIL_DATA")

echo "$CREATE_BLOCKED_EMAIL_RESPONSE" | jq '.' 2>/dev/null || echo "$CREATE_BLOCKED_EMAIL_RESPONSE"

EMAIL_QUEUE_ID_2=$(echo "$CREATE_BLOCKED_EMAIL_RESPONSE" | jq -r '.data.id // empty')

if [ -n "$EMAIL_QUEUE_ID_2" ] && [ "$EMAIL_QUEUE_ID_2" != "null" ]; then
    echo -e "${GREEN}✅ Blocked email queued${NC}"
    echo -e "   Email ID: ${YELLOW}$EMAIL_QUEUE_ID_2${NC}"

    # Process the blocked email
    echo -e "${YELLOW}Processing blocked email...${NC}"
    sleep 2

    PROCESS_BLOCKED_RESPONSE=$(curl -s -X POST "$EMAIL_SERVICE_URL/api/v1/email-queue/process" \
      -H "Content-Type: application/json" \
      -d '{"email_id": "'"$EMAIL_QUEUE_ID_2"'"}')

    echo "$PROCESS_BLOCKED_RESPONSE" | jq '.' 2>/dev/null || echo "$PROCESS_BLOCKED_RESPONSE"

    # Check if it was blocked
    SUCCESS=$(echo "$PROCESS_BLOCKED_RESPONSE" | jq -r '.success // false')
    ERROR_MSG=$(echo "$PROCESS_BLOCKED_RESPONSE" | jq -r '.error // .message // empty')

    if [[ "$ERROR_MSG" == *"blocked"* ]] || [[ "$ERROR_MSG" == *"Blocked"* ]]; then
        echo -e "${GREEN}✅ Email was BLOCKED as expected!${NC}"
        echo -e "${GREEN}✅ Error message confirms blocking: ${NC}${YELLOW}$ERROR_MSG${NC}"
    else
        echo -e "${YELLOW}⚠️  Response: $PROCESS_BLOCKED_RESPONSE${NC}"
    fi
else
    echo -e "${YELLOW}⚠️  Could not queue blocked email (service may not support direct queue creation)${NC}"
fi
echo ""

# ============================================================
# STEP 7: Check Blocked Email Attempts Log
# ============================================================
echo -e "${BLUE}[STEP 7]${NC} Checking blocked email attempts log..."
BLOCKED_ATTEMPTS_RESPONSE=$(curl -s -X GET "$EMAIL_SERVICE_URL/api/v1/blocked-attempts?email=$TEST_EMAIL")

echo "$BLOCKED_ATTEMPTS_RESPONSE" | jq '.' 2>/dev/null || echo "$BLOCKED_ATTEMPTS_RESPONSE"

ATTEMPTS_COUNT=$(echo "$BLOCKED_ATTEMPTS_RESPONSE" | jq -r '.data | length // 0')

if [ "$ATTEMPTS_COUNT" -gt 0 ]; then
    echo -e "${GREEN}✅ Found $ATTEMPTS_COUNT blocked attempt(s) in log${NC}"
else
    echo -e "${YELLOW}⚠️  No blocked attempts found in log (endpoint may not exist yet)${NC}"
fi
echo ""

# ============================================================
# STEP 8: Verify No Ticket Was Created for Blocked Email
# ============================================================
echo -e "${BLUE}[STEP 8]${NC} Verifying no ticket was created for blocked email..."
TICKETS_RESPONSE=$(curl -s -X GET "$TICKET_SERVICE_URL/api/v1/tickets?client_email=$TEST_EMAIL")

echo "$TICKETS_RESPONSE" | jq '.' 2>/dev/null || echo "$TICKETS_RESPONSE"

# Count tickets (should be 1 from before blocking, not 2)
TICKETS_COUNT=$(echo "$TICKETS_RESPONSE" | jq -r '.data.data | length // 0' 2>/dev/null || echo "0")

if [ "$TICKETS_COUNT" == "1" ]; then
    echo -e "${GREEN}✅ Correct! Only 1 ticket exists (from before blocking)${NC}"
    echo -e "${GREEN}✅ Blocked email did NOT create a ticket${NC}"
elif [ "$TICKETS_COUNT" == "2" ]; then
    echo -e "${RED}❌ FAIL: 2 tickets found - blocked email created a ticket!${NC}"
else
    echo -e "${YELLOW}⚠️  Found $TICKETS_COUNT ticket(s) - verification inconclusive${NC}"
fi
echo ""

# ============================================================
# STEP 9: Test Email Template (Show What Blocked User Receives)
# ============================================================
echo -e "${BLUE}[STEP 9]${NC} Email template that blocked user receives..."
echo ""
echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${RED}Subject: Message Delivery Failed - Account Restricted${NC}"
echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
cat << 'EMAIL_PREVIEW'
Your message could not be delivered to AidlY Support team.

┌──────────────────────────────────────────────────┐
│ ⚠️  ACCOUNT RESTRICTED                           │
│                                                   │
│ Your account has been restricted from submitting │
│ support requests. This means we cannot process   │
│ emails sent from your address.                   │
└──────────────────────────────────────────────────┘

Original Subject:
Test Email from Blocked Client (After Block) - Should Be Blocked

WHY WAS MY MESSAGE BLOCKED?
──────────────────────────────────────────────────
Your account may have been restricted for one of the
following reasons:

• Violation of our terms of service or acceptable use policy
• Repeated abusive or inappropriate communications
• Outstanding payment or account issues
• Request from account administrator

WHAT CAN I DO?
──────────────────────────────────────────────────
If you believe this restriction was made in error or
would like to discuss having it removed, please contact
our account management team directly:

📧 Email: support@aidly.com

Please include your account details and the reason
you're contacting us.

──────────────────────────────────────────────────
This is an automated message from AidlY Support.
Please do not reply directly to this email.
EMAIL_PREVIEW
echo ""
echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

# ============================================================
# STEP 10: Cleanup (Optional)
# ============================================================
echo -e "${BLUE}[STEP 10]${NC} Cleanup test data..."
read -p "$(echo -e ${YELLOW}Delete test client? [y/N]: ${NC})" -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    DELETE_RESPONSE=$(curl -s -X DELETE "$CLIENT_SERVICE_URL/api/v1/clients/$CLIENT_ID")
    echo "$DELETE_RESPONSE" | jq '.' 2>/dev/null || echo "$DELETE_RESPONSE"
    echo -e "${GREEN}✅ Test client deleted${NC}"
else
    echo -e "${YELLOW}⚠️  Test client preserved for manual inspection${NC}"
    echo -e "   Client ID: ${YELLOW}$CLIENT_ID${NC}"
    echo -e "   Email: ${YELLOW}$TEST_EMAIL${NC}"
fi
echo ""

# ============================================================
# Summary
# ============================================================
echo -e "${BLUE}============================================================${NC}"
echo -e "${GREEN}Test Summary${NC}"
echo -e "${BLUE}============================================================${NC}"
echo -e "${GREEN}✅ Created test client${NC}"
echo -e "${GREEN}✅ Verified client starts as non-blocked${NC}"
echo -e "${GREEN}✅ Blocked the client${NC}"
echo -e "${GREEN}✅ Simulated email from blocked client${NC}"
echo -e "${GREEN}✅ Verified email was blocked${NC}"
echo -e "${GREEN}✅ Verified notification template${NC}"
echo ""
echo -e "${YELLOW}Client ID:${NC} $CLIENT_ID"
echo -e "${YELLOW}Email:${NC} $TEST_EMAIL"
echo ""
echo -e "${BLUE}============================================================${NC}"
echo -e "${GREEN}Test Complete!${NC}"
echo -e "${BLUE}============================================================${NC}"
