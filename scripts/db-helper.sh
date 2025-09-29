#!/bin/bash

# AidlY Database Helper Script
# Provides useful commands for database management

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Database credentials
DB_HOST="localhost"
DB_PORT="5432"
DB_NAME="aidly"
DB_USER="aidly_user"
DB_PASSWORD="aidly_secret_2024"

# Navigate to project root
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR/.."

function show_menu() {
    echo -e "\n${BLUE}=== AidlY Database Helper ===${NC}"
    echo "1) Start database services"
    echo "2) Stop database services"
    echo "3) Restart database services"
    echo "4) View database logs"
    echo "5) Connect to PostgreSQL CLI"
    echo "6) Seed test data"
    echo "7) Reset database (WARNING: Deletes all data)"
    echo "8) Backup database"
    echo "9) Show connection info for DBeaver"
    echo "10) Check service status"
    echo "11) Run custom SQL query"
    echo "0) Exit"
    echo -e "${YELLOW}Choose an option:${NC} "
}

function start_services() {
    echo -e "${GREEN}Starting database services...${NC}"
    ./scripts/start-db.sh
}

function stop_services() {
    echo -e "${YELLOW}Stopping database services...${NC}"
    docker-compose down
    echo -e "${GREEN}✅ Services stopped${NC}"
}

function restart_services() {
    echo -e "${YELLOW}Restarting database services...${NC}"
    docker-compose restart postgres redis rabbitmq minio
    echo -e "${GREEN}✅ Services restarted${NC}"
}

function view_logs() {
    echo -e "${BLUE}Viewing database logs (Ctrl+C to exit)...${NC}"
    docker-compose logs -f postgres
}

function connect_psql() {
    echo -e "${BLUE}Connecting to PostgreSQL...${NC}"
    echo -e "${YELLOW}Password: ${DB_PASSWORD}${NC}"
    docker-compose exec postgres psql -U ${DB_USER} -d ${DB_NAME}
}

function seed_data() {
    echo -e "${YELLOW}Seeding test data...${NC}"

    # Check if data already exists
    EXISTS=$(docker-compose exec -T postgres psql -U ${DB_USER} -d ${DB_NAME} -t -c "SELECT COUNT(*) FROM users;" 2>/dev/null || echo "0")

    if [ "$EXISTS" -gt "0" ]; then
        echo -e "${YELLOW}⚠️  Warning: Database already contains data.${NC}"
        read -p "Do you want to continue? This may cause duplicate data (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            echo -e "${RED}Seeding cancelled${NC}"
            return
        fi
    fi

    docker-compose exec -T postgres psql -U ${DB_USER} -d ${DB_NAME} < docker/init-scripts/02-seed-test-data.sql
    echo -e "${GREEN}✅ Test data seeded successfully${NC}"
}

function reset_database() {
    echo -e "${RED}⚠️  WARNING: This will delete ALL data in the database!${NC}"
    read -p "Are you sure you want to reset the database? (y/N): " -n 1 -r
    echo

    if [[ $REPLY =~ ^[Yy]$ ]]; then
        echo -e "${YELLOW}Resetting database...${NC}"
        docker-compose down -v
        docker-compose up -d postgres
        sleep 5
        echo -e "${GREEN}✅ Database reset complete${NC}"

        read -p "Do you want to seed test data now? (y/N): " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            seed_data
        fi
    else
        echo -e "${GREEN}Reset cancelled${NC}"
    fi
}

function backup_database() {
    TIMESTAMP=$(date +%Y%m%d_%H%M%S)
    BACKUP_FILE="backups/aidly_backup_${TIMESTAMP}.sql"

    mkdir -p backups

    echo -e "${YELLOW}Creating backup...${NC}"
    docker-compose exec -T postgres pg_dump -U ${DB_USER} -d ${DB_NAME} > ${BACKUP_FILE}

    echo -e "${GREEN}✅ Backup created: ${BACKUP_FILE}${NC}"
    echo -e "${BLUE}File size: $(ls -lh ${BACKUP_FILE} | awk '{print $5}')${NC}"
}

function show_connection_info() {
    echo -e "\n${GREEN}=== DBeaver Connection Info ===${NC}"
    echo "Host: ${DB_HOST}"
    echo "Port: ${DB_PORT}"
    echo "Database: ${DB_NAME}"
    echo "Username: ${DB_USER}"
    echo "Password: ${DB_PASSWORD}"
    echo ""
    echo -e "${BLUE}=== Service URLs ===${NC}"
    echo "PostgreSQL: ${DB_HOST}:${DB_PORT}"
    echo "Redis: localhost:6379 (password: redis_secret_2024)"
    echo "RabbitMQ: http://localhost:15672 (user: aidly_admin, pass: rabbitmq_secret_2024)"
    echo "MinIO: http://localhost:9001 (user: aidly_minio_admin, pass: minio_secret_2024)"
}

function check_status() {
    echo -e "${BLUE}=== Service Status ===${NC}"
    docker-compose ps
}

function run_query() {
    echo -e "${BLUE}Enter your SQL query (end with ;):${NC}"
    read -r QUERY

    echo -e "${YELLOW}Executing query...${NC}"
    echo "${QUERY}" | docker-compose exec -T postgres psql -U ${DB_USER} -d ${DB_NAME}
}

# Main loop
while true; do
    show_menu
    read -r option

    case $option in
        1) start_services ;;
        2) stop_services ;;
        3) restart_services ;;
        4) view_logs ;;
        5) connect_psql ;;
        6) seed_data ;;
        7) reset_database ;;
        8) backup_database ;;
        9) show_connection_info ;;
        10) check_status ;;
        11) run_query ;;
        0)
            echo -e "${GREEN}Goodbye!${NC}"
            exit 0
            ;;
        *)
            echo -e "${RED}Invalid option${NC}"
            ;;
    esac

    echo -e "\n${YELLOW}Press Enter to continue...${NC}"
    read
done