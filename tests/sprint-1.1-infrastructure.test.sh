#!/bin/bash

# Sprint 1.1 Infrastructure Tests
# Tests all infrastructure components: PostgreSQL, Redis, RabbitMQ, MinIO

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

TEST_RESULTS=()
FAILED_TESTS=0

echo "🧪 Testing Sprint 1.1: Infrastructure Setup"
echo "==========================================="

# Function to log test results
log_test() {
    local test_name="$1"
    local status="$2"
    local message="$3"

    if [ "$status" = "PASS" ]; then
        echo -e "✅ ${GREEN}PASS${NC}: $test_name"
        TEST_RESULTS+=("PASS: $test_name")
    else
        echo -e "❌ ${RED}FAIL${NC}: $test_name - $message"
        TEST_RESULTS+=("FAIL: $test_name - $message")
        FAILED_TESTS=$((FAILED_TESTS + 1))
    fi
}

# Test 1: Docker Compose Configuration
test_docker_compose_config() {
    echo -e "\n${BLUE}Testing Docker Compose Configuration...${NC}"

    if [ -f "/root/AidlY/docker-compose.yml" ]; then
        log_test "Docker Compose file exists" "PASS"
    else
        log_test "Docker Compose file exists" "FAIL" "docker-compose.yml not found"
        return
    fi

    # Test if compose file is valid
    if docker compose -f /root/AidlY/docker-compose.yml config >/dev/null 2>&1; then
        log_test "Docker Compose configuration is valid" "PASS"
    else
        log_test "Docker Compose configuration is valid" "FAIL" "Invalid docker-compose.yml syntax"
    fi
}

# Test 2: PostgreSQL Database
test_postgresql() {
    echo -e "\n${BLUE}Testing PostgreSQL Database...${NC}"

    # Check if PostgreSQL container is running
    if docker compose ps postgres | grep -q "running"; then
        log_test "PostgreSQL container is running" "PASS"
    else
        log_test "PostgreSQL container is running" "FAIL" "PostgreSQL container not running"
        return
    fi

    # Test database connection
    if PGPASSWORD="${DB_PASSWORD:-aidly_secret_2024}" psql -h localhost -U aidly_user -d aidly -c "SELECT 1;" >/dev/null 2>&1; then
        log_test "PostgreSQL database connection" "PASS"
    else
        log_test "PostgreSQL database connection" "FAIL" "Cannot connect to database"
        return
    fi

    # Test if initialization scripts ran
    table_count=$(PGPASSWORD="${DB_PASSWORD:-aidly_secret_2024}" psql -h localhost -U aidly_user -d aidly -t -c "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public';" 2>/dev/null | xargs)

    if [ "$table_count" -gt 0 ]; then
        log_test "Database initialization scripts executed" "PASS"
        echo "  → Found $table_count tables in database"
    else
        log_test "Database initialization scripts executed" "FAIL" "No tables found in database"
    fi

    # Test UUID extension
    if PGPASSWORD="${DB_PASSWORD:-aidly_secret_2024}" psql -h localhost -U aidly_user -d aidly -c "SELECT gen_random_uuid();" >/dev/null 2>&1; then
        log_test "UUID extension available" "PASS"
    else
        log_test "UUID extension available" "FAIL" "UUID extension not installed"
    fi
}

# Test 3: Redis Cache
test_redis() {
    echo -e "\n${BLUE}Testing Redis Cache...${NC}"

    # Check if Redis container is running
    if docker compose ps redis | grep -q "running"; then
        log_test "Redis container is running" "PASS"
    else
        log_test "Redis container is running" "FAIL" "Redis container not running"
        return
    fi

    # Test Redis connection and authentication
    if redis-cli -h localhost -p 6379 -a "${REDIS_PASSWORD:-redis_secret_2024}" ping 2>/dev/null | grep -q "PONG"; then
        log_test "Redis connection and authentication" "PASS"
    else
        log_test "Redis connection and authentication" "FAIL" "Cannot connect to Redis or authentication failed"
        return
    fi

    # Test basic Redis operations
    test_key="test_key_$(date +%s)"
    test_value="test_value_$(date +%s)"

    if redis-cli -h localhost -p 6379 -a "${REDIS_PASSWORD:-redis_secret_2024}" SET "$test_key" "$test_value" >/dev/null 2>&1; then
        retrieved_value=$(redis-cli -h localhost -p 6379 -a "${REDIS_PASSWORD:-redis_secret_2024}" GET "$test_key" 2>/dev/null)

        if [ "$retrieved_value" = "$test_value" ]; then
            log_test "Redis SET/GET operations" "PASS"
            # Cleanup
            redis-cli -h localhost -p 6379 -a "${REDIS_PASSWORD:-redis_secret_2024}" DEL "$test_key" >/dev/null 2>&1
        else
            log_test "Redis SET/GET operations" "FAIL" "Retrieved value doesn't match"
        fi
    else
        log_test "Redis SET/GET operations" "FAIL" "Cannot perform SET operation"
    fi
}

