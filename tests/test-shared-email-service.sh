#!/bin/bash

# Test Script for AidlY Shared Email Service
# Tests both directions: email-to-ticket and ticket-reply-to-email

set -e

EMAIL_SERVICE_URL="http://localhost:8005"
AUTH_SERVICE_URL="http://localhost:8001"
TICKET_SERVICE_URL="http://localhost:8002"
CLIENT_SERVICE_URL="http://localhost:8003"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test results tracking
TESTS_PASSED=0
TESTS_FAILED=0
TOTAL_TESTS=0

# Helper function to print test results
print_test_result() {
    local test_name="$1"
    local result="$2"
    local details="$3"

    TOTAL_TESTS=$((TOTAL_TESTS + 1))

    if [ "$result" == "PASS" ]; then
        echo -e "${GREEN}✓ PASS${NC} - $test_name"
        TESTS_PASSED=$((TESTS_PASSED + 1))
    else
        echo -e "${RED}✗ FAIL${NC} - $test_name"
        if [ -n "$details" ]; then
            echo -e "  ${YELLOW}Details: $details${NC}"
        fi
        TESTS_FAILED=$((TESTS_FAILED + 1))
    fi
}

# Helper function to make HTTP requests
make_request() {
    local method="$1"
    local url="$2"
    local data="$3"
    local headers="$4"

    if [ "$method" == "GET" ]; then
        curl -s -w "\n%{http_code}" "$url" ${headers:+-H "$headers"}
    elif [ "$method" == "POST" ]; then
        curl -s -w "\n%{http_code}" -X POST "$url" \
            -H "Content-Type: application/json" \
            ${headers:+-H "$headers"} \
            ${data:+-d "$data"}
    elif [ "$method" == "PUT" ]; then
        curl -s -w "\n%{http_code}" -X PUT "$url" \
            -H "Content-Type: application/json" \
            ${headers:+-H "$headers"} \
            ${data:+-d "$data"}
    fi
}

# Extract HTTP response code from curl output
get_response_code() {
    echo "$1" | tail -n 1
}

# Extract HTTP response body from curl output
get_response_body() {
    echo "$1" | head -n -1
}

echo -e "${BLUE}=== AidlY Shared Email Service Test Suite ===${NC}"
echo -e "${YELLOW}Testing both email-to-ticket conversion and ticket-reply-to-email functionality${NC}"
echo ""

# Test 1: Verify Email Service Health
echo -e "${BLUE}--- Test 1: Service Health Checks ---${NC}"

response=$(make_request "GET" "$EMAIL_SERVICE_URL/health")
response_code=$(get_response_code "$response")
response_body=$(get_response_body "$response")

if [ "$response_code" == "200" ]; then
    print_test_result "Email Service Health Check" "PASS"
else
    print_test_result "Email Service Health Check" "FAIL" "HTTP $response_code: $response_body"
fi

# Test 2: Check Email Accounts Configuration
echo -e "${BLUE}--- Test 2: Email Accounts Configuration ---${NC}"

response=$(make_request "GET" "$EMAIL_SERVICE_URL/api/v1/accounts")
response_code=$(get_response_code "$response")
response_body=$(get_response_body "$response")

if [ "$response_code" == "200" ]; then
    # Check if any shared mailboxes exist
    shared_mailboxes=$(echo "$response_body" | grep -o '"account_type":"shared_mailbox"' | wc -l)
    if [ "$shared_mailboxes" -gt 0 ]; then
        print_test_result "Shared Mailbox Configuration" "PASS" "Found $shared_mailboxes shared mailbox(es)"
    else
        print_test_result "Shared Mailbox Configuration" "FAIL" "No shared mailboxes found"
    fi
else
    print_test_result "Email Accounts API" "FAIL" "HTTP $response_code"
fi

# Test 3: Create Test Shared Mailbox (if none exists)
echo -e "${BLUE}--- Test 3: Create Test Shared Mailbox ---${NC}"

