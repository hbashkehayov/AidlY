#!/bin/bash
# Trigger instant email processing
# This script should be called by cron every minute for quasi-instant processing

curl -X POST http://localhost:8005/api/v1/webhooks/email/trigger \
  -H "Content-Type: application/json" \
  -d '{}' \
  -s -o /dev/null -w "Status: %{http_code}\n"
