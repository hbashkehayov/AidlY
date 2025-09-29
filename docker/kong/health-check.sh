#!/bin/bash

# Health Check Script for AidlY Services
# This script verifies that all microservices are healthy and accessible through Kong

set -e

KONG_ADMIN_URL="${KONG_ADMIN_URL:-http://localhost:8001}"
KONG_PROXY_URL="${KONG_PROXY_URL:-http://localhost:8000}"
HEALTH_CHECK_TIMEOUT=10

echo "🏥 AidlY Service Health Check"
echo "============================="

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to check if a service is responding
check_service_health() {
    local service_name=$1
    local service_url=$2
    local expected_status=${3:-200}

    echo -n "Checking $service_name... "

    response=$(curl -s -o /dev/null -w "%{http_code}" --max-time $HEALTH_CHECK_TIMEOUT "$service_url" || echo "000")

    if [ "$response" = "$expected_status" ]; then
        echo -e "${GREEN}✓ Healthy${NC}"
        return 0
    else
        echo -e "${RED}✗ Unhealthy (HTTP $response)${NC}"
        return 1
    fi
}

# Function to check Kong admin API
check_kong_admin() {
    echo -n "Checking Kong Admin API... "

    response=$(curl -s -o /dev/null -w "%{http_code}" --max-time $HEALTH_CHECK_TIMEOUT "$KONG_ADMIN_URL/status" || echo "000")

    if [ "$response" = "200" ]; then
        echo -e "${GREEN}✓ Healthy${NC}"

        # Get Kong status
        kong_status=$(curl -s --max-time $HEALTH_CHECK_TIMEOUT "$KONG_ADMIN_URL/status" | jq -r '.database.reachable // "unknown"' 2>/dev/null || echo "unknown")
        echo "  Database reachable: $kong_status"
        return 0
    else
        echo -e "${RED}✗ Unhealthy (HTTP $response)${NC}"
        return 1
    fi
}

# Function to check Kong services
check_kong_services() {
    echo -e "\n📋 Kong Services Status:"

    services=$(curl -s --max-time $HEALTH_CHECK_TIMEOUT "$KONG_ADMIN_URL/services" | jq -r '.data[].name' 2>/dev/null || echo "")

    if [ -z "$services" ]; then
        echo -e "${YELLOW}⚠ No services found or Kong unreachable${NC}"
        return 1
    fi

    for service in $services; do
        service_info=$(curl -s --max-time $HEALTH_CHECK_TIMEOUT "$KONG_ADMIN_URL/services/$service" | jq -r '.host + ":" + (.port | tostring)' 2>/dev/null || echo "unknown")
        echo "  • $service ($service_info)"
    done
}

# Function to test routes through Kong
test_kong_routes() {
    echo -e "\n🔀 Testing Routes through Kong:"

    # Test health endpoint
    check_service_health "Health Endpoint" "$KONG_PROXY_URL/health" "404"

    # Test auth service (should return 405 for GET on login)
    check_service_health "Auth Service Routes" "$KONG_PROXY_URL/api/v1/auth/login" "405"

    echo -e "\n${YELLOW}Note: Protected routes will return 401/403 without authentication${NC}"
}

# Function to check upstream services directly
check_upstream_services() {
    echo -e "\n🔧 Checking Upstream Services:"

    # Check if services are running (this would need to be adapted based on actual service discovery)
    services_to_check=(
        "auth-service:8001"
        "ticket-service:8002"
        "client-service:8003"
        "notification-service:8004"
        "email-service:8005"
    )

    for service_endpoint in "${services_to_check[@]}"; do
        service_name=$(echo $service_endpoint | cut -d: -f1)
        echo -e "${YELLOW}  • $service_name: Not yet implemented${NC}"
    done
}

# Function to check database connections
check_databases() {
    echo -e "\n💾 Database Health:"

    # PostgreSQL main database
    if command -v psql >/dev/null 2>&1; then
        if PGPASSWORD="${DB_PASSWORD:-aidly_secret_2024}" psql -h localhost -U aidly_user -d aidly -c "SELECT 1;" >/dev/null 2>&1; then
            echo -e "  • PostgreSQL (main): ${GREEN}✓ Connected${NC}"
        else
            echo -e "  • PostgreSQL (main): ${RED}✗ Connection failed${NC}"
        fi
    else
        echo -e "  • PostgreSQL (main): ${YELLOW}⚠ psql not available${NC}"
    fi

    # Redis
    if command -v redis-cli >/dev/null 2>&1; then
        if redis-cli -h localhost -p 6379 -a "${REDIS_PASSWORD:-redis_secret_2024}" ping >/dev/null 2>&1; then
            echo -e "  • Redis: ${GREEN}✓ Connected${NC}"
        else
            echo -e "  • Redis: ${RED}✗ Connection failed${NC}"
        fi
    else
        echo -e "  • Redis: ${YELLOW}⚠ redis-cli not available${NC}"
    fi
}

# Function to show Kong configuration summary
show_kong_config() {
    echo -e "\n⚙️  Kong Configuration Summary:"
    echo "  Admin API: $KONG_ADMIN_URL"
    echo "  Proxy URL: $KONG_PROXY_URL"
    echo "  Timeout: ${HEALTH_CHECK_TIMEOUT}s"
}

# Main execution
main() {
    show_kong_config

    echo -e "\n🚀 Starting Health Checks..."

    # Check Kong itself
    if ! check_kong_admin; then
        echo -e "\n${RED}❌ Kong Admin API is not accessible. Please check if Kong is running.${NC}"
        exit 1
    fi

    # Check Kong services configuration
    check_kong_services

    # Test routes
    test_kong_routes

    # Check upstream services
    check_upstream_services

    # Check databases
    check_databases

    echo -e "\n✅ Health check completed!"
    echo -e "\n${YELLOW}Next steps:${NC}"
    echo "  1. Implement actual microservices"
    echo "  2. Add proper health endpoints to each service"
    echo "  3. Configure service discovery"
    echo "  4. Set up monitoring dashboards"
}

# Run health checks
main "$@"