#!/bin/bash

# Setup cron job for Laravel/Lumen scheduler
# This runs the scheduler every minute, which then runs scheduled tasks

CRON_JOB="* * * * * php artisan schedule:run >> /dev/null 2>&1"

# Check if cron job already exists
if crontab -l 2>/dev/null | grep -q "schedule:run"; then
    echo "Cron job already exists"
else
    # Add cron job
    (crontab -l 2>/dev/null; echo "$CRON_JOB") | crontab -
    echo "Cron job added successfully"
fi

echo "Current crontab:"
crontab -l
