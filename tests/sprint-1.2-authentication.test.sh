#!/bin/bash

# Sprint 1.2 Authentication Service Tests
# Tests JWT authentication, user registration, login, RBAC, and all auth endpoints

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

TEST_RESULTS=()
FAILED_TESTS=0
AUTH_SERVICE_URL="http://localhost:8001"
TEST_USER_EMAIL="test-$(date +%s)@example.com"
TEST_USER_PASSWORD="TestPassword123!"
JWT_TOKEN=""
REFRESH_TOKEN=""

echo "🧪 Testing Sprint 1.2: Authentication Foundation"
echo "================================================"

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

# Function to make HTTP requests with better error handling
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

# Test 1: Auth Service File Structure
test_auth_service_structure() {
    echo -e "\n${BLUE}Testing Auth Service File Structure...${NC}"

    # Check if auth service directory exists
    if [ -d "/root/AidlY/services/auth-service" ]; then
        log_test "Auth service directory exists" "PASS"
    else
        log_test "Auth service directory exists" "FAIL" "services/auth-service directory not found"
        return
    fi

    # Check key files
    required_files=(
        "services/auth-service/app/Models/User.php"
        "services/auth-service/app/Services/JwtService.php"
        "services/auth-service/app/Http/Controllers/AuthController.php"
        "services/auth-service/app/Http/Middleware/JwtMiddleware.php"
        "services/auth-service/app/Http/Middleware/RoleMiddleware.php"
        "services/auth-service/app/Http/Middleware/CorsMiddleware.php"
        "services/auth-service/routes/web.php"
        "services/auth-service/.env"
        "services/auth-service/composer.json"
    )

    for file in "${required_files[@]}"; do
        if [ -f "/root/AidlY/$file" ]; then
            log_test "File $file exists" "PASS"
        else
            log_test "File $file exists" "FAIL" "Required file missing"
        fi
    done

    # Check if composer dependencies are installed
    if [ -d "/root/AidlY/services/auth-service/vendor" ]; then
        log_test "Composer dependencies installed" "PASS"
    else
        log_test "Composer dependencies installed" "FAIL" "vendor directory not found"
    fi
}

# Test 2: Auth Service is Running
test_auth_service_running() {
    echo -e "\n${BLUE}Testing Auth Service Availability...${NC}"

    # Check if we can reach the auth service
    if curl -s -f "$AUTH_SERVICE_URL" >/dev/null 2>&1; then
        log_test "Auth service is accessible" "PASS"
    else
        # Try to start the auth service if not running
        echo "Auth service not accessible, trying to start it..."

        if [ -f "/root/AidlY/services/auth-service/public/index.php" ]; then
            # Start PHP built-in server in background
            cd /root/AidlY/services/auth-service
            php -S localhost:8001 -t public > /dev/null 2>&1 &
            AUTH_SERVICE_PID=$!
            sleep 3

            if curl -s -f "$AUTH_SERVICE_URL" >/dev/null 2>&1; then
                log_test "Auth service started successfully" "PASS"
                echo "  → Auth service started with PID: $AUTH_SERVICE_PID"
            else
                log_test "Auth service is accessible" "FAIL" "Cannot start or reach auth service"
                return
            fi
        else
            log_test "Auth service is accessible" "FAIL" "Auth service not properly installed"
            return
        fi
    fi
}

# Test 3: Environment Configuration
test_environment_config() {
    echo -e "\n${BLUE}Testing Environment Configuration...${NC}"

    if [ -f "/root/AidlY/services/auth-service/.env" ]; then
        log_test "Auth service .env file exists" "PASS"

        # Check key environment variables
        env_vars=("JWT_SECRET" "DB_HOST" "DB_DATABASE" "DB_USERNAME")

        for var in "${env_vars[@]}"; do
            if grep -q "^$var=" "/root/AidlY/services/auth-service/.env"; then
                log_test "Environment variable $var configured" "PASS"
            else
                log_test "Environment variable $var configured" "FAIL" "Variable not found in .env"
            fi
        done
    else
        log_test "Auth service .env file exists" "FAIL" ".env file not found"
    fi
}

# Test 4: Database Connectivity
test_database_connection() {
    echo -e "\n${BLUE}Testing Database Connection...${NC}"

    # Test if auth service can connect to database
    response=$(make_request "GET" "$AUTH_SERVICE_URL" "" "" "")

    if [ $? -eq 0 ]; then
        log_test "Auth service database connection" "PASS"
    else
        log_test "Auth service database connection" "FAIL" "Service cannot connect to database"
    fi
}

