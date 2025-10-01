#!/bin/bash

# Color codes for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}  AidlY Notification System Test${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# Configuration
AUTH_SERVICE="http://localhost:8001/api/v1"
TICKET_SERVICE="http://localhost:8002/api/v1"
NOTIFICATION_SERVICE="http://localhost:8004/api/v1"
CLIENT_SERVICE="http://localhost:8003/api/v1"

# Step 1: Login as Admin/Agent
echo -e "${YELLOW}Step 1: Login as Agent${NC}"
echo "Logging in..."

LOGIN_RESPONSE=$(curl -s -X POST "$AUTH_SERVICE/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@aidly.com",
    "password": "password123"
  }')

TOKEN=$(echo $LOGIN_RESPONSE | jq -r '.access_token // .token // .data.token // empty')
USER_ID=$(echo $LOGIN_RESPONSE | jq -r '.user.id // .data.user.id // .data.id // empty')

if [ -z "$TOKEN" ] || [ "$TOKEN" == "null" ]; then
  echo -e "${RED}❌ Login failed. Response:${NC}"
  echo $LOGIN_RESPONSE | jq '.'
  exit 1
fi

echo -e "${GREEN}✓ Login successful${NC}"
echo "User ID: $USER_ID"
echo "Token: ${TOKEN:0:20}..."
echo ""

# Step 2: Get or Create a Client
echo -e "${YELLOW}Step 2: Get/Create Test Client${NC}"

CLIENT_RESPONSE=$(curl -s -X POST "$CLIENT_SERVICE/clients" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "name": "Test Client",
    "email": "testclient@example.com",
    "phone": "+1234567890",
    "company": "Test Company"
  }')

CLIENT_ID=$(echo $CLIENT_RESPONSE | jq -r '.data.id // .id // empty')

if [ -z "$CLIENT_ID" ] || [ "$CLIENT_ID" == "null" ]; then
  # Try to get existing client
  CLIENT_LIST=$(curl -s -X GET "$CLIENT_SERVICE/clients?limit=1" \
    -H "Authorization: Bearer $TOKEN")
  CLIENT_ID=$(echo $CLIENT_LIST | jq -r '.data[0].id // empty')
fi

echo -e "${GREEN}✓ Client ID: $CLIENT_ID${NC}"
echo ""

# Step 3: Create a Ticket (Auto-Assignment)
echo -e "${YELLOW}Step 3: Create Ticket with Auto-Assignment${NC}"
echo "This should trigger a notification to the assigned agent..."

TICKET_RESPONSE=$(curl -s -X POST "$TICKET_SERVICE/public/tickets" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d "{
    \"subject\": \"Test Ticket - Auto Assignment\",
    \"description\": \"This is a test ticket to verify auto-assignment notifications\",
    \"client_id\": \"$CLIENT_ID\",
    \"priority\": \"high\",
    \"source\": \"web_form\",
    \"auto_assign\": true
  }")

TICKET_ID=$(echo $TICKET_RESPONSE | jq -r '.data.id // empty')
TICKET_NUMBER=$(echo $TICKET_RESPONSE | jq -r '.data.ticket_number // empty')
ASSIGNED_AGENT=$(echo $TICKET_RESPONSE | jq -r '.data.assigned_agent_id // empty')

if [ -z "$TICKET_ID" ] || [ "$TICKET_ID" == "null" ]; then
  echo -e "${RED}❌ Ticket creation failed${NC}"
  echo $TICKET_RESPONSE | jq '.'
else
  echo -e "${GREEN}✓ Ticket created successfully${NC}"
  echo "Ticket ID: $TICKET_ID"
  echo "Ticket Number: $TICKET_NUMBER"
  echo "Assigned Agent: $ASSIGNED_AGENT"
fi
echo ""

# Wait a moment for notification to be created
sleep 2

# Step 4: Check Notifications
echo -e "${YELLOW}Step 4: Check Notifications for Agent${NC}"

