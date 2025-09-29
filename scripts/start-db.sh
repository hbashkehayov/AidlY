#!/bin/bash

# AidlY Database Startup Script
# This script starts the PostgreSQL database and ensures it's ready for connections

set -e

echo "🚀 Starting AidlY Database Services..."
echo "=================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo -e "${RED}❌ Docker is not installed. Please install Docker first.${NC}"
    exit 1
fi

# Check if $DOCKER_COMPOSE is installed (either standalone or as Docker plugin)
if command -v $DOCKER_COMPOSE &> /dev/null; then
    DOCKER_COMPOSE="$DOCKER_COMPOSE"
elif docker compose version &> /dev/null; then
    DOCKER_COMPOSE="docker compose"
else
    echo -e "${RED}❌ $DOCKER_COMPOSE is not installed. Please install $DOCKER_COMPOSE first.${NC}"
    exit 1
fi

# Navigate to project root
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR/.."

echo -e "${YELLOW}📁 Working directory: $(pwd)${NC}"

# Start PostgreSQL container
echo -e "\n${YELLOW}🐘 Starting PostgreSQL...${NC}"
eval "$DOCKER_COMPOSE up -d postgres"

# Wait for PostgreSQL to be ready
echo -e "\n${YELLOW}⏳ Waiting for PostgreSQL to be ready...${NC}"
MAX_TRIES=30
COUNTER=0

while [ $COUNTER -lt $MAX_TRIES ]; do
    if eval "$DOCKER_COMPOSE exec -T postgres pg_isready -U aidly_user -d aidly" &>/dev/null; then
        echo -e "${GREEN}✅ PostgreSQL is ready!${NC}"
        break
    fi

    COUNTER=$((COUNTER+1))
    if [ $COUNTER -eq $MAX_TRIES ]; then
        echo -e "${RED}❌ PostgreSQL failed to start after $MAX_TRIES attempts${NC}"
        exit 1
    fi

    echo -n "."
    sleep 1
done

# Start Redis (needed for auth service)
echo -e "\n${YELLOW}🔴 Starting Redis...${NC}"
eval "$DOCKER_COMPOSE up -d redis"

# Start RabbitMQ
echo -e "\n${YELLOW}🐰 Starting RabbitMQ...${NC}"
eval "$DOCKER_COMPOSE up -d rabbitmq"

# Start MinIO
echo -e "\n${YELLOW}📦 Starting MinIO...${NC}"
eval "$DOCKER_COMPOSE up -d minio"

echo -e "\n${GREEN}✅ All services started successfully!${NC}"
echo -e "\n📊 Service Status:"
eval "$DOCKER_COMPOSE ps"

echo -e "\n${GREEN}🔗 Connection Details for DBeaver:${NC}"
echo "=================================="
echo "Host: localhost"
echo "Port: 5432"
echo "Database: aidly"
echo "Username: aidly_user"
echo "Password: aidly_secret_2024"
echo "=================================="

echo -e "\n${GREEN}🌐 Service URLs:${NC}"
echo "=================================="
echo "PostgreSQL: localhost:5432"
echo "Redis: localhost:6379"
echo "RabbitMQ Management: http://localhost:15672"
echo "MinIO Console: http://localhost:9001"
echo "=================================="

echo -e "\n${YELLOW}💡 Tips:${NC}"
echo "- To view logs: docker compose logs -f postgres"
echo "- To stop services: docker compose down"
echo "- To reset database: docker compose down -v"
echo "- To seed test data: ./scripts/seed-db.sh"