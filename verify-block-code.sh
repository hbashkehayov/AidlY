#!/bin/bash

echo "=== CODE VERIFICATION TEST ==="
echo ""

# Check if the blocking code is in EmailToTicketService
echo "[1/3] Checking if block check exists in createTicketFromEmail()..."
if grep -q "is_blocked.*true" /root/AidlY/services/email-service/app/Services/EmailToTicketService.php; then
    echo "✓ Block check code FOUND in EmailToTicketService"
    echo ""
    echo "Code snippet:"
    grep -A 5 "is_blocked.*true" /root/AidlY/services/email-service/app/Services/EmailToTicketService.php | head -15
else
    echo "✗ Block check code NOT FOUND"
    exit 1
fi
echo ""

# Check if notification method exists
echo "[2/3] Checking if sendBlockedSenderNotification() method exists..."
if grep -q "function sendBlockedSenderNotification" /root/AidlY/services/email-service/app/Services/EmailToTicketService.php; then
    echo "✓ sendBlockedSenderNotification() method FOUND"
else
    echo "✗ Notification method NOT FOUND"
    exit 1
fi
echo ""

# Check if log method exists
echo "[3/3] Checking if logBlockedEmailAttempt() method exists..."
if grep -q "function logBlockedEmailAttempt" /root/AidlY/services/email-service/app/Services/EmailToTicketService.php; then
    echo "✓ logBlockedEmailAttempt() method FOUND"
else
    echo "✗ Log method NOT FOUND"
    exit 1
fi
echo ""

echo "=== ALL CODE CHECKS PASSED ==="