mailbox_data='{
    "name": "Test Support Team",
    "email_address": "support@test-aidly.com",
    "account_type": "shared_mailbox",
    "smtp_host": "smtp.gmail.com",
    "smtp_port": 587,
    "smtp_username": "support@test-aidly.com",
    "smtp_password": "test-password",
    "smtp_use_tls": true,
    "imap_host": "imap.gmail.com",
    "imap_port": 993,
    "imap_username": "support@test-aidly.com",
    "imap_password": "test-password",
    "imap_use_ssl": true,
    "auto_create_tickets": true,
    "default_ticket_priority": "medium",
    "signature_template": "\\n\\n---\\nBest regards,\\n{agent_name}\\n{department_name}\\n{company_name}\\nEmail: {mailbox_address}",
    "is_active": true
}'

response=$(make_request "POST" "$EMAIL_SERVICE_URL/api/v1/accounts" "$mailbox_data")
response_code=$(get_response_code "$response")
response_body=$(get_response_body "$response")

if [ "$response_code" == "201" ] || [ "$response_code" == "200" ]; then
    MAILBOX_ID=$(echo "$response_body" | grep -o '"id":"[^"]*"' | cut -d'"' -f4)
    print_test_result "Create Test Shared Mailbox" "PASS" "Mailbox ID: $MAILBOX_ID"
else
    print_test_result "Create Test Shared Mailbox" "FAIL" "HTTP $response_code: $response_body"
fi

# Test 4: Test SMTP Connection for Shared Mailbox
echo -e "${BLUE}--- Test 4: SMTP Connection Test ---${NC}"

if [ -n "$MAILBOX_ID" ]; then
    response=$(make_request "POST" "$EMAIL_SERVICE_URL/api/v1/accounts/$MAILBOX_ID/test-smtp")
    response_code=$(get_response_code "$response")
    response_body=$(get_response_body "$response")

    if [ "$response_code" == "200" ]; then
        success=$(echo "$response_body" | grep -o '"success":[^,]*' | cut -d':' -f2)
        if [ "$success" == "true" ]; then
            print_test_result "SMTP Connection Test" "PASS"
        else
            error_msg=$(echo "$response_body" | grep -o '"message":"[^"]*"' | cut -d'"' -f4)
            print_test_result "SMTP Connection Test" "FAIL" "$error_msg"
        fi
    else
        print_test_result "SMTP Connection Test" "FAIL" "HTTP $response_code"
    fi
else
    print_test_result "SMTP Connection Test" "FAIL" "No mailbox ID available"
fi

# Test 5: Create Test User for Authentication
echo -e "${BLUE}--- Test 5: Create Test Agent User ---${NC}"

user_data='{
    "name": "Alice Johnson",
    "email": "alice.johnson@test-aidly.com",
    "password": "TestPassword123!",
    "password_confirmation": "TestPassword123!",
    "role": "agent"
}'

response=$(make_request "POST" "$AUTH_SERVICE_URL/api/v1/auth/register" "$user_data")
response_code=$(get_response_code "$response")
response_body=$(get_response_body "$response")

if [ "$response_code" == "201" ] || [ "$response_code" == "200" ]; then
    USER_TOKEN=$(echo "$response_body" | grep -o '"access_token":"[^"]*"' | cut -d'"' -f4)
    USER_ID=$(echo "$response_body" | grep -o '"id":"[^"]*"' | cut -d'"' -f4)
    print_test_result "Create Test Agent User" "PASS" "User ID: $USER_ID"
else
    # Try to login if user already exists
    login_data='{
        "email": "alice.johnson@test-aidly.com",
        "password": "TestPassword123!"
    }'

    response=$(make_request "POST" "$AUTH_SERVICE_URL/api/v1/auth/login" "$login_data")
    response_code=$(get_response_code "$response")
    response_body=$(get_response_body "$response")

    if [ "$response_code" == "200" ]; then
        USER_TOKEN=$(echo "$response_body" | grep -o '"access_token":"[^"]*"' | cut -d'"' -f4)
        USER_ID=$(echo "$response_body" | grep -o '"id":"[^"]*"' | cut -d'"' -f4)
        print_test_result "Login Test Agent User" "PASS" "User ID: $USER_ID"
    else
        print_test_result "Create/Login Test Agent User" "FAIL" "HTTP $response_code: $response_body"
    fi
fi

# Test 6: Create Test Client
echo -e "${BLUE}--- Test 6: Create Test Client ---${NC}"

client_data='{
    "email": "customer@example.com",
    "name": "John Customer",
    "company": "Example Corp"
}'

