#!/bin/bash

# AidlY - Comprehensive Test Runner for All Completed Sprints
# Runs all tests for Sprint 1.1, 1.2, and 1.3 to verify system integrity

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m' # No Color

# Test configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
RESULTS_DIR="$PROJECT_DIR/test-results"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")

# Global test tracking
TOTAL_SUITES=0
PASSED_SUITES=0
FAILED_SUITES=0
TOTAL_TESTS=0
TOTAL_PASSED=0
TOTAL_FAILED=0
TOTAL_SKIPPED=0

echo -e "${CYAN}${BOLD}"
echo "╔═══════════════════════════════════════════════════════╗"
echo "║                                                       ║"
echo "║    🧪 AidlY - Complete System Test Suite             ║"
echo "║                                                       ║"
echo "║    Testing all completed sprints (1.1, 1.2, 1.3)    ║"
echo "║                                                       ║"
echo "╚═══════════════════════════════════════════════════════╝"
echo -e "${NC}"

# Function to print section headers
print_header() {
    local title="$1"
    echo -e "\n${BLUE}${BOLD}$title${NC}"
    printf '%*s\n' "${#title}" '' | tr ' ' '='
}

# Function to run a test suite
run_test_suite() {
    local suite_name="$1"
    local script_path="$2"
    local description="$3"

    print_header "Running $suite_name"
    echo -e "${YELLOW}Description: $description${NC}"
    echo -e "Script: $script_path\n"

    TOTAL_SUITES=$((TOTAL_SUITES + 1))

    if [ ! -f "$script_path" ]; then
        echo -e "${RED}❌ Test script not found: $script_path${NC}"
        FAILED_SUITES=$((FAILED_SUITES + 1))
        return 1
    fi

    if [ ! -x "$script_path" ]; then
        echo -e "${YELLOW}⚠️  Making test script executable...${NC}"
        chmod +x "$script_path"
    fi

    # Create results directory
    mkdir -p "$RESULTS_DIR"

    # Run the test suite and capture output
    local log_file="$RESULTS_DIR/${suite_name}_${TIMESTAMP}.log"
    local start_time=$(date +%s)

    echo -e "📝 Logging output to: $log_file"
    echo -e "⏱️  Started at: $(date)\n"

    if "$script_path" 2>&1 | tee "$log_file"; then
        local end_time=$(date +%s)
        local duration=$((end_time - start_time))

        echo -e "\n${GREEN}✅ $suite_name PASSED${NC} (${duration}s)"
        PASSED_SUITES=$((PASSED_SUITES + 1))

        # Extract test statistics from log
        extract_test_stats "$log_file"

        return 0
    else
        local end_time=$(date +%s)
        local duration=$((end_time - start_time))

        echo -e "\n${RED}❌ $suite_name FAILED${NC} (${duration}s)"
        FAILED_SUITES=$((FAILED_SUITES + 1))

        # Extract test statistics even for failed suites
        extract_test_stats "$log_file"

        return 1
    fi
}

# Function to extract test statistics from log files
extract_test_stats() {
    local log_file="$1"

    if [ -f "$log_file" ]; then
        # Extract test counts using various patterns
        local tests=$(grep -o "Total Tests: [0-9]*" "$log_file" | grep -o "[0-9]*" | head -1)
        local passed=$(grep -o "Passed: [0-9]*" "$log_file" | grep -o "[0-9]*" | head -1)
        local failed=$(grep -o "Failed: [0-9]*" "$log_file" | grep -o "[0-9]*" | head -1)
        local skipped=$(grep -o "Skipped: [0-9]*" "$log_file" | grep -o "[0-9]*" | head -1)

        # Add to totals (with defaults of 0 if not found)
        TOTAL_TESTS=$((TOTAL_TESTS + ${tests:-0}))
        TOTAL_PASSED=$((TOTAL_PASSED + ${passed:-0}))
        TOTAL_FAILED=$((TOTAL_FAILED + ${failed:-0}))
        TOTAL_SKIPPED=$((TOTAL_SKIPPED + ${skipped:-0}))

        echo -e "  📊 Suite Stats: ${tests:-0} total, ${passed:-0} passed, ${failed:-0} failed, ${skipped:-0} skipped"
    fi
}