# Test 4: RabbitMQ Message Queue
test_rabbitmq() {
    echo -e "\n${BLUE}Testing RabbitMQ Message Queue...${NC}"

    # Check if RabbitMQ container is running
    if docker compose ps rabbitmq | grep -q "running"; then
        log_test "RabbitMQ container is running" "PASS"
    else
        log_test "RabbitMQ container is running" "FAIL" "RabbitMQ container not running"
        return
    fi

    # Test RabbitMQ Management API
    rabbitmq_user="${RABBITMQ_USER:-aidly_admin}"
    rabbitmq_pass="${RABBITMQ_PASSWORD:-rabbitmq_secret_2024}"

    if curl -s -u "$rabbitmq_user:$rabbitmq_pass" "http://localhost:15672/api/overview" >/dev/null 2>&1; then
        log_test "RabbitMQ Management API accessible" "PASS"
    else
        log_test "RabbitMQ Management API accessible" "FAIL" "Cannot access management API"
        return
    fi

    # Test if default vhost exists
    vhosts=$(curl -s -u "$rabbitmq_user:$rabbitmq_pass" "http://localhost:15672/api/vhosts" 2>/dev/null)
    if echo "$vhosts" | grep -q '"name":"aidly"'; then
        log_test "RabbitMQ aidly vhost exists" "PASS"
    else
        log_test "RabbitMQ aidly vhost exists" "FAIL" "aidly vhost not found"
    fi

    # Test basic queue operations
    queue_name="test_queue_$(date +%s)"
    if curl -s -u "$rabbitmq_user:$rabbitmq_pass" -X PUT "http://localhost:15672/api/queues/aidly/$queue_name" \
        -H "Content-Type: application/json" -d '{"durable":false}' >/dev/null 2>&1; then

        # Check if queue was created
        if curl -s -u "$rabbitmq_user:$rabbitmq_pass" "http://localhost:15672/api/queues/aidly/$queue_name" >/dev/null 2>&1; then
            log_test "RabbitMQ queue creation" "PASS"
            # Cleanup
            curl -s -u "$rabbitmq_user:$rabbitmq_pass" -X DELETE "http://localhost:15672/api/queues/aidly/$queue_name" >/dev/null 2>&1
        else
            log_test "RabbitMQ queue creation" "FAIL" "Queue not found after creation"
        fi
    else
        log_test "RabbitMQ queue creation" "FAIL" "Cannot create queue"
    fi
}

# Test 5: MinIO Object Storage
test_minio() {
    echo -e "\n${BLUE}Testing MinIO Object Storage...${NC}"

    # Check if MinIO container is running
    if docker compose ps minio | grep -q "running"; then
        log_test "MinIO container is running" "PASS"
    else
        log_test "MinIO container is running" "FAIL" "MinIO container not running"
        return
    fi

    # Test MinIO health endpoint
    if curl -s -f "http://localhost:9000/minio/health/live" >/dev/null 2>&1; then
        log_test "MinIO health endpoint accessible" "PASS"
    else
        log_test "MinIO health endpoint accessible" "FAIL" "Health endpoint not responding"
        return
    fi

    # Test MinIO Console access
    if curl -s -f "http://localhost:9001" >/dev/null 2>&1; then
        log_test "MinIO Console accessible" "PASS"
    else
        log_test "MinIO Console accessible" "FAIL" "Console not accessible"
    fi

    # Test bucket creation (requires MinIO client)
    if command -v mc >/dev/null 2>&1; then
        minio_user="${MINIO_ROOT_USER:-aidly_minio_admin}"
        minio_pass="${MINIO_ROOT_PASSWORD:-minio_secret_2024}"

        # Configure mc client
        if mc alias set testminio http://localhost:9000 "$minio_user" "$minio_pass" >/dev/null 2>&1; then
            test_bucket="test-bucket-$(date +%s)"

            if mc mb "testminio/$test_bucket" >/dev/null 2>&1; then
                log_test "MinIO bucket operations" "PASS"
                # Cleanup
                mc rb "testminio/$test_bucket" >/dev/null 2>&1
            else
                log_test "MinIO bucket operations" "FAIL" "Cannot create bucket"
            fi

            # Remove test alias
            mc alias remove testminio >/dev/null 2>&1
        else
            log_test "MinIO bucket operations" "FAIL" "Cannot configure MinIO client"
        fi
    else
        log_test "MinIO bucket operations" "SKIP" "MinIO client (mc) not available"
    fi
}

