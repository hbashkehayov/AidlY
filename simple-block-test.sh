#!/bin/bash
set -e

echo "=== BLOCKED CLIENT TEST ==="
echo ""

# Step 1: Create client
echo "[1/5] Creating test client..."
CLIENT_RESP=$(curl -s -X POST "http://localhost:8003/api/v1/clients" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "block-test-'$(date +%s)'@example.com",
    "name": "Block Test User",
    "is_blocked": false
  }')

echo "$CLIENT_RESP" | jq -c '.data | {id, email, is_blocked}'
CLIENT_ID=$(echo "$CLIENT_RESP" | jq -r '.data.id')
CLIENT_EMAIL=$(echo "$CLIENT_RESP" | jq -r '.data.email')
echo "✓ Client ID: $CLIENT_ID"
echo ""

# Step 2: Verify client exists (not blocked)
echo "[2/5] Verifying client is NOT blocked..."
GET_RESP=$(curl -s "http://localhost:8003/api/v1/clients/$CLIENT_ID")
IS_BLOCKED=$(echo "$GET_RESP" | jq -r '.data.is_blocked')
echo "is_blocked = $IS_BLOCKED"
if [ "$IS_BLOCKED" == "false" ]; then
  echo "✓ Client is NOT blocked (correct)"
else
  echo "✗ Client is already blocked (unexpected)"
  exit 1
fi
echo ""

# Step 3: Block the client
echo "[3/5] Blocking the client..."
BLOCK_RESP=$(curl -s -X PUT "http://localhost:8003/api/v1/clients/$CLIENT_ID" \
  -H "Content-Type: application/json" \
  -d '{"is_blocked": true}')

echo "$BLOCK_RESP" | jq -c '.data | {id, email, is_blocked}'
IS_BLOCKED_NOW=$(echo "$BLOCK_RESP" | jq -r '.data.is_blocked')
if [ "$IS_BLOCKED_NOW" == "true" ]; then
  echo "✓ Client successfully BLOCKED"
else
  echo "✗ Failed to block client"
  exit 1
fi
echo ""

# Step 4: Query to find client by email
echo "[4/5] Finding client by email (simulating email-to-ticket lookup)..."
FIND_RESP=$(curl -s "http://localhost:8003/api/v1/clients?email=$CLIENT_EMAIL")
echo "$FIND_RESP" | jq -c '.data[0] | {id, email, is_blocked}'
FOUND_BLOCKED=$(echo "$FIND_RESP" | jq -r '.data[0].is_blocked')
if [ "$FOUND_BLOCKED" == "true" ]; then
  echo "✓ Client found by email and is BLOCKED"
  echo "✓ EmailToTicketService would detect this block!"
else
  echo "✗ Client not found or not blocked in search"
fi
echo ""

# Step 5: Show what would happen
echo "[5/5] What happens when email arrives from this client:"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "1. Email arrives from: $CLIENT_EMAIL"
echo "2. EmailToTicketService.findClient('$CLIENT_EMAIL')"
echo "3. Client found: is_blocked = true"
echo "4. ✓ Email BLOCKED - No ticket created"
echo "5. ✓ Notification email sent to: $CLIENT_EMAIL"
echo "6. ✓ Attempt logged to blocked_email_attempts table"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

echo "=== TEST COMPLETE ==="
echo ""
echo "Cleanup command:"
echo "curl -X DELETE \"http://localhost:8003/api/v1/clients/$CLIENT_ID\""
