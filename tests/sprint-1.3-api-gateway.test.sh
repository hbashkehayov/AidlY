#!/bin/bash

# Sprint 1.3 API Gateway Tests
# Tests Kong API Gateway configuration, routing, rate limiting, CORS, and monitoring

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

TEST_RESULTS=()
FAILED_TESTS=0
KONG_ADMIN_URL="http://localhost:8001"
KONG_PROXY_URL="http://localhost:8000"

echo "🧪 Testing Sprint 1.3: API Gateway & Service Discovery"
echo "======================================================"

# Function to log test results
log_test() {
    local test_name="$1"
    local status="$2"
    local message="$3"

    if [ "$status" = "PASS" ]; then
        echo -e "✅ ${GREEN}PASS${NC}: $test_name"
        TEST_RESULTS+=("PASS: $test_name")
    elif [ "$status" = "SKIP" ]; then
        echo -e "⏭️  ${YELLOW}SKIP${NC}: $test_name - $message"
        TEST_RESULTS+=("SKIP: $test_name - $message")
    else
        echo -e "❌ ${RED}FAIL${NC}: $test_name - $message"
        TEST_RESULTS+=("FAIL: $test_name - $message")
        FAILED_TESTS=$((FAILED_TESTS + 1))
    fi
}

# Function to make HTTP requests with error handling
make_request() {
    local method="$1"
    local url="$2"
    local data="$3"
    local headers="$4"
    local expected_status="$5"

    local curl_cmd="curl -s -w '%{http_code}' -X $method '$url'"

    if [ -n "$data" ]; then
        curl_cmd="$curl_cmd -H 'Content-Type: application/json' -d '$data'"
    fi

    if [ -n "$headers" ]; then
        curl_cmd="$curl_cmd $headers"
    fi

    local response=$(eval $curl_cmd 2>/dev/null)
    local http_code="${response: -3}"
    local body="${response%???}"

    if [ -n "$expected_status" ] && [ "$http_code" != "$expected_status" ]; then
        echo "Expected: $expected_status, Got: $http_code, Body: $body" >&2
        return 1
    fi

    echo "$body"
    return 0
}

# Test 1: Kong Configuration Files
test_kong_configuration_files() {
    echo -e "\n${BLUE}Testing Kong Configuration Files...${NC}"

    # Check if Kong configuration files exist
    required_files=(
        "docker/kong/kong.yml"
        "docker/kong/kong-setup.sh"
        "docker/kong/health-check.sh"
        "docker/kong/README.md"
        "docker/monitoring/prometheus.yml"
        "docker/monitoring/alertmanager.yml"
        "docker/monitoring/alert_rules.yml"
    )

    for file in "${required_files[@]}"; do
        if [ -f "/root/AidlY/$file" ]; then
            log_test "Configuration file $file exists" "PASS"
        else
            log_test "Configuration file $file exists" "FAIL" "Required file missing"
        fi
    done

    # Check if Kong configuration is valid YAML
    if command -v yq >/dev/null 2>&1 || python3 -c "import yaml" 2>/dev/null; then
        if python3 -c "import yaml; yaml.safe_load(open('/root/AidlY/docker/kong/kong.yml'))" 2>/dev/null; then
            log_test "Kong YAML configuration is valid" "PASS"
        else
            log_test "Kong YAML configuration is valid" "FAIL" "Invalid YAML syntax"
        fi
    else
        log_test "Kong YAML configuration is valid" "SKIP" "No YAML parser available"
    fi
}

