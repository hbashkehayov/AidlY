#!/bin/bash

# Kong Setup Script for AidlY
# This script configures Kong with services, routes, and plugins

set -e

KONG_ADMIN_URL="${KONG_ADMIN_URL:-http://localhost:8001}"

echo "🦍 Kong Setup for AidlY"
echo "======================="

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to wait for Kong to be ready
wait_for_kong() {
    echo -n "Waiting for Kong Admin API to be ready..."

    max_attempts=30
    attempt=1

    while [ $attempt -le $max_attempts ]; do
        if curl -s --max-time 3 "$KONG_ADMIN_URL/status" >/dev/null 2>&1; then
            echo -e " ${GREEN}✓ Ready${NC}"
            return 0
        fi

        echo -n "."
        sleep 2
        attempt=$((attempt + 1))
    done

    echo -e " ${RED}✗ Timeout${NC}"
    echo "Kong Admin API is not accessible at $KONG_ADMIN_URL"
    exit 1
}

# Function to create or update a Kong service
create_service() {
    local service_name=$1
    local service_url=$2

    echo -n "Creating/updating service: $service_name... "

    # Try to update existing service first
    response=$(curl -s -o /dev/null -w "%{http_code}" \
        -X PATCH "$KONG_ADMIN_URL/services/$service_name" \
        -H "Content-Type: application/json" \
        -d "{\"url\": \"$service_url\"}" 2>/dev/null || echo "000")

    if [ "$response" = "200" ]; then
        echo -e "${BLUE}Updated${NC}"
    else
        # Create new service
        response=$(curl -s -o /dev/null -w "%{http_code}" \
            -X POST "$KONG_ADMIN_URL/services" \
            -H "Content-Type: application/json" \
            -d "{\"name\": \"$service_name\", \"url\": \"$service_url\"}" 2>/dev/null || echo "000")

        if [ "$response" = "201" ]; then
            echo -e "${GREEN}Created${NC}"
        else
            echo -e "${RED}Failed (HTTP $response)${NC}"
            return 1
        fi
    fi
}

# Function to create or update a route
create_route() {
    local service_name=$1
    local route_name=$2
    local paths=$3
    local methods=${4:-"GET,POST,PUT,PATCH,DELETE,OPTIONS"}

    echo -n "Creating/updating route: $route_name... "

    # Convert comma-separated paths to JSON array
    paths_json=$(echo "$paths" | sed 's/,/","/g' | sed 's/^/["/' | sed 's/$/"]/')
    methods_json=$(echo "$methods" | sed 's/,/","/g' | sed 's/^/["/' | sed 's/$/"]/')

    # Try to update existing route first
    response=$(curl -s -o /dev/null -w "%{http_code}" \
        -X PATCH "$KONG_ADMIN_URL/routes/$route_name" \
        -H "Content-Type: application/json" \
        -d "{\"paths\": $paths_json, \"methods\": $methods_json, \"strip_path\": false}" 2>/dev/null || echo "000")

    if [ "$response" = "200" ]; then
        echo -e "${BLUE}Updated${NC}"
    else
        # Create new route
        response=$(curl -s -o /dev/null -w "%{http_code}" \
            -X POST "$KONG_ADMIN_URL/services/$service_name/routes" \
            -H "Content-Type: application/json" \
            -d "{\"name\": \"$route_name\", \"paths\": $paths_json, \"methods\": $methods_json, \"strip_path\": false}" 2>/dev/null || echo "000")

        if [ "$response" = "201" ]; then
            echo -e "${GREEN}Created${NC}"
        else
            echo -e "${RED}Failed (HTTP $response)${NC}"
            return 1
        fi
    fi
}

# Function to apply plugin to service
apply_plugin() {
    local service_name=$1
    local plugin_name=$2
    local config=$3

    echo -n "Applying plugin $plugin_name to $service_name... "

    # Check if plugin already exists
    existing_plugin=$(curl -s "$KONG_ADMIN_URL/services/$service_name/plugins" | jq -r ".data[] | select(.name == \"$plugin_name\") | .id" 2>/dev/null || echo "")

    if [ -n "$existing_plugin" ]; then
        # Update existing plugin
        response=$(curl -s -o /dev/null -w "%{http_code}" \
            -X PATCH "$KONG_ADMIN_URL/plugins/$existing_plugin" \
            -H "Content-Type: application/json" \
            -d "{\"config\": $config}" 2>/dev/null || echo "000")

        if [ "$response" = "200" ]; then
            echo -e "${BLUE}Updated${NC}"
        else
            echo -e "${RED}Failed to update (HTTP $response)${NC}"
        fi
    else
        # Create new plugin
        response=$(curl -s -o /dev/null -w "%{http_code}" \
            -X POST "$KONG_ADMIN_URL/services/$service_name/plugins" \
            -H "Content-Type: application/json" \
            -d "{\"name\": \"$plugin_name\", \"config\": $config}" 2>/dev/null || echo "000")

        if [ "$response" = "201" ]; then
            echo -e "${GREEN}Created${NC}"
        else
            echo -e "${RED}Failed to create (HTTP $response)${NC}"
        fi
    fi
}