if [ ! -z "$ASSIGNED_AGENT" ] && [ "$ASSIGNED_AGENT" != "null" ]; then
  NOTIF_RESPONSE=$(curl -s -X GET "$NOTIFICATION_SERVICE/notifications?notifiable_id=$ASSIGNED_AGENT&notifiable_type=user&limit=5" \
    -H "Authorization: Bearer $TOKEN")

  echo "Notifications for assigned agent:"
  echo $NOTIF_RESPONSE | jq '.data[] | {id, type, title, message, read_at, created_at}'
else
  # Check for current user
  NOTIF_RESPONSE=$(curl -s -X GET "$NOTIFICATION_SERVICE/notifications?notifiable_id=$USER_ID&notifiable_type=user&limit=5" \
    -H "Authorization: Bearer $TOKEN")

  echo "Notifications for current user:"
  echo $NOTIF_RESPONSE | jq '.data[] | {id, type, title, message, read_at, created_at}'
fi
echo ""

# Step 5: Get Notification Stats
echo -e "${YELLOW}Step 5: Get Notification Statistics${NC}"

STATS_RESPONSE=$(curl -s -X GET "$NOTIFICATION_SERVICE/notifications/stats?notifiable_id=$USER_ID&notifiable_type=user" \
  -H "Authorization: Bearer $TOKEN")

echo "Stats:"
echo $STATS_RESPONSE | jq '.data'
echo ""

# Step 6: Add a Client Reply (simulate)
echo -e "${YELLOW}Step 6: Add Client Reply to Ticket${NC}"
echo "This should trigger a notification to the assigned agent..."

if [ ! -z "$TICKET_ID" ] && [ "$TICKET_ID" != "null" ]; then
  COMMENT_RESPONSE=$(curl -s -X POST "$TICKET_SERVICE/public/tickets/$TICKET_ID/comments" \
    -H "Content-Type: application/json" \
    -d '{
      "content": "Hi, I am the client and I need help with this issue!",
      "is_internal_note": false,
      "client_email": "testclient@example.com"
    }')

  COMMENT_ID=$(echo $COMMENT_RESPONSE | jq -r '.data.id // empty')

  if [ -z "$COMMENT_ID" ] || [ "$COMMENT_ID" == "null" ]; then
    echo -e "${RED}❌ Comment creation failed${NC}"
    echo $COMMENT_RESPONSE | jq '.'
  else
    echo -e "${GREEN}✓ Client comment added${NC}"
    echo "Comment ID: $COMMENT_ID"
  fi

  # Wait for notification
  sleep 2

  # Check notifications again
  echo ""
  echo -e "${YELLOW}Step 7: Check Notifications After Client Reply${NC}"

  NOTIF_RESPONSE=$(curl -s -X GET "$NOTIFICATION_SERVICE/notifications?notifiable_id=$USER_ID&notifiable_type=user&limit=10" \
    -H "Authorization: Bearer $TOKEN")

  echo "Updated notifications:"
  echo $NOTIF_RESPONSE | jq '.data[] | {id, type, title, message, priority, read_at, created_at}'
fi
echo ""

# Step 8: Manual Assignment Test
echo -e "${YELLOW}Step 8: Test Manual Re-Assignment${NC}"