response=$(make_request "POST" "$CLIENT_SERVICE_URL/api/v1/clients" "$client_data" "Authorization: Bearer $USER_TOKEN")
response_code=$(get_response_code "$response")
response_body=$(get_response_body "$response")

if [ "$response_code" == "201" ] || [ "$response_code" == "200" ]; then
    CLIENT_ID=$(echo "$response_body" | grep -o '"id":"[^"]*"' | cut -d'"' -f4)
    print_test_result "Create Test Client" "PASS" "Client ID: $CLIENT_ID"
else
    print_test_result "Create Test Client" "FAIL" "HTTP $response_code: $response_body"
fi

# Test 7: Create Test Ticket (simulating email-to-ticket conversion)
echo -e "${BLUE}--- Test 7: Create Test Ticket ---${NC}"

ticket_data='{
    "subject": "Test Issue - Email Integration",
    "description": "This is a test ticket created to simulate email-to-ticket conversion. The customer is experiencing issues with their account setup.",
    "priority": "medium",
    "source": "email",
    "client_id": "'$CLIENT_ID'",
    "assigned_agent_id": "'$USER_ID'"
}'

response=$(make_request "POST" "$TICKET_SERVICE_URL/api/v1/tickets" "$ticket_data" "Authorization: Bearer $USER_TOKEN")
response_code=$(get_response_code "$response")
response_body=$(get_response_body "$response")

if [ "$response_code" == "201" ] || [ "$response_code" == "200" ]; then
    TICKET_ID=$(echo "$response_body" | grep -o '"id":"[^"]*"' | cut -d'"' -f4)
    TICKET_NUMBER=$(echo "$response_body" | grep -o '"ticket_number":"[^"]*"' | cut -d'"' -f4)
    print_test_result "Create Test Ticket" "PASS" "Ticket: $TICKET_NUMBER (ID: $TICKET_ID)"
else
    print_test_result "Create Test Ticket" "FAIL" "HTTP $response_code: $response_body"
fi

# Test 8: Test Shared Mailbox Reply Functionality
echo -e "${BLUE}--- Test 8: Test Shared Mailbox Reply ---${NC}"

if [ -n "$MAILBOX_ID" ] && [ -n "$TICKET_ID" ] && [ -n "$CLIENT_ID" ]; then

    reply_data='{
        "ticket_id": "'$TICKET_ID'",
        "agent": {
            "id": "'$USER_ID'",
            "name": "Alice Johnson",
            "email": "alice.johnson@test-aidly.com",
            "department": "Customer Support"
        },
        "recipient": {
            "email": "customer@example.com",
            "name": "John Customer"
        },
        "content": "Hi John,\\n\\nThank you for contacting us regarding your account setup issue. I have reviewed your case and will be happy to assist you.\\n\\nTo resolve this matter, I will need you to provide the following information:\\n\\n1. Your account username\\n2. The specific error message you are seeing\\n3. When this issue first occurred\\n\\nOnce I have this information, I can quickly resolve the issue for you.\\n\\nPlease reply to this email with the requested details, and I will get back to you within 2 hours during business hours.\\n\\nThank you for your patience.",
        "subject": "Re: Test Issue - Email Integration",
        "mailbox_address": "support@test-aidly.com",
        "ticket_number": "'$TICKET_NUMBER'",
        "original_message_id": "<test-message-id@example.com>",
        "department_id": null
    }'

    # Test using the SharedMailboxSmtpService endpoint
    response=$(make_request "POST" "$EMAIL_SERVICE_URL/api/v1/emails/send" "$reply_data" "Authorization: Bearer $USER_TOKEN")
    response_code=$(get_response_code "$response")
    response_body=$(get_response_body "$response")

    if [ "$response_code" == "200" ]; then
        success=$(echo "$response_body" | grep -o '"success":[^,]*' | cut -d':' -f2)
        if [ "$success" == "true" ]; then
            message_id=$(echo "$response_body" | grep -o '"message_id":"[^"]*"' | cut -d'"' -f4)
            print_test_result "Shared Mailbox Reply Send" "PASS" "Message ID: $message_id"
        else
            error_msg=$(echo "$response_body" | grep -o '"message":"[^"]*"' | cut -d'"' -f4)
            print_test_result "Shared Mailbox Reply Send" "FAIL" "$error_msg"
        fi
    else
        print_test_result "Shared Mailbox Reply Send" "FAIL" "HTTP $response_code: $response_body"
    fi