# Function to setup all services and routes
setup_services() {
    echo -e "\n📋 Setting up Kong services and routes..."

    # Auth Service
    create_service "auth-service" "http://auth-service:8001"
    create_route "auth-service" "auth-public-routes" "/api/v1/auth/login,/api/v1/auth/register,/api/v1/auth/forgot-password,/api/v1/auth/reset-password" "GET,POST"
    create_route "auth-service" "auth-protected-routes" "/api/v1/auth/user,/api/v1/auth/refresh,/api/v1/auth/logout" "GET,POST"

    # Ticket Service
    create_service "ticket-service" "http://ticket-service:8002"
    create_route "ticket-service" "ticket-routes" "/api/v1/tickets" "GET,POST,PUT,PATCH,DELETE,OPTIONS"

    # Client Service
    create_service "client-service" "http://client-service:8003"
    create_route "client-service" "client-routes" "/api/v1/clients" "GET,POST,PUT,PATCH,DELETE,OPTIONS"

    # Notification Service
    create_service "notification-service" "http://notification-service:8004"
    create_route "notification-service" "notification-routes" "/api/v1/notifications" "GET,POST,PUT,PATCH,DELETE,OPTIONS"

    # Email Service
    create_service "email-service" "http://email-service:8005"
    create_route "email-service" "email-routes" "/api/v1/emails" "GET,POST,PUT,PATCH,DELETE,OPTIONS"

    echo -e "${GREEN}✅ Services and routes configured${NC}"
}

# Function to setup plugins
setup_plugins() {
    echo -e "\n🔌 Setting up Kong plugins..."

    # CORS plugin for auth service
    cors_config='{
        "origins": ["http://localhost:3000", "https://localhost:3000", "*"],
        "methods": ["GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS", "HEAD"],
        "headers": ["Accept", "Accept-Version", "Content-Length", "Content-Type", "Authorization", "X-Requested-With"],
        "credentials": true,
        "max_age": 3600
    }'

    apply_plugin "auth-service" "cors" "$cors_config"

    # Rate limiting for auth service
    rate_limit_config='{"minute": 30, "hour": 200, "policy": "local"}'
    apply_plugin "auth-service" "rate-limiting" "$rate_limit_config"

    # Apply CORS to all services
    for service in ticket-service client-service notification-service email-service; do
        apply_plugin "$service" "cors" "$cors_config"
        apply_plugin "$service" "rate-limiting" '{"minute": 60, "hour": 1000, "policy": "local"}'
    done

    echo -e "${GREEN}✅ Plugins configured${NC}"
}

# Function to apply global plugins
setup_global_plugins() {
    echo -e "\n🌐 Setting up global plugins..."

    # Prometheus plugin
    prometheus_config='{"per_consumer": false, "status_code_metrics": true, "latency_metrics": true, "bandwidth_metrics": true}'

    echo -n "Applying global Prometheus plugin... "
    response=$(curl -s -o /dev/null -w "%{http_code}" \
        -X POST "$KONG_ADMIN_URL/plugins" \
        -H "Content-Type: application/json" \
        -d "{\"name\": \"prometheus\", \"config\": $prometheus_config}" 2>/dev/null || echo "000")

    if [ "$response" = "201" ] || [ "$response" = "409" ]; then
        echo -e "${GREEN}✅ Applied${NC}"
    else
        echo -e "${RED}Failed (HTTP $response)${NC}"
    fi

    echo -e "${GREEN}✅ Global plugins configured${NC}"
}

# Function to show Kong status
show_status() {
    echo -e "\n📊 Kong Status:"

    # Get services count
    services_count=$(curl -s "$KONG_ADMIN_URL/services" | jq -r '.data | length' 2>/dev/null || echo "0")
    echo "  • Services: $services_count"

    # Get routes count
    routes_count=$(curl -s "$KONG_ADMIN_URL/routes" | jq -r '.data | length' 2>/dev/null || echo "0")
    echo "  • Routes: $routes_count"

    # Get plugins count
    plugins_count=$(curl -s "$KONG_ADMIN_URL/plugins" | jq -r '.data | length' 2>/dev/null || echo "0")
    echo "  • Plugins: $plugins_count"

    echo -e "\n🔗 Access URLs:"
    echo "  • Kong Admin API: $KONG_ADMIN_URL"
    echo "  • Kong Proxy: http://localhost:8000"
    echo "  • Prometheus Metrics: http://localhost:8001/metrics"
}

# Main execution
main() {
    echo "Starting Kong setup process..."

    # Wait for Kong to be ready
    wait_for_kong

    # Setup services and routes
    setup_services

    # Setup plugins
    setup_plugins

    # Setup global plugins
    setup_global_plugins

    # Show status
    show_status

    echo -e "\n${GREEN}🎉 Kong setup completed successfully!${NC}"
    echo -e "\n${YELLOW}Note: Services need to be actually running for routes to work properly.${NC}"
    echo -e "${YELLOW}Run the health check script to verify everything is working.${NC}"
}

# Check if jq is available
if ! command -v jq >/dev/null 2>&1; then
    echo -e "${YELLOW}⚠ jq is not installed. Some features may not work properly.${NC}"
fi

# Run setup
main "$@"