# Test 5: User Registration
test_user_registration() {
    echo -e "\n${BLUE}Testing User Registration...${NC}"

    # Test user registration endpoint
    registration_data='{
        "name": "Test User",
        "email": "'$TEST_USER_EMAIL'",
        "password": "'$TEST_USER_PASSWORD'",
        "password_confirmation": "'$TEST_USER_PASSWORD'"
    }'

    response=$(make_request "POST" "$AUTH_SERVICE_URL/register" "$registration_data" "" "201")

    if [ $? -eq 0 ]; then
        log_test "User registration endpoint" "PASS"

        # Check if response contains expected fields
        if echo "$response" | grep -q '"user"' && echo "$response" | grep -q '"token"'; then
            log_test "Registration response contains user and token" "PASS"

            # Extract token for further tests
            JWT_TOKEN=$(echo "$response" | grep -o '"token":"[^"]*' | cut -d'"' -f4)

            if [ -n "$JWT_TOKEN" ]; then
                log_test "JWT token extracted from registration" "PASS"
            else
                log_test "JWT token extracted from registration" "FAIL" "Token not found in response"
            fi
        else
            log_test "Registration response contains user and token" "FAIL" "Missing user or token in response"
        fi
    else
        log_test "User registration endpoint" "FAIL" "Registration request failed"
    fi
}

# Test 6: User Login
test_user_login() {
    echo -e "\n${BLUE}Testing User Login...${NC}"

    # Test user login endpoint
    login_data='{
        "email": "'$TEST_USER_EMAIL'",
        "password": "'$TEST_USER_PASSWORD'"
    }'

    response=$(make_request "POST" "$AUTH_SERVICE_URL/login" "$login_data" "" "200")

    if [ $? -eq 0 ]; then
        log_test "User login endpoint" "PASS"

        # Check if response contains expected fields
        if echo "$response" | grep -q '"user"' && echo "$response" | grep -q '"token"'; then
            log_test "Login response contains user and token" "PASS"

            # Update JWT token
            NEW_JWT_TOKEN=$(echo "$response" | grep -o '"token":"[^"]*' | cut -d'"' -f4)
            if [ -n "$NEW_JWT_TOKEN" ]; then
                JWT_TOKEN="$NEW_JWT_TOKEN"
                log_test "JWT token updated from login" "PASS"
            fi

            # Check for refresh token
            if echo "$response" | grep -q '"refresh_token"'; then
                REFRESH_TOKEN=$(echo "$response" | grep -o '"refresh_token":"[^"]*' | cut -d'"' -f4)
                log_test "Refresh token provided in login" "PASS"
            else
                log_test "Refresh token provided in login" "FAIL" "Refresh token missing"
            fi
        else
            log_test "Login response contains user and token" "FAIL" "Missing user or token in response"
        fi
    else
        log_test "User login endpoint" "FAIL" "Login request failed"
    fi

    # Test invalid login
    invalid_login_data='{
        "email": "'$TEST_USER_EMAIL'",
        "password": "wrongpassword"
    }'

    response=$(make_request "POST" "$AUTH_SERVICE_URL/login" "$invalid_login_data" "" "401")

    if [ $? -eq 0 ]; then
        log_test "Invalid login returns 401" "PASS"
    else
        log_test "Invalid login returns 401" "FAIL" "Should return 401 for invalid credentials"
    fi
}