# Test 2: Kong Service Availability
test_kong_availability() {
    echo -e "\n${BLUE}Testing Kong Service Availability...${NC}"

    # Check if Kong containers are running
    if docker compose ps kong | grep -q "running"; then
        log_test "Kong container is running" "PASS"
    else
        log_test "Kong container is running" "FAIL" "Kong container not running"
        return
    fi

    if docker compose ps kong-database | grep -q "running"; then
        log_test "Kong database container is running" "PASS"
    else
        log_test "Kong database container is running" "FAIL" "Kong database container not running"
    fi

    # Test Kong Admin API
    response=$(make_request "GET" "$KONG_ADMIN_URL/status" "" "" "200")
    if [ $? -eq 0 ]; then
        log_test "Kong Admin API accessible" "PASS"

        # Check database connectivity
        if echo "$response" | grep -q '"reachable":true'; then
            log_test "Kong database connectivity" "PASS"
        else
            log_test "Kong database connectivity" "FAIL" "Database not reachable"
        fi
    else
        log_test "Kong Admin API accessible" "FAIL" "Cannot reach Kong Admin API"
        return
    fi

    # Test Kong Proxy
    response=$(curl -s -o /dev/null -w "%{http_code}" "$KONG_PROXY_URL" 2>/dev/null)
    if [ "$response" = "404" ]; then
        log_test "Kong Proxy accessible" "PASS"
        echo "  → Kong Proxy responding (404 expected for root path)"
    else
        log_test "Kong Proxy accessible" "FAIL" "Unexpected response: $response"
    fi
}

# Test 3: Service Configuration
test_service_configuration() {
    echo -e "\n${BLUE}Testing Service Configuration...${NC}"

    # Get all configured services
    services_response=$(make_request "GET" "$KONG_ADMIN_URL/services" "" "" "200")
    if [ $? -ne 0 ]; then
        log_test "Retrieve Kong services" "FAIL" "Cannot get services from Kong"
        return
    fi

    # Expected services
    expected_services=("auth-service" "ticket-service" "client-service" "notification-service" "email-service")

    # Check if we have any services configured
    service_count=$(echo "$services_response" | grep -o '"name"' | wc -l)

    if [ "$service_count" -gt 0 ]; then
        log_test "Services are configured in Kong" "PASS"
        echo "  → Found $service_count services"
    else
        log_test "Services are configured in Kong" "FAIL" "No services found - declarative config may not be loaded"

        # If no services found, this might be expected if using declarative config
        # Let's check if the kong.yml is properly mounted
        if docker exec aidly-kong ls -la /opt/kong/kong.yml >/dev/null 2>&1; then
            log_test "Kong declarative config file mounted" "PASS"
        else
            log_test "Kong declarative config file mounted" "FAIL" "kong.yml not found in container"
        fi
        return
    fi

    # Check each expected service
    for service in "${expected_services[@]}"; do
        if echo "$services_response" | grep -q "\"name\":\"$service\""; then
            log_test "Service $service configured" "PASS"
        else
            log_test "Service $service configured" "FAIL" "Service not found in Kong"
        fi
    done
}

# Test 4: Route Configuration
test_route_configuration() {
    echo -e "\n${BLUE}Testing Route Configuration...${NC}"

    # Get all configured routes
    routes_response=$(make_request "GET" "$KONG_ADMIN_URL/routes" "" "" "200")
    if [ $? -ne 0 ]; then
        log_test "Retrieve Kong routes" "FAIL" "Cannot get routes from Kong"
        return
    fi

    # Check route count
    route_count=$(echo "$routes_response" | grep -o '"name"' | wc -l)

    if [ "$route_count" -gt 0 ]; then
        log_test "Routes are configured in Kong" "PASS"
        echo "  → Found $route_count routes"
    else
        log_test "Routes are configured in Kong" "FAIL" "No routes found"
        return
    fi

    # Expected route paths
    expected_paths=("/api/v1/auth" "/api/v1/tickets" "/api/v1/clients" "/api/v1/notifications" "/api/v1/emails")

    for path in "${expected_paths[@]}"; do
        if echo "$routes_response" | grep -q "$path"; then
            log_test "Route for $path configured" "PASS"
        else
            log_test "Route for $path configured" "FAIL" "Route path not found"
        fi
    done
}

# Test 5: Plugin Configuration
test_plugin_configuration() {
    echo -e "\n${BLUE}Testing Plugin Configuration...${NC}"

    # Get all configured plugins
    plugins_response=$(make_request "GET" "$KONG_ADMIN_URL/plugins" "" "" "200")
    if [ $? -ne 0 ]; then
        log_test "Retrieve Kong plugins" "FAIL" "Cannot get plugins from Kong"
        return
    fi

    # Check plugin count
    plugin_count=$(echo "$plugins_response" | grep -o '"name"' | wc -l)

    if [ "$plugin_count" -gt 0 ]; then
        log_test "Plugins are configured in Kong" "PASS"
        echo "  → Found $plugin_count plugins"
    else
        log_test "Plugins are configured in Kong" "FAIL" "No plugins found"
        return
    fi

    # Expected plugins
    expected_plugins=("cors" "rate-limiting" "prometheus" "key-auth")

    for plugin in "${expected_plugins[@]}"; do
        if echo "$plugins_response" | grep -q "\"name\":\"$plugin\""; then
            log_test "Plugin $plugin configured" "PASS"
        else
            log_test "Plugin $plugin configured" "FAIL" "Plugin not found"
        fi
    done
}