# Test 6: Network Configuration
test_networking() {
    echo -e "\n${BLUE}Testing Docker Network Configuration...${NC}"

    # Check if aidly-network exists
    if docker network ls | grep -q "aidly-network"; then
        log_test "Docker network aidly-network exists" "PASS"
    else
        log_test "Docker network aidly-network exists" "FAIL" "aidly-network not found"
    fi

    # Test service discovery between containers
    if docker exec aidly-postgres ping -c 1 aidly-redis >/dev/null 2>&1; then
        log_test "Service discovery between containers" "PASS"
    else
        log_test "Service discovery between containers" "FAIL" "Cannot ping between services"
    fi
}

# Test 7: Volume Persistence
test_volumes() {
    echo -e "\n${BLUE}Testing Docker Volume Persistence...${NC}"

    required_volumes=("aidly-postgres-data" "aidly-redis-data" "aidly-rabbitmq-data" "aidly-minio-data")

    for volume in "${required_volumes[@]}"; do
        if docker volume ls | grep -q "$volume"; then
            log_test "Docker volume $volume exists" "PASS"
        else
            log_test "Docker volume $volume exists" "FAIL" "Volume not found"
        fi
    done
}

# Test 8: Environment Configuration
test_environment() {
    echo -e "\n${BLUE}Testing Environment Configuration...${NC}"

    if [ -f "/root/AidlY/.env.example" ]; then
        log_test "Environment example file exists" "PASS"

        # Check if key environment variables are documented
        required_vars=("DB_PASSWORD" "REDIS_PASSWORD" "RABBITMQ_PASSWORD" "MINIO_ROOT_PASSWORD" "JWT_SECRET")

        for var in "${required_vars[@]}"; do
            if grep -q "$var=" "/root/AidlY/.env.example"; then
                log_test "Environment variable $var documented" "PASS"
            else
                log_test "Environment variable $var documented" "FAIL" "Variable not in .env.example"
            fi
        done
    else
        log_test "Environment example file exists" "FAIL" ".env.example not found"
    fi
}

# Run all tests
main() {
    echo "Starting Sprint 1.1 Infrastructure Tests..."

    test_docker_compose_config
    test_postgresql
    test_redis
    test_rabbitmq
    test_minio
    test_networking
    test_volumes
    test_environment

    echo -e "\n${BLUE}Test Summary${NC}"
    echo "============"

    total_tests=${#TEST_RESULTS[@]}
    passed_tests=$((total_tests - FAILED_TESTS))

    echo -e "Total Tests: $total_tests"
    echo -e "Passed: ${GREEN}$passed_tests${NC}"
    echo -e "Failed: ${RED}$FAILED_TESTS${NC}"

    if [ $FAILED_TESTS -eq 0 ]; then
        echo -e "\n🎉 ${GREEN}All Sprint 1.1 Infrastructure tests passed!${NC}"
        return 0
    else
        echo -e "\n💥 ${RED}Sprint 1.1 Infrastructure tests failed!${NC}"
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

    if ! command -v docker >/dev/null 2>&1; then
        missing_deps+=("docker")
    fi

    if ! command -v psql >/dev/null 2>&1; then
        missing_deps+=("postgresql-client")
    fi

    if ! command -v redis-cli >/dev/null 2>&1; then
        missing_deps+=("redis-tools")
    fi

    if ! command -v curl >/dev/null 2>&1; then
        missing_deps+=("curl")
    fi

    if [ ${#missing_deps[@]} -gt 0 ]; then
        echo -e "${YELLOW}Warning: Missing dependencies: ${missing_deps[*]}${NC}"
        echo "Some tests may be skipped or fail."
    fi
}

# Change to project directory
cd /root/AidlY

# Check dependencies
check_dependencies

# Run tests
main