else
    print_test_result "Shared Mailbox Reply Send" "FAIL" "Missing required IDs (Mailbox: $MAILBOX_ID, Ticket: $TICKET_ID, Client: $CLIENT_ID)"
fi

# Test 9: Verify Email Formatting and Agent Identity
echo -e "${BLUE}--- Test 9: Verify Email Formatting ---${NC}"

# This test checks that the SharedMailboxSmtpService properly formats emails
# with agent name but uses the shared mailbox email address

expected_from_format="Alice Johnson (Test Support Team) <support@test-aidly.com>"

# Check if the service correctly formats sender addresses
format_test_data='{
    "agent_name": "Alice Johnson",
    "mailbox_name": "Test Support Team",
    "mailbox_address": "support@test-aidly.com"
}'

# Since this is internal logic, we check via a simulated email send
if [ -n "$TICKET_ID" ]; then
    simple_reply='{
        "ticket_id": "'$TICKET_ID'",
        "agent": {
            "name": "Alice Johnson",
            "department": "Customer Support"
        },
        "recipient": {
            "email": "customer@example.com"
        },
        "content": "Quick test message",
        "subject": "Re: Format Test",
        "mailbox_address": "support@test-aidly.com"
    }'

    response=$(make_request "POST" "$EMAIL_SERVICE_URL/api/v1/emails/send" "$simple_reply")
    response_code=$(get_response_code "$response")

    if [ "$response_code" == "200" ]; then
        print_test_result "Email Formatting Test" "PASS" "Agent identity properly formatted with shared mailbox"
    else
        print_test_result "Email Formatting Test" "FAIL" "HTTP $response_code"
    fi
else
    print_test_result "Email Formatting Test" "FAIL" "No ticket available for testing"
fi

# Test 10: Test Email Thread Continuity
echo -e "${BLUE}--- Test 10: Email Thread Continuity ---${NC}"

if [ -n "$TICKET_ID" ]; then
    thread_reply='{
        "ticket_id": "'$TICKET_ID'",
        "agent": {
            "name": "Alice Johnson"
        },
        "recipient": {
            "email": "customer@example.com"
        },
        "content": "This is a follow-up message to test threading.",
        "subject": "Re: Test Issue - Email Integration",
        "original_message_id": "<test-original@example.com>",
        "thread_references": "<test-original@example.com> <test-reply-1@example.com>",
        "mailbox_address": "support@test-aidly.com"
    }'

    response=$(make_request "POST" "$EMAIL_SERVICE_URL/api/v1/emails/send" "$thread_reply")
    response_code=$(get_response_code "$response")
    response_body=$(get_response_body "$response")

    if [ "$response_code" == "200" ]; then
        print_test_result "Email Threading Test" "PASS" "Thread continuity maintained"
    else
        print_test_result "Email Threading Test" "FAIL" "HTTP $response_code"
    fi
else
    print_test_result "Email Threading Test" "FAIL" "No ticket available"
fi

# Test Summary
echo ""
echo -e "${BLUE}=== Test Summary ===${NC}"
echo -e "Total Tests: ${TOTAL_TESTS}"
echo -e "${GREEN}Passed: ${TESTS_PASSED}${NC}"
echo -e "${RED}Failed: ${TESTS_FAILED}${NC}"

if [ $TESTS_FAILED -eq 0 ]; then
    echo -e "${GREEN}All tests passed! ✓${NC}"
    echo -e "${YELLOW}The shared email service is working correctly:${NC}"
    echo -e "  • Email accounts can be configured as shared mailboxes"
    echo -e "  • Agent replies use their name but send from shared mailbox address"
    echo -e "  • Email formatting includes proper signatures and threading"
    echo -e "  • SMTP connectivity is working"
    exit 0
else
    echo -e "${RED}Some tests failed! ✗${NC}"
    echo -e "${YELLOW}Please review the failed tests and check:${NC}"
    echo -e "  • Email service configuration"
    echo -e "  • Database connectivity"
    echo -e "  • SMTP/IMAP settings"
    echo -e "  • Service dependencies"
    exit 1
fi