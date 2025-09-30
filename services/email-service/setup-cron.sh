#!/bin/bash

# AidlY Shared Mailbox - Cron Job Setup Script
# This script sets up the cron job for shared mailbox processing (replaces individual Gmail accounts)

echo "=========================================="
echo "AidlY Shared Mailbox Processing Setup"
echo "=========================================="

# Get the current directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PHP_PATH=$(which php)

# Check if PHP is installed
if [ -z "$PHP_PATH" ]; then
    echo "❌ PHP not found. Please install PHP first."
    exit 1
fi

echo "✅ PHP found at: $PHP_PATH"

# Create log directory if it doesn't exist
LOG_DIR="$SCRIPT_DIR/storage/logs"
if [ ! -d "$LOG_DIR" ]; then
    mkdir -p "$LOG_DIR"
    echo "✅ Created log directory: $LOG_DIR"
fi

echo ""
echo "🔧 Setting up shared mailbox processing..."
echo ""

# Test shared mailbox connections first
echo "🔍 Testing shared mailbox connections..."
if cd "$SCRIPT_DIR" && $PHP_PATH artisan mailbox:process-shared --test-connections; then
    echo "✅ Shared mailbox connections verified"
else
    echo "⚠️  Some shared mailbox connections failed - check configuration"
    echo "   You can continue with setup, but fix connection issues before running in production"
    read -p "   Continue with cron setup anyway? (y/n): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "❌ Setup cancelled. Fix shared mailbox connections first."
        exit 1
    fi
fi

# Create the new shared mailbox cron job entry (every 5 minutes)
SHARED_MAILBOX_CRON="*/5 * * * * cd $SCRIPT_DIR && $PHP_PATH artisan mailbox:process-shared >> $LOG_DIR/shared-mailbox.log 2>&1"

# Create cleanup cron job (daily at 2 AM)
CLEANUP_CRON="0 2 * * * cd $SCRIPT_DIR && $PHP_PATH artisan queue:prune-failed --hours=168 >> $LOG_DIR/cleanup.log 2>&1"

# Remove old email-to-ticket cron jobs if they exist
echo "🧹 Removing old email processing cron jobs..."
crontab -l 2>/dev/null | grep -v "emails:to-tickets" | grep -v "email-to-ticket" | grep -v "artisan schedule:run" > /tmp/new_crontab

# Add new shared mailbox cron jobs
echo "📧 Adding shared mailbox processing cron jobs..."
echo "$SHARED_MAILBOX_CRON" >> /tmp/new_crontab
echo "$CLEANUP_CRON" >> /tmp/new_crontab

# Install the new crontab
crontab /tmp/new_crontab
rm /tmp/new_crontab

echo ""
echo "✅ Shared mailbox cron jobs have been set up successfully!"
echo ""
echo "📋 The following cron jobs have been added:"
echo "   • Shared Mailbox Processing (every 5 minutes):"
echo "     $SHARED_MAILBOX_CRON"
echo ""
echo "   • Cleanup Failed Jobs (daily at 2 AM):"
echo "     $CLEANUP_CRON"
echo ""
echo "🎯 This new system will:"
echo "  • Fetch emails from shared mailboxes (support@company.com, billing@company.com, etc.)"
echo "  • Process emails to tickets every 5 minutes"
echo "  • Handle duplicate detection across shared mailboxes"
echo "  • Apply routing rules based on recipient address"
echo "  • Clean up failed processing jobs weekly"
echo ""
echo "📊 Monitoring and Management:"
echo "  • View current cron jobs: crontab -l"
echo "  • Test connections: php artisan mailbox:process-shared --test-connections"
echo "  • Run manually: php artisan mailbox:process-shared --verbose"
echo "  • Dry run test: php artisan mailbox:process-shared --dry-run"
echo ""
echo "📁 Log files:"
echo "  • $LOG_DIR/shared-mailbox.log (main processing log)"
echo "  • $LOG_DIR/cleanup.log (maintenance log)"
echo "  • $LOG_DIR/laravel.log (application errors)"
echo ""
echo "⚙️  Configuration:"
echo "  • Configure shared mailboxes in: email_accounts table with account_type='shared_mailbox'"
echo "  • Set up routing rules in the routing_rules JSON field"
echo "  • Customize agent signatures in signature_template field"
echo ""
echo "🔄 Migration from Individual Gmail:"
echo "  1. Your existing Gmail-based system is now replaced"
echo "  2. No more individual app passwords needed"
echo "  3. All agents send through shared mailboxes"
echo "  4. Centralized configuration and monitoring"
echo ""
echo "=========================================="
echo "✅ Setup Complete! Shared mailbox processing is now active."
echo "=========================================="