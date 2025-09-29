#!/bin/bash

# Sprint 4.2 Status Check - Simple diagnostic script
# Shows current implementation status without failing on missing components

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Sprint 4.2 AI Integration Status Check${NC}"
echo -e "${BLUE}========================================${NC}\n"

# Check database connection
echo -e "${BLUE}1. Database Connection:${NC}"
if PGPASSWORD="aidly_secret_2024" psql -h localhost -U aidly_user -d aidly -c "SELECT 1;" > /dev/null 2>&1; then
    echo -e "   ${GREEN}✓${NC} Database connection successful"
else
    echo -e "   ${RED}✗${NC} Database connection failed"
fi

# Check AI fields in tickets table
echo -e "\n${BLUE}2. AI Fields in Tickets Table:${NC}"
ai_fields=(
    "ai_suggestion"
    "ai_confidence_score"
    "ai_suggested_category_id"
    "ai_suggested_priority"
    "ai_processed_at"
    "ai_provider"
    "ai_model_version"
    "ai_webhook_url"
    "detected_language"
    "language_confidence_score"
    "sentiment_score"
    "sentiment_confidence"
    "ai_category_suggestions"
    "ai_tag_suggestions"
    "ai_response_suggestions"
    "ai_estimated_resolution_time"
    "ai_processing_metadata"
    "ai_processing_status"
    "ai_last_processed_at"
    "ai_categorization_enabled"
    "ai_suggestions_enabled"
    "ai_sentiment_analysis_enabled"
)

basic_fields_count=0
enhanced_fields_count=0

for field in "${ai_fields[@]}"; do
    if PGPASSWORD="aidly_secret_2024" psql -h localhost -U aidly_user -d aidly -c "\d tickets" 2>/dev/null | grep -q "$field"; then
        echo -e "   ${GREEN}✓${NC} $field"
        if [[ "$field" == ai_suggestion || "$field" == ai_confidence_score || "$field" == ai_suggested_category_id || "$field" == ai_suggested_priority || "$field" == ai_processed_at || "$field" == ai_provider || "$field" == ai_model_version || "$field" == ai_webhook_url ]]; then
            ((basic_fields_count++))
        else
            ((enhanced_fields_count++))
        fi
    else
        echo -e "   ${RED}✗${NC} $field (MISSING)"
    fi
done

echo -e "\n   Basic AI fields present: ${basic_fields_count}/8"
echo -e "   Enhanced AI fields present: ${enhanced_fields_count}/14"

# Check AI configuration table
echo -e "\n${BLUE}3. AI Configuration Table:${NC}"
if PGPASSWORD="aidly_secret_2024" psql -h localhost -U aidly_user -d aidly -c "\d ai_configurations" > /dev/null 2>&1; then
    echo -e "   ${GREEN}✓${NC} ai_configurations table exists"
    config_count=$(PGPASSWORD="aidly_secret_2024" psql -h localhost -U aidly_user -d aidly -t -c "SELECT COUNT(*) FROM ai_configurations;" 2>/dev/null | xargs)
    echo -e "   ${GREEN}✓${NC} $config_count AI configurations present"
else
    echo -e "   ${RED}✗${NC} ai_configurations table missing"
fi

# Check AI processing queue table
echo -e "\n${BLUE}4. AI Processing Queue Table:${NC}"
if PGPASSWORD="aidly_secret_2024" psql -h localhost -U aidly_user -d aidly -c "\d ai_processing_queue" > /dev/null 2>&1; then
    echo -e "   ${GREEN}✓${NC} ai_processing_queue table exists"
    queue_count=$(PGPASSWORD="aidly_secret_2024" psql -h localhost -U aidly_user -d aidly -t -c "SELECT COUNT(*) FROM ai_processing_queue;" 2>/dev/null | xargs)
    echo -e "   ${GREEN}✓${NC} $queue_count queue entries present"
else
    echo -e "   ${RED}✗${NC} ai_processing_queue table missing"
fi

# Check running services
echo -e "\n${BLUE}5. Microservices Status:${NC}"
services=("postgres" "redis" "rabbitmq" "kong" "auth-service" "ticket-service" "client-service" "notification-service" "email-service" "ai-integration-service")

for service in "${services[@]}"; do
    if docker ps --format "{{.Names}}" | grep -q "aidly-$service"; then
        echo -e "   ${GREEN}✓${NC} $service (running)"
    else
        echo -e "   ${RED}✗${NC} $service (not running)"
    fi
done

# Check service endpoints
echo -e "\n${BLUE}6. Service Endpoints:${NC}"
endpoints=(
    "http://localhost:8001/health:Auth Service"
    "http://localhost:8002/health:Ticket Service"
    "http://localhost:8003/health:Client Service"
    "http://localhost:8004/health:Notification Service"
    "http://localhost:8005/health:Email Service"
    "http://localhost:8006/health:AI Integration Service"
)

for endpoint_info in "${endpoints[@]}"; do
    IFS=':' read -r endpoint name <<< "$endpoint_info"
    if curl -s -f "$endpoint" > /dev/null 2>&1; then
        echo -e "   ${GREEN}✓${NC} $name ($endpoint)"
    else
        echo -e "   ${RED}✗${NC} $name ($endpoint)"
    fi
done

# Summary
echo -e "\n${BLUE}========================================${NC}"
echo -e "${BLUE}Sprint 4.2 Implementation Summary${NC}"
echo -e "${BLUE}========================================${NC}"

if [[ $basic_fields_count -eq 8 && $enhanced_fields_count -eq 14 ]]; then
    echo -e "✅ ${GREEN}Database Schema: COMPLETE${NC}"
else
    echo -e "❌ ${RED}Database Schema: INCOMPLETE${NC} ($((basic_fields_count + enhanced_fields_count))/22 fields)"
fi

if docker ps --format "{{.Names}}" | grep -q "aidly-ai-integration-service"; then
    echo -e "✅ ${GREEN}AI Integration Service: RUNNING${NC}"
else
    echo -e "❌ ${RED}AI Integration Service: NOT RUNNING${NC}"
fi

if curl -s -f "http://localhost:8006/health" > /dev/null 2>&1; then
    echo -e "✅ ${GREEN}AI Service Health: OK${NC}"
else
    echo -e "❌ ${RED}AI Service Health: FAILED${NC}"
fi

echo -e "\n${YELLOW}Next Steps to Complete Sprint 4.2:${NC}"
echo -e "1. Add missing AI fields to tickets table"
echo -e "2. Start AI Integration Service (port 8006)"
echo -e "3. Implement AI webhook endpoints"
echo -e "4. Add feature flags system"
echo -e "5. Test all AI integration points"

echo -e "\n${BLUE}Current Sprint 4.2 Completion: ~25%${NC}"
echo -e "${YELLOW}Status: INCOMPLETE - Significant work remaining${NC}"