# Function to check prerequisites
check_prerequisites() {
    print_header "Checking Prerequisites"

    local missing_deps=()

    # Check required commands
    required_commands=("docker" "curl" "grep" "awk")

    for cmd in "${required_commands[@]}"; do
        if command -v "$cmd" >/dev/null 2>&1; then
            echo -e "✅ $cmd available"
        else
            echo -e "❌ $cmd missing"
            missing_deps+=("$cmd")
        fi
    done

    # Check if we're in the right directory
    if [ -f "$PROJECT_DIR/docker-compose.yml" ]; then
        echo -e "✅ Project directory found"
    else
        echo -e "❌ Not in AidlY project directory"
        return 1
    fi

    # Check Docker services
    echo -e "\n${YELLOW}Checking Docker services...${NC}"

    if docker compose ps --format table | grep -E "(postgres|redis|rabbitmq|minio)" | grep -q "running"; then
        echo -e "✅ Infrastructure services running"
    else
        echo -e "❌ Infrastructure services not running"
        echo -e "   Run: docker compose up -d postgres redis rabbitmq minio"
        return 1
    fi

    if docker compose ps kong | grep -q "running"; then
        echo -e "✅ Kong API Gateway running"
    else
        echo -e "⚠️  Kong API Gateway not running"
        echo -e "   Run: docker compose up -d kong-database kong-migration kong"
    fi

    if [ ${#missing_deps[@]} -gt 0 ]; then
        echo -e "\n${RED}Missing dependencies: ${missing_deps[*]}${NC}"
        return 1
    fi

    return 0
}

# Function to start required services
start_services() {
    print_header "Starting Required Services"

    echo "Starting infrastructure services..."
    if docker compose up -d postgres redis rabbitmq minio; then
        echo -e "${GREEN}✅ Infrastructure services started${NC}"
    else
        echo -e "${RED}❌ Failed to start infrastructure services${NC}"
        return 1
    fi

    echo "Waiting for infrastructure services to be ready..."
    sleep 15

    echo "Starting Kong API Gateway..."
    if docker compose up -d kong-database kong-migration kong; then
        echo -e "${GREEN}✅ Kong API Gateway started${NC}"
    else
        echo -e "${RED}❌ Failed to start Kong API Gateway${NC}"
        return 1
    fi

    echo "Waiting for Kong to be ready..."
    sleep 10

    return 0
}

# Function to generate test report
generate_report() {
    local report_file="$RESULTS_DIR/test_report_${TIMESTAMP}.md"

    print_header "Generating Test Report"

    cat > "$report_file" << EOF
# AidlY System Test Report

**Generated**: $(date)
**Test Run ID**: $TIMESTAMP

## Summary

- **Total Test Suites**: $TOTAL_SUITES
- **Passed Suites**: $PASSED_SUITES
- **Failed Suites**: $FAILED_SUITES
- **Success Rate**: $(( (PASSED_SUITES * 100) / TOTAL_SUITES ))%

## Individual Test Statistics

- **Total Individual Tests**: $TOTAL_TESTS
- **Passed Tests**: $TOTAL_PASSED
- **Failed Tests**: $TOTAL_FAILED
- **Skipped Tests**: $TOTAL_SKIPPED

## Test Suites

### Sprint 1.1 - Infrastructure Setup
- **Status**: $([ -f "$RESULTS_DIR/sprint-1.1-infrastructure_${TIMESTAMP}.log" ] && echo "Executed" || echo "Not Run")
- **Components Tested**: PostgreSQL, Redis, RabbitMQ, MinIO, Docker networking, volumes
- **Purpose**: Verify all infrastructure services are running and properly configured

### Sprint 1.2 - Authentication Foundation
- **Status**: $([ -f "$RESULTS_DIR/sprint-1.2-authentication_${TIMESTAMP}.log" ] && echo "Executed" || echo "Not Run")
- **Components Tested**: JWT authentication, user registration, login, RBAC, password reset
- **Purpose**: Verify authentication service is working with all endpoints

### Sprint 1.3 - API Gateway & Service Discovery
- **Status**: $([ -f "$RESULTS_DIR/sprint-1.3-api-gateway_${TIMESTAMP}.log" ] && echo "Executed" || echo "Not Run")
- **Components Tested**: Kong configuration, routing, rate limiting, CORS, monitoring
- **Purpose**: Verify API Gateway is properly configured and ready for microservices

## Detailed Logs

Individual test logs are available in the \`test-results\` directory:
EOF

    # Add links to log files
    for log_file in "$RESULTS_DIR"/*"${TIMESTAMP}".log; do
        if [ -f "$log_file" ]; then
            local basename=$(basename "$log_file")
            echo "- [$basename]($basename)" >> "$report_file"
        fi
    done

    cat >> "$report_file" << EOF

## Next Steps

Based on the test results:

$(if [ $FAILED_SUITES -eq 0 ]; then
    echo "🎉 **All tests passed!** The system is ready for Sprint 2.1 (Ticket Service Backend)."
else
    echo "⚠️ **Some tests failed.** Please review the failed tests and fix issues before proceeding."
fi)

### Recommendations

1. **If Infrastructure Tests Failed**: Check Docker services and database connectivity
2. **If Authentication Tests Failed**: Verify auth service is running and database is accessible
3. **If API Gateway Tests Failed**: Check Kong configuration and service routing

---

*Report generated by AidlY Test Suite*
EOF

    echo -e "📄 Test report generated: $report_file"

    # Also create a simple summary file
    cat > "$RESULTS_DIR/LATEST_RESULTS.txt" << EOF
AidlY Test Results - $(date)
================================

Suites: $PASSED_SUITES/$TOTAL_SUITES passed
Tests: $TOTAL_PASSED/$TOTAL_TESTS passed
Status: $([ $FAILED_SUITES -eq 0 ] && echo "READY FOR NEXT SPRINT" || echo "NEEDS ATTENTION")

$([ $FAILED_SUITES -eq 0 ] && echo "✅ All systems operational" || echo "❌ Issues detected - see full report")
EOF
}

# Function to display final summary
display_summary() {
    echo -e "\n${CYAN}${BOLD}"
    echo "╔═══════════════════════════════════════════════════════╗"
    echo "║                   FINAL SUMMARY                       ║"
    echo "╚═══════════════════════════════════════════════════════╝"
    echo -e "${NC}"

    echo -e "${BOLD}Test Suite Results:${NC}"
    echo -e "  Total Suites: $TOTAL_SUITES"
    echo -e "  Passed: ${GREEN}$PASSED_SUITES${NC}"
    echo -e "  Failed: ${RED}$FAILED_SUITES${NC}"

    if [ $TOTAL_TESTS -gt 0 ]; then
        echo -e "\n${BOLD}Individual Test Results:${NC}"
        echo -e "  Total Tests: $TOTAL_TESTS"
        echo -e "  Passed: ${GREEN}$TOTAL_PASSED${NC}"
        echo -e "  Failed: ${RED}$TOTAL_FAILED${NC}"
        echo -e "  Skipped: ${YELLOW}$TOTAL_SKIPPED${NC}"
    fi

    echo -e "\n${BOLD}System Status:${NC}"
    if [ $FAILED_SUITES -eq 0 ]; then
        echo -e "  ${GREEN}🎉 ALL SYSTEMS OPERATIONAL${NC}"
        echo -e "  ${GREEN}✅ Ready to proceed with Sprint 2.1${NC}"
    else
        echo -e "  ${RED}⚠️  ISSUES DETECTED${NC}"
        echo -e "  ${RED}❌ Please fix failed tests before continuing${NC}"
    fi

    echo -e "\n${BOLD}Results Location:${NC}"
    echo -e "  📁 $RESULTS_DIR"
    echo -e "  📄 See test_report_${TIMESTAMP}.md for detailed report"
}

# Main execution function
main() {
    local start_services_flag=false
    local skip_prerequisites=false

    # Parse command line arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            --start-services)
                start_services_flag=true
                shift
                ;;
            --skip-checks)
                skip_prerequisites=true
                shift
                ;;
            --help|-h)
                echo "Usage: $0 [OPTIONS]"
                echo ""
                echo "Options:"
                echo "  --start-services    Start required Docker services before testing"
                echo "  --skip-checks       Skip prerequisite checks"
                echo "  --help, -h         Show this help message"
                echo ""
                echo "This script runs comprehensive tests for all completed AidlY sprints."
                exit 0
                ;;
            *)
                echo "Unknown option: $1"
                echo "Use --help for usage information"
                exit 1
                ;;
        esac
    done

    # Change to project directory
    cd "$PROJECT_DIR"

    # Check prerequisites unless skipped
    if [ "$skip_prerequisites" = false ]; then
        if ! check_prerequisites; then
            echo -e "\n${RED}Prerequisites check failed. Fix issues above or use --skip-checks to bypass.${NC}"
            exit 1
        fi
    fi

    # Start services if requested
    if [ "$start_services_flag" = true ]; then
        if ! start_services; then
            echo -e "\n${RED}Failed to start required services.${NC}"
            exit 1
        fi
    fi

    # Run test suites
    print_header "Executing Test Suites"

    # Sprint 1.1 - Infrastructure
    run_test_suite "sprint-1.1-infrastructure" \
        "$SCRIPT_DIR/sprint-1.1-infrastructure.test.sh" \
        "Tests all infrastructure components: PostgreSQL, Redis, RabbitMQ, MinIO"

    # Sprint 1.2 - Authentication
    run_test_suite "sprint-1.2-authentication" \
        "$SCRIPT_DIR/sprint-1.2-authentication.test.sh" \
        "Tests JWT authentication service and all auth endpoints"

    # Sprint 1.3 - API Gateway
    run_test_suite "sprint-1.3-api-gateway" \
        "$SCRIPT_DIR/sprint-1.3-api-gateway.test.sh" \
        "Tests Kong API Gateway configuration and service routing"

    # Generate report
    generate_report

    # Display final summary
    display_summary

    # Exit with appropriate code
    if [ $FAILED_SUITES -eq 0 ]; then
        exit 0
    else
        exit 1
    fi
}

# Help text
if [ "$1" = "--help" ] || [ "$1" = "-h" ]; then
    main "$@"
fi

# Run main function with all arguments
main "$@"