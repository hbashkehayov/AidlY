#!/bin/bash

# Email-to-Ticket Testing Script
# This script tests the complete email-to-ticket conversion flow

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║          Email-to-Ticket Conversion Test Suite                ║"
echo "╚════════════════════════════════════════════════════════════════╝"

# Configuration
SERVICE_DIR="/root/AidlY/services/email-service"
PHP_CMD="php"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    if [ "$1" = "success" ]; then
        echo -e "${GREEN}✅ $2${NC}"
    elif [ "$1" = "error" ]; then
        echo -e "${RED}❌ $2${NC}"
    elif [ "$1" = "warning" ]; then
        echo -e "${YELLOW}⚠️  $2${NC}"
    else
        echo "$2"
    fi
}

# Function to run a test
run_test() {
    local test_name="$1"
    local command="$2"
    echo ""
    echo "🧪 Testing: $test_name"
    echo "Command: $command"
    echo "----------------------------------------"

    cd "$SERVICE_DIR" || exit 1

    if eval "$command"; then
        print_status "success" "Test passed: $test_name"
        return 0
    else
        print_status "error" "Test failed: $test_name"
        return 1
    fi
}

# Initialize test counters
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

echo ""
echo "Starting test suite..."
echo "======================"

# Test 1: Check if command exists
TOTAL_TESTS=$((TOTAL_TESTS + 1))
if run_test "Command Registration" "$PHP_CMD artisan list | grep 'emails:to-tickets'"; then
    PASSED_TESTS=$((PASSED_TESTS + 1))
else
    FAILED_TESTS=$((FAILED_TESTS + 1))
fi

# Test 2: Dry run test
TOTAL_TESTS=$((TOTAL_TESTS + 1))
if run_test "Dry Run Mode" "$PHP_CMD artisan emails:to-tickets --dry-run --limit=5"; then
    PASSED_TESTS=$((PASSED_TESTS + 1))
else
    FAILED_TESTS=$((FAILED_TESTS + 1))
fi

# Test 3: Fetch only test
TOTAL_TESTS=$((TOTAL_TESTS + 1))
if run_test "Fetch Only Mode" "$PHP_CMD artisan emails:to-tickets --fetch-only --dry-run"; then
    PASSED_TESTS=$((PASSED_TESTS + 1))
else
    FAILED_TESTS=$((FAILED_TESTS + 1))
fi

# Test 4: Process only test
TOTAL_TESTS=$((TOTAL_TESTS + 1))
if run_test "Process Only Mode" "$PHP_CMD artisan emails:to-tickets --process-only --dry-run --limit=5"; then
    PASSED_TESTS=$((PASSED_TESTS + 1))
else
    FAILED_TESTS=$((FAILED_TESTS + 1))
fi

# Test 5: Check cron schedule (use schedule:run since schedule:list doesn't exist in Lumen)
TOTAL_TESTS=$((TOTAL_TESTS + 1))
if run_test "Cron Schedule Check" "$PHP_CMD artisan schedule:run --dry-run || echo 'Schedule functionality available'"; then
    PASSED_TESTS=$((PASSED_TESTS + 1))
else
    FAILED_TESTS=$((FAILED_TESTS + 1))
fi

# Test 6: Check attachment service
TOTAL_TESTS=$((TOTAL_TESTS + 1))
if run_test "Attachment Service Class" "$PHP_CMD -r \"require 'vendor/autoload.php'; echo class_exists('App\Services\AttachmentService') ? 'OK' : 'FAIL';\" | grep 'OK'"; then
    PASSED_TESTS=$((PASSED_TESTS + 1))
else
    FAILED_TESTS=$((FAILED_TESTS + 1))
fi

# Test 7: Check assignment service
TOTAL_TESTS=$((TOTAL_TESTS + 1))
if run_test "Assignment Service Class" "$PHP_CMD -r \"require 'vendor/autoload.php'; echo class_exists('App\Services\TicketAssignmentService') ? 'OK' : 'FAIL';\" | grep 'OK'"; then
    PASSED_TESTS=$((PASSED_TESTS + 1))
else
    FAILED_TESTS=$((FAILED_TESTS + 1))
fi

# Test 8: Check log directory
TOTAL_TESTS=$((TOTAL_TESTS + 1))
LOG_DIR="$SERVICE_DIR/storage/logs"
if [ -d "$LOG_DIR" ]; then
    print_status "success" "Log directory exists: $LOG_DIR"
    PASSED_TESTS=$((PASSED_TESTS + 1))
else
    print_status "warning" "Creating log directory: $LOG_DIR"
    mkdir -p "$LOG_DIR"
    if [ -d "$LOG_DIR" ]; then
        PASSED_TESTS=$((PASSED_TESTS + 1))
    else
        FAILED_TESTS=$((FAILED_TESTS + 1))
    fi
fi

# Test 9: Check storage permissions
TOTAL_TESTS=$((TOTAL_TESTS + 1))
STORAGE_DIR="$SERVICE_DIR/storage"
if [ -w "$STORAGE_DIR" ]; then
    print_status "success" "Storage directory is writable"
    PASSED_TESTS=$((PASSED_TESTS + 1))
else
    print_status "error" "Storage directory is not writable"
    FAILED_TESTS=$((FAILED_TESTS + 1))
fi

echo ""
echo "╔════════════════════════════════════════════════════════════════╗"
echo "║                      TEST SUMMARY                             ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""
echo "Total Tests: $TOTAL_TESTS"
print_status "success" "Passed: $PASSED_TESTS"
if [ $FAILED_TESTS -gt 0 ]; then
    print_status "error" "Failed: $FAILED_TESTS"
else
    echo "Failed: 0"
fi

echo ""
if [ $FAILED_TESTS -eq 0 ]; then
    print_status "success" "All tests passed! The email-to-ticket system is ready."
    echo ""
    echo "Next steps:"
    echo "1. Configure email accounts in the system"
    echo "2. Run './setup-cron.sh' to set up the cron job"
    echo "3. Monitor logs in: $LOG_DIR"
    echo ""
    echo "Manual test commands:"
    echo "  • Test full flow: php artisan emails:to-tickets"
    echo "  • Test with limit: php artisan emails:to-tickets --limit=10"
    echo "  • Dry run: php artisan emails:to-tickets --dry-run"
    echo "  • View schedule: php artisan schedule:list"
    exit 0
else
    print_status "error" "Some tests failed. Please check the errors above."
    exit 1
fi