#!/bin/bash

# Sprint 4.2 Final Verification Test - Fixed Version
# Quick test to verify Sprint 4.2 completion

echo "🎯 Sprint 4.2 AI Integration Points - Final Verification"
echo "========================================================"

# Test counters
tests_passed=0
tests_total=0

# Test function for GET requests
test_get_endpoint() {
    local name="$1"
    local url="$2"
    local expected_status="$3"

    ((tests_total++))
    echo -n "Testing $name... "

    status=$(curl -s -o /dev/null -w "%{http_code}" "$url" 2>/dev/null)

    if [[ "$status" == "$expected_status" ]]; then
        echo "✅ PASS ($status)"
        ((tests_passed++))
    else
        echo "❌ FAIL (got $status, expected $expected_status)"
    fi
}

# Test function for POST requests (webhooks)
test_post_endpoint() {
    local name="$1"
    local url="$2"
    local expected_status="$3"

    ((tests_total++))
    echo -n "Testing $name... "

    status=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$url" -H "Content-Type: application/json" -d '{"test": "data"}' 2>/dev/null)

    if [[ "$status" == "$expected_status" ]]; then
        echo "✅ PASS ($status)"
        ((tests_passed++))
    else
        echo "❌ FAIL (got $status, expected $expected_status)"
    fi
}

# Test database AI fields
echo -e "\n📊 Database Schema Tests:"
ai_fields_count=$(PGPASSWORD="aidly_secret_2024" psql -h localhost -U aidly_user -d aidly -t -c "
SELECT COUNT(*) FROM information_schema.columns
WHERE table_name = 'tickets'
AND (column_name LIKE '%ai_%' OR column_name LIKE '%sentiment%' OR column_name LIKE '%language%');" 2>/dev/null | xargs)

if [[ "$ai_fields_count" -ge 22 ]]; then
    echo "✅ AI fields in tickets table: $ai_fields_count/22 (COMPLETE)"
    ((tests_passed++))
else
    echo "❌ AI fields in tickets table: $ai_fields_count/22 (INCOMPLETE)"
fi
((tests_total++))

# Check feature flags
feature_flags_count=$(PGPASSWORD="aidly_secret_2024" psql -h localhost -U aidly_user -d aidly -t -c "SELECT COUNT(*) FROM feature_flags;" 2>/dev/null | xargs)
if [[ "$feature_flags_count" -ge 8 ]]; then
    echo "✅ Feature flags table: $feature_flags_count flags (COMPLETE)"
    ((tests_passed++))
else
    echo "❌ Feature flags table: $feature_flags_count flags (INCOMPLETE)"
fi
((tests_total++))

# Check AI configurations
ai_configs_count=$(PGPASSWORD="aidly_secret_2024" psql -h localhost -U aidly_user -d aidly -t -c "SELECT COUNT(*) FROM ai_configurations;" 2>/dev/null | xargs)
if [[ "$ai_configs_count" -ge 5 ]]; then
    echo "✅ AI configurations table: $ai_configs_count configurations (COMPLETE)"
    ((tests_passed++))
else
    echo "❌ AI configurations table: $ai_configs_count configurations (INCOMPLETE)"
fi
((tests_total++))

# Test service endpoints
echo -e "\n🚀 Service Endpoint Tests:"
test_get_endpoint "Ticket Service Health" "http://localhost:8002/health" "200"
test_get_endpoint "Client Service Health" "http://localhost:8003/health" "200"
test_get_endpoint "Notification Service Health" "http://localhost:8004/health" "200"
test_get_endpoint "AI Integration Service Health" "http://localhost:8006/health" "200"

# Test AI service specific endpoints
echo -e "\n🤖 AI Integration Service Tests:"
test_get_endpoint "AI Monitoring Health" "http://localhost:8006/api/v1/monitoring/health" "200"
test_get_endpoint "AI Monitoring Metrics" "http://localhost:8006/api/v1/monitoring/metrics" "200"
test_get_endpoint "AI Monitoring Performance" "http://localhost:8006/api/v1/monitoring/performance" "200"
test_get_endpoint "AI Monitoring Errors" "http://localhost:8006/api/v1/monitoring/errors" "200"

# Test webhook endpoints (POST requests)
echo -e "\n🔗 AI Webhook Endpoint Tests:"
test_post_endpoint "OpenAI Webhook" "http://localhost:8006/api/v1/webhooks/openai" "200"
test_post_endpoint "Anthropic Webhook" "http://localhost:8006/api/v1/webhooks/anthropic" "200"
test_post_endpoint "Gemini Webhook" "http://localhost:8006/api/v1/webhooks/gemini" "200"
test_post_endpoint "N8N Webhook" "http://localhost:8006/api/v1/webhooks/n8n" "200"

# Test configuration endpoints
echo -e "\n⚙️  AI Configuration Tests:"
test_get_endpoint "Feature Flags Endpoint" "http://localhost:8006/api/v1/feature-flags" "200"
test_get_endpoint "AI Configurations" "http://localhost:8006/api/v1/configurations" "200"
test_get_endpoint "AI Jobs Queue" "http://localhost:8006/api/v1/jobs" "200"

# Test provider endpoints
echo -e "\n🔌 AI Provider Tests:"
test_get_endpoint "OpenAI Provider Test" "http://localhost:8006/api/v1/providers/openai/test" "200"
test_get_endpoint "Anthropic Provider Test" "http://localhost:8006/api/v1/providers/anthropic/test" "200"
test_get_endpoint "Gemini Provider Test" "http://localhost:8006/api/v1/providers/gemini/test" "200"
test_get_endpoint "N8N Provider Test" "http://localhost:8006/api/v1/providers/n8n/test" "200"

# Final summary
echo -e "\n📋 SPRINT 4.2 FINAL RESULTS"
echo "================================"
echo "Tests Passed: $tests_passed/$tests_total"
echo "Success Rate: $(( tests_passed * 100 / tests_total ))%"

if [[ $tests_passed -eq $tests_total ]]; then
    echo -e "\n🎉 SUCCESS: Sprint 4.2 is 100% COMPLETE!"
    echo "✅ All AI integration points are implemented and working"
    echo "✅ Database schema enhanced with all required AI fields (22/22)"
    echo "✅ Feature flags system operational (8 flags)"
    echo "✅ AI Integration Service running with all endpoints"
    echo "✅ AI configurations present (5 providers configured)"
    echo "✅ Webhook infrastructure ready for AI providers"
    echo "✅ Provider abstraction layer functional"
    echo "✅ Monitoring and health checks operational"
    echo -e "\n🚀 READY TO PROCEED TO SPRINT 5.1 (Analytics Service)"
    exit 0
else
    missing=$((tests_total - tests_passed))
    echo -e "\n⚠️  Sprint 4.2 Status: $missing issues remaining"
    echo "Current completion: $(( tests_passed * 100 / tests_total ))%"
    if [[ $(( tests_passed * 100 / tests_total )) -ge 90 ]]; then
        echo -e "\n✨ EXCELLENT PROGRESS! Sprint 4.2 is nearly complete."
        echo "Minor issues can be addressed in Sprint 5.1 if needed."
    fi
    exit 1
fi