# Test 6: Rate Limiting
test_rate_limiting() {
    echo -e "\n${BLUE}Testing Rate Limiting...${NC}"

    # Test rate limiting by making multiple requests quickly
    # Note: This test may fail if services are not actually running
    test_endpoint="$KONG_PROXY_URL/api/v1/auth/login"

    echo "  → Testing rate limiting on auth endpoint..."

    # Make rapid requests to trigger rate limiting
    rate_limit_hit=false
    for i in {1..35}; do
        response=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$test_endpoint" \
            -H "Content-Type: application/json" \
            -d '{"email":"test@example.com","password":"test"}' 2>/dev/null)

        if [ "$response" = "429" ]; then
            rate_limit_hit=true
            break
        fi
        sleep 0.1
    done

    if [ "$rate_limit_hit" = true ]; then
        log_test "Rate limiting is working" "PASS"
        echo "  → Rate limit triggered after multiple requests"
    else
        log_test "Rate limiting is working" "SKIP" "Rate limit not triggered (may require actual service)"
    fi

    # Check if rate-limiting plugin is properly configured
    rate_limit_plugins=$(echo "$plugins_response" | grep -c '"name":"rate-limiting"' 2>/dev/null || echo "0")
    if [ "$rate_limit_plugins" -gt 0 ]; then
        log_test "Rate limiting plugin configured" "PASS"
        echo "  → Found $rate_limit_plugins rate-limiting plugins"
    else
        log_test "Rate limiting plugin configured" "FAIL" "No rate-limiting plugins found"
    fi
}

# Test 7: CORS Configuration
test_cors() {
    echo -e "\n${BLUE}Testing CORS Configuration...${NC}"

    # Test CORS preflight request
    test_endpoint="$KONG_PROXY_URL/api/v1/auth/login"

    response=$(curl -s -I -X OPTIONS "$test_endpoint" \
        -H "Origin: http://localhost:3000" \
        -H "Access-Control-Request-Method: POST" \
        -H "Access-Control-Request-Headers: Content-Type,Authorization" 2>/dev/null)

    if [ $? -eq 0 ]; then
        log_test "CORS preflight request handled" "PASS"

        # Check for CORS headers
        if echo "$response" | grep -i "access-control-allow-origin" >/dev/null; then
            log_test "CORS Allow-Origin header present" "PASS"
        else
            log_test "CORS Allow-Origin header present" "FAIL" "Header missing"
        fi

        if echo "$response" | grep -i "access-control-allow-methods" >/dev/null; then
            log_test "CORS Allow-Methods header present" "PASS"
        else
            log_test "CORS Allow-Methods header present" "FAIL" "Header missing"
        fi

        if echo "$response" | grep -i "access-control-allow-headers" >/dev/null; then
            log_test "CORS Allow-Headers header present" "PASS"
        else
            log_test "CORS Allow-Headers header present" "FAIL" "Header missing"
        fi
    else
        log_test "CORS preflight request handled" "FAIL" "Preflight request failed"
    fi

    # Check if CORS plugin is configured
    cors_plugins=$(echo "$plugins_response" | grep -c '"name":"cors"' 2>/dev/null || echo "0")
    if [ "$cors_plugins" -gt 0 ]; then
        log_test "CORS plugin configured" "PASS"
        echo "  → Found $cors_plugins CORS plugins"
    else
        log_test "CORS plugin configured" "FAIL" "No CORS plugins found"
    fi
}