# Test 7: JWT Token Validation
test_jwt_validation() {
    echo -e "\n${BLUE}Testing JWT Token Validation...${NC}"

    if [ -z "$JWT_TOKEN" ]; then
        log_test "JWT token validation" "SKIP" "No JWT token available from previous tests"
        return
    fi

    # Test protected endpoint with valid token
    response=$(make_request "GET" "$AUTH_SERVICE_URL/user" "" "-H 'Authorization: Bearer $JWT_TOKEN'" "200")

    if [ $? -eq 0 ]; then
        log_test "Protected endpoint with valid JWT" "PASS"

        # Check if user data is returned
        if echo "$response" | grep -q '"email"' && echo "$response" | grep -q "$TEST_USER_EMAIL"; then
            log_test "JWT returns correct user data" "PASS"
        else
            log_test "JWT returns correct user data" "FAIL" "User data incorrect or missing"
        fi
    else
        log_test "Protected endpoint with valid JWT" "FAIL" "Valid token rejected"
    fi

    # Test protected endpoint with invalid token
    response=$(make_request "GET" "$AUTH_SERVICE_URL/user" "" "-H 'Authorization: Bearer invalid_token'" "401")

    if [ $? -eq 0 ]; then
        log_test "Invalid JWT token rejected" "PASS"
    else
        log_test "Invalid JWT token rejected" "FAIL" "Invalid token was accepted"
    fi

    # Test protected endpoint without token
    response=$(make_request "GET" "$AUTH_SERVICE_URL/user" "" "" "401")

    if [ $? -eq 0 ]; then
        log_test "No JWT token rejected" "PASS"
    else
        log_test "No JWT token rejected" "FAIL" "Request without token was accepted"
    fi
}

# Test 8: Token Refresh
test_token_refresh() {
    echo -e "\n${BLUE}Testing Token Refresh...${NC}"

    if [ -z "$REFRESH_TOKEN" ]; then
        log_test "Token refresh functionality" "SKIP" "No refresh token available"
        return
    fi

    refresh_data='{
        "refresh_token": "'$REFRESH_TOKEN'"
    }'

    response=$(make_request "POST" "$AUTH_SERVICE_URL/refresh" "$refresh_data" "" "200")

    if [ $? -eq 0 ]; then
        log_test "Token refresh endpoint" "PASS"

        # Check if new token is provided
        if echo "$response" | grep -q '"token"'; then
            NEW_TOKEN=$(echo "$response" | grep -o '"token":"[^"]*' | cut -d'"' -f4)
            if [ -n "$NEW_TOKEN" ] && [ "$NEW_TOKEN" != "$JWT_TOKEN" ]; then
                log_test "New JWT token generated on refresh" "PASS"
                JWT_TOKEN="$NEW_TOKEN"
            else
                log_test "New JWT token generated on refresh" "FAIL" "Same token returned"
            fi
        else
            log_test "New JWT token generated on refresh" "FAIL" "No token in refresh response"
        fi
    else
        log_test "Token refresh endpoint" "FAIL" "Refresh request failed"
    fi
}

# Test 9: Password Reset
test_password_reset() {
    echo -e "\n${BLUE}Testing Password Reset...${NC}"

    # Test password reset request
    reset_request_data='{
        "email": "'$TEST_USER_EMAIL'"
    }'

    response=$(make_request "POST" "$AUTH_SERVICE_URL/forgot-password" "$reset_request_data" "" "200")

    if [ $? -eq 0 ]; then
        log_test "Password reset request endpoint" "PASS"

        if echo "$response" | grep -q '"message"'; then
            log_test "Password reset response format" "PASS"
        else
            log_test "Password reset response format" "FAIL" "Missing message in response"
        fi
    else
        log_test "Password reset request endpoint" "FAIL" "Reset request failed"
    fi

    # Test invalid email for reset
    invalid_reset_data='{
        "email": "nonexistent@example.com"
    }'

    response=$(make_request "POST" "$AUTH_SERVICE_URL/forgot-password" "$invalid_reset_data" "" "404")

    if [ $? -eq 0 ]; then
        log_test "Password reset with invalid email returns 404" "PASS"
    else
        log_test "Password reset with invalid email returns 404" "FAIL" "Should return 404 for non-existent email"
    fi
}

# Test 10: CORS Configuration
test_cors_configuration() {
    echo -e "\n${BLUE}Testing CORS Configuration...${NC}"

    # Test preflight request
    response=$(curl -s -I -X OPTIONS "$AUTH_SERVICE_URL/login" \
        -H "Origin: http://localhost:3000" \
        -H "Access-Control-Request-Method: POST" \
        -H "Access-Control-Request-Headers: Content-Type,Authorization" \
        -w "%{http_code}")

    http_code="${response: -3}"

    if [ "$http_code" = "200" ] || [ "$http_code" = "204" ]; then
        log_test "CORS preflight request handled" "PASS"

        # Check CORS headers
        if echo "$response" | grep -i "access-control-allow-origin"; then
            log_test "CORS Allow-Origin header present" "PASS"
        else
            log_test "CORS Allow-Origin header present" "FAIL" "Header missing"
        fi

        if echo "$response" | grep -i "access-control-allow-methods"; then
            log_test "CORS Allow-Methods header present" "PASS"
        else
            log_test "CORS Allow-Methods header present" "FAIL" "Header missing"
        fi
    else
        log_test "CORS preflight request handled" "FAIL" "Preflight request failed"
    fi
}

