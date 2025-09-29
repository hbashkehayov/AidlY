#!/bin/bash

# Email Service - Cron Job Setup Script
# This script sets up the cron job for email-to-ticket conversion

echo "=========================================="
echo "Email-to-Ticket Cron Job Setup"
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

# Create the cron job entry
CRON_JOB="* * * * * cd $SCRIPT_DIR && $PHP_PATH artisan schedule:run >> $LOG_DIR/cron.log 2>&1"

# Check if cron job already exists
if crontab -l 2>/dev/null | grep -q "artisan schedule:run"; then
    echo "⚠️  Cron job already exists. Updating..."
    # Remove existing job
    crontab -l | grep -v "artisan schedule:run" | crontab -
fi

# Add the cron job
(crontab -l 2>/dev/null; echo "$CRON_JOB") | crontab -

echo "✅ Cron job has been set up successfully!"
echo ""
echo "The following cron job has been added:"
echo "$CRON_JOB"
echo ""
echo "This will run the Laravel scheduler every minute, which will:"
echo "  • Fetch and process emails every 5 minutes"
echo "  • Retry failed emails every 15 minutes"
echo "  • Clean up old attachments daily at 2 AM"
echo ""
echo "To view the current cron jobs, run: crontab -l"
echo "To remove the cron job, run: crontab -r"
echo "To edit cron jobs manually, run: crontab -e"
echo ""
echo "Logs will be written to:"
echo "  • $LOG_DIR/email-to-ticket.log (main process)"
echo "  • $LOG_DIR/cron.log (scheduler output)"
echo ""
echo "=========================================="