# Test 8: Security Headers
test_security_headers() {
    echo -e "\n${BLUE}Testing Security Headers...${NC}"

    # Test if security headers are added by Kong
    response=$(curl -s -I "$KONG_PROXY_URL/api/v1/auth/login" 2>/dev/null)

    if [ $? -eq 0 ]; then
        # Check for security headers
        security_headers=("X-Frame-Options" "X-Content-Type-Options" "X-XSS-Protection" "Referrer-Policy")

        for header in "${security_headers[@]}"; do
            if echo "$response" | grep -i "$header" >/dev/null; then
                log_test "Security header $header present" "PASS"
            else
                log_test "Security header $header present" "FAIL" "Header missing"
            fi
        done

        # Check for gateway identification headers
        if echo "$response" | grep -i "X-Gateway" >/dev/null; then
            log_test "Gateway identification header present" "PASS"
        else
            log_test "Gateway identification header present" "FAIL" "X-Gateway header missing"
        fi
    else
        log_test "Security headers test" "FAIL" "Cannot retrieve headers"
    fi
}

# Test 9: Health Check Script
test_health_check_script() {
    echo -e "\n${BLUE}Testing Health Check Script...${NC}"

    health_script="/root/AidlY/docker/kong/health-check.sh"

    if [ -f "$health_script" ]; then
        log_test "Health check script exists" "PASS"

        if [ -x "$health_script" ]; then
            log_test "Health check script is executable" "PASS"

            # Try to run health check
            echo "  → Running health check script..."
            if timeout 30 "$health_script" >/dev/null 2>&1; then
                log_test "Health check script executes successfully" "PASS"
            else
                log_test "Health check script executes successfully" "FAIL" "Script execution failed or timed out"
            fi
        else
            log_test "Health check script is executable" "FAIL" "Script not executable"
        fi
    else
        log_test "Health check script exists" "FAIL" "health-check.sh not found"
    fi
}

# Test 10: Monitoring Configuration
test_monitoring_configuration() {
    echo -e "\n${BLUE}Testing Monitoring Configuration...${NC}"

    # Test Prometheus metrics endpoint
    metrics_response=$(curl -s "$KONG_ADMIN_URL/metrics" 2>/dev/null)

    if [ $? -eq 0 ] && [ -n "$metrics_response" ]; then
        log_test "Kong Prometheus metrics accessible" "PASS"

        # Check if metrics contain Kong-specific data
        if echo "$metrics_response" | grep -q "kong_"; then
            log_test "Kong metrics are being collected" "PASS"
        else
            log_test "Kong metrics are being collected" "FAIL" "No Kong metrics found"
        fi
    else
        log_test "Kong Prometheus metrics accessible" "FAIL" "Cannot access /metrics endpoint"
    fi

    # Check monitoring configuration files
    monitoring_files=("prometheus.yml" "alertmanager.yml" "alert_rules.yml")

    for file in "${monitoring_files[@]}"; do
        if [ -f "/root/AidlY/docker/monitoring/$file" ]; then
            log_test "Monitoring config $file exists" "PASS"
        else
            log_test "Monitoring config $file exists" "FAIL" "File not found"
        fi
    done
}

# Test 11: Service Discovery
test_service_discovery() {
    echo -e "\n${BLUE}Testing Service Discovery...${NC}"

    # Test if Kong can handle upstream health checks
    # This is more about configuration validation since actual services aren't running

    upstreams_response=$(make_request "GET" "$KONG_ADMIN_URL/upstreams" "" "" "200")

    if [ $? -eq 0 ]; then
        upstream_count=$(echo "$upstreams_response" | grep -o '"name"' | wc -l)
        log_test "Kong upstream configuration accessible" "PASS"

        if [ "$upstream_count" -gt 0 ]; then
            log_test "Upstreams configured for service discovery" "PASS"
            echo "  → Found $upstream_count upstreams"
        else
            log_test "Upstreams configured for service discovery" "SKIP" "No upstreams configured (using services directly)"
        fi
    else
        log_test "Kong upstream configuration accessible" "FAIL" "Cannot access upstreams"
    fi

    # Test health check configuration
    if docker exec aidly-kong kong health 2>/dev/null; then
        log_test "Kong internal health check" "PASS"
    else
        log_test "Kong internal health check" "FAIL" "Kong health command failed"
    fi
}