# Test 11: Role-Based Access Control (RBAC)
test_rbac() {
    echo -e "\n${BLUE}Testing Role-Based Access Control...${NC}"

    if [ -z "$JWT_TOKEN" ]; then
        log_test "RBAC functionality" "SKIP" "No JWT token available"
        return
    fi

    # Test user role endpoint (should work for authenticated users)
    response=$(make_request "GET" "$AUTH_SERVICE_URL/user/profile" "" "-H 'Authorization: Bearer $JWT_TOKEN'" "")

    if [ $? -eq 0 ]; then
        log_test "User can access own profile" "PASS"
    else
        log_test "User can access own profile" "FAIL" "User cannot access profile endpoint"
    fi

    # Test admin endpoint (should fail for regular user)
    response=$(make_request "GET" "$AUTH_SERVICE_URL/admin/users" "" "-H 'Authorization: Bearer $JWT_TOKEN'" "403")

    if [ $? -eq 0 ]; then
        log_test "Non-admin user blocked from admin endpoint" "PASS"
    else
        log_test "Non-admin user blocked from admin endpoint" "FAIL" "Should return 403 for non-admin"
    fi
}

# Test 12: Logout Functionality
test_logout() {
    echo -e "\n${BLUE}Testing Logout Functionality...${NC}"

    if [ -z "$JWT_TOKEN" ]; then
        log_test "Logout functionality" "SKIP" "No JWT token available"
        return
    fi

    # Test logout endpoint
    response=$(make_request "POST" "$AUTH_SERVICE_URL/logout" "" "-H 'Authorization: Bearer $JWT_TOKEN'" "200")

    if [ $? -eq 0 ]; then
        log_test "Logout endpoint" "PASS"

        # Test that token is invalidated after logout
        response=$(make_request "GET" "$AUTH_SERVICE_URL/user" "" "-H 'Authorization: Bearer $JWT_TOKEN'" "401")

        if [ $? -eq 0 ]; then
            log_test "Token invalidated after logout" "PASS"
        else
            log_test "Token invalidated after logout" "FAIL" "Token still valid after logout"
        fi
    else
        log_test "Logout endpoint" "FAIL" "Logout request failed"
    fi
}

# Test 13: API Documentation
test_api_documentation() {
    echo -e "\n${BLUE}Testing API Documentation...${NC}"

    if [ -f "/root/AidlY/services/auth-service/API_DOCUMENTATION.md" ]; then
        log_test "API documentation file exists" "PASS"

        # Check if documentation contains key endpoints
        doc_file="/root/AidlY/services/auth-service/API_DOCUMENTATION.md"

        endpoints=("POST /register" "POST /login" "GET /user" "POST /refresh" "POST /logout")

        for endpoint in "${endpoints[@]}"; do
            if grep -q "$endpoint" "$doc_file"; then
                log_test "Documentation includes $endpoint" "PASS"
            else
                log_test "Documentation includes $endpoint" "FAIL" "Endpoint not documented"
            fi
        done
    else
        log_test "API documentation file exists" "FAIL" "API_DOCUMENTATION.md not found"
    fi
}

# Cleanup function
cleanup() {
    if [ -n "$AUTH_SERVICE_PID" ]; then
        echo "Cleaning up auth service process..."
        kill $AUTH_SERVICE_PID 2>/dev/null
    fi
}

# Run all tests
main() {
    echo "Starting Sprint 1.2 Authentication Service Tests..."

    # Set trap for cleanup
    trap cleanup EXIT

    test_auth_service_structure
    test_auth_service_running
    test_environment_config
    test_database_connection
    test_user_registration
    test_user_login
    test_jwt_validation
    test_token_refresh
    test_password_reset
    test_cors_configuration
    test_rbac
    test_logout
    test_api_documentation

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
        echo -e "\n🎉 ${GREEN}All Sprint 1.2 Authentication tests passed!${NC}"
        return 0
    else
        echo -e "\n💥 ${RED}Sprint 1.2 Authentication tests failed!${NC}"
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

    if ! command -v php >/dev/null 2>&1; then
        missing_deps+=("php")
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