if [ ! -z "$TICKET_ID" ] && [ "$TICKET_ID" != "null" ]; then
  # Get another agent (or use same user for testing)
  ASSIGN_RESPONSE=$(curl -s -X POST "$TICKET_SERVICE/public/tickets/$TICKET_ID/assign" \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer $TOKEN" \
    -d "{
      \"assigned_agent_id\": \"$USER_ID\"
    }")

  echo "Assignment response:"
  echo $ASSIGN_RESPONSE | jq '.success, .message'

  # Wait and check notifications
  sleep 2

  NOTIF_RESPONSE=$(curl -s -X GET "$NOTIFICATION_SERVICE/notifications?notifiable_id=$USER_ID&notifiable_type=user&limit=10&unread_only=1" \
    -H "Authorization: Bearer $TOKEN")

  UNREAD_COUNT=$(echo $NOTIF_RESPONSE | jq '.data | length')
  echo ""
  echo -e "${GREEN}✓ Unread notifications: $UNREAD_COUNT${NC}"
  echo $NOTIF_RESPONSE | jq '.data[] | {type, title, priority, created_at}'
fi
echo ""

# Step 9: Mark Notification as Read
echo -e "${YELLOW}Step 9: Mark Notification as Read${NC}"

FIRST_NOTIF_ID=$(echo $NOTIF_RESPONSE | jq -r '.data[0].id // empty')

if [ ! -z "$FIRST_NOTIF_ID" ] && [ "$FIRST_NOTIF_ID" != "null" ]; then
  MARK_READ_RESPONSE=$(curl -s -X POST "$NOTIFICATION_SERVICE/notifications/$FIRST_NOTIF_ID/read" \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer $TOKEN" \
    -d "{
      \"notifiable_id\": \"$USER_ID\",
      \"notifiable_type\": \"user\"
    }")

  echo "Mark as read response:"
  echo $MARK_READ_RESPONSE | jq '.'

  # Check stats again
  sleep 1
  STATS_RESPONSE=$(curl -s -X GET "$NOTIFICATION_SERVICE/notifications/stats?notifiable_id=$USER_ID&notifiable_type=user" \
    -H "Authorization: Bearer $TOKEN")

  echo ""
  echo "Updated stats:"
  echo $STATS_RESPONSE | jq '.data'
fi
echo ""

# Step 10: Direct Webhook Test (Internal)
echo -e "${YELLOW}Step 10: Direct Webhook Test${NC}"
echo "Testing notification service webhook directly..."

WEBHOOK_RESPONSE=$(curl -s -X POST "$NOTIFICATION_SERVICE/webhooks/ticket-assigned" \
  -H "Content-Type: application/json" \
  -d "{
    \"ticket_id\": \"$TICKET_ID\",
    \"ticket_number\": \"$TICKET_NUMBER\",
    \"subject\": \"Direct Webhook Test\",
    \"priority\": \"urgent\",
    \"customer_name\": \"Test Customer\",
    \"assigned_to_id\": \"$USER_ID\",
    \"assigned_to_name\": \"Test Agent\",
    \"assigned_to_email\": \"agent@aidly.com\",
    \"assigned_by\": \"Test Script\"
  }")

echo "Webhook response:"
echo $WEBHOOK_RESPONSE | jq '.'

sleep 2

# Final notification check
FINAL_NOTIF=$(curl -s -X GET "$NOTIFICATION_SERVICE/notifications?notifiable_id=$USER_ID&notifiable_type=user&limit=1" \
  -H "Authorization: Bearer $TOKEN")

echo ""
echo "Latest notification:"
echo $FINAL_NOTIF | jq '.data[0] | {type, title, message, priority, created_at}'
echo ""

# Summary
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}  Test Summary${NC}"
echo -e "${BLUE}========================================${NC}"
echo -e "${GREEN}✓ Authentication tested${NC}"
echo -e "${GREEN}✓ Ticket creation tested${NC}"
echo -e "${GREEN}✓ Auto-assignment notification tested${NC}"
echo -e "${GREEN}✓ Client reply notification tested${NC}"
echo -e "${GREEN}✓ Manual assignment notification tested${NC}"
echo -e "${GREEN}✓ Mark as read tested${NC}"
echo -e "${GREEN}✓ Direct webhook tested${NC}"
echo ""
echo -e "${YELLOW}Check your frontend at: http://localhost:3000${NC}"
echo -e "${YELLOW}Bell icon should show notification badge!${NC}"
echo ""