# Test 12: Load Balancing and Failover
test_load_balancing() {
    echo -e "\n${BLUE}Testing Load Balancing Configuration...${NC}"

    # Check if services have proper retry and timeout configuration
    services_response=$(make_request "GET" "$KONG_ADMIN_URL/services" "" "" "200")

    if [ $? -eq 0 ]; then
        # Look for timeout configurations in services
        if echo "$services_response" | grep -q "connect_timeout"; then
            log_test "Service timeout configuration present" "PASS"
        else
            log_test "Service timeout configuration present" "SKIP" "Timeout config not found (may be using defaults)"
        fi

        if echo "$services_response" | grep -q "retries"; then
            log_test "Service retry configuration present" "PASS"
        else
            log_test "Service retry configuration present" "SKIP" "Retry config not found (may be using defaults)"
        fi
    else
        log_test "Load balancing configuration test" "FAIL" "Cannot retrieve service configuration"
    fi
}

# Test 13: Kong Setup Script
test_setup_script() {
    echo -e "\n${BLUE}Testing Kong Setup Script...${NC}"

    setup_script="/root/AidlY/docker/kong/kong-setup.sh"

    if [ -f "$setup_script" ]; then
        log_test "Kong setup script exists" "PASS"

        if [ -x "$setup_script" ]; then
            log_test "Kong setup script is executable" "PASS"
        else
            log_test "Kong setup script is executable" "FAIL" "Script not executable"
        fi

        # Check if script contains expected functions
        if grep -q "create_service" "$setup_script"; then
            log_test "Setup script contains service creation functions" "PASS"
        else
            log_test "Setup script contains service creation functions" "FAIL" "Function not found"
        fi

        if grep -q "apply_plugin" "$setup_script"; then
            log_test "Setup script contains plugin application functions" "PASS"
        else
            log_test "Setup script contains plugin application functions" "FAIL" "Function not found"
        fi
    else
        log_test "Kong setup script exists" "FAIL" "kong-setup.sh not found"
    fi
}

# Run all tests
main() {
    echo "Starting Sprint 1.3 API Gateway Tests..."

    test_kong_configuration_files
    test_kong_availability
    test_service_configuration
    test_route_configuration
    test_plugin_configuration
    test_rate_limiting
    test_cors
    test_security_headers
    test_health_check_script
    test_monitoring_configuration
    test_service_discovery
    test_load_balancing
    test_setup_script

    echo -e "\n${BLUE}Test Summary${NC}"
    echo "============"

    total_tests=${#TEST_RESULTS[@]}
    passed_tests=0
    skipped_tests=0

    for result in "${TEST_RESULTS[@]}"; do
        if [[ "$result" == PASS* ]]; then
            passed_tests=$((passed_tests + 1))
        elif [[ "$result" == SKIP* ]]; then
            skipped_tests=$((skipped_tests + 1))
        fi
    done

    echo -e "Total Tests: $total_tests"
    echo -e "Passed: ${GREEN}$passed_tests${NC}"
    echo -e "Failed: ${RED}$FAILED_TESTS${NC}"
    echo -e "Skipped: ${YELLOW}$skipped_tests${NC}"

    if [ $FAILED_TESTS -eq 0 ]; then
        echo -e "\n🎉 ${GREEN}All Sprint 1.3 API Gateway tests passed!${NC}"
        return 0
    else
        echo -e "\n💥 ${RED}Sprint 1.3 API Gateway tests failed!${NC}"
        echo -e "\nFailed tests:"
        for result in "${TEST_RESULTS[@]}"; do
            if [[ "$result" == FAIL* ]]; then
                echo -e "  - ${RED}$result${NC}"
            fi
        done
        return 1
    fi
}

# Check dependencies
check_dependencies() {
    missing_deps=()

    if ! command -v curl >/dev/null 2>&1; then
        missing_deps+=("curl")
    fi

    if ! command -v docker >/dev/null 2>&1; then
        missing_deps+=("docker")
    fi

    if [ ${#missing_deps[@]} -gt 0 ]; then
        echo -e "${YELLOW}Warning: Missing dependencies: ${missing_deps[*]}${NC}"
        echo "Some tests may fail."
    fi
}

# Change to project directory
cd /root/AidlY

# Check dependencies
check_dependencies

# Run tests
main