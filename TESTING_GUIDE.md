# AidlY Testing Guide - Comprehensive Test Coverage Strategy

**Target: 80%+ Test Coverage**
**Version:** 1.0.0
**Last Updated:** 2025-10-10

---

## Table of Contents

1. [Testing Philosophy](#testing-philosophy)
2. [Testing Stack](#testing-stack)
3. [Setup Instructions](#setup-instructions)
4. [Test Structure](#test-structure)
5. [Coverage Targets](#coverage-targets)
6. [Step-by-Step Testing Guide](#step-by-step-testing-guide)
7. [Test Examples](#test-examples)
8. [Running Tests](#running-tests)
9. [CI/CD Integration](#cicd-integration)
10. [Troubleshooting](#troubleshooting)

---

## 1. Testing Philosophy

### Testing Pyramid Strategy

```
                 ▲
                / \
               /   \
              / E2E \         10% - End-to-End Tests
             /-------\
            /         \
           / Integration\    30% - Integration Tests
          /-------------\
         /               \
        /   Unit Tests    \  60% - Unit Tests
       /___________________\
```

### Coverage Goals by Component

| Component | Target Coverage | Priority | Estimated Time |
|-----------|----------------|----------|----------------|
| **Backend Models** | 90% | High | 2 days |
| **Backend Controllers** | 80% | High | 3 days |
| **Backend Services** | 85% | High | 2 days |
| **API Endpoints** | 80% | High | 3 days |
| **Frontend Components** | 75% | Medium | 3 days |
| **Frontend Hooks** | 80% | Medium | 2 days |
| **Integration Flows** | 70% | High | 3 days |

**Total Estimated Time: 18 days** (with 2-3 developers)

---

## 2. Testing Stack

### Backend (PHP/Lumen)

```json
{
  "testing_framework": "PHPUnit 10.x",
  "mocking": "Mockery",
  "database": "SQLite (in-memory for tests)",
  "fixtures": "Laravel Database Factories",
  "coverage": "PHPUnit Code Coverage (Xdebug/PCOV)"
}
```

### Frontend (Next.js/TypeScript)

```json
{
  "testing_framework": "Jest 29.x",
  "react_testing": "@testing-library/react 14.x",
  "e2e": "Playwright",
  "mocking": "MSW (Mock Service Worker)",
  "coverage": "Istanbul/NYC"
}
```

---

## 3. Setup Instructions

### A. Backend Testing Setup (Per Microservice)

#### Step 1: Install PHPUnit and Dependencies

```bash
cd /root/AidlY/services/ticket-service

# Install testing dependencies
composer require --dev phpunit/phpunit:^10.0
composer require --dev mockery/mockery:^1.5
composer require --dev fakerphp/faker:^1.21
```

#### Step 2: Create PHPUnit Configuration

Create `phpunit.xml` in service root:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="./vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         failOnRisky="true"
         failOnWarning="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>
    <coverage>
        <include>
            <directory suffix=".php">app</directory>
        </include>
        <exclude>
            <directory>app/Http/Middleware</directory>
            <file>app/Http/Controllers/Controller.php</file>
        </exclude>
        <report>
            <html outputDirectory="coverage-report/html"/>
            <clover outputFile="coverage-report/clover.xml"/>
            <text outputFile="php://stdout" showUncoveredFiles="false"/>
        </report>
    </coverage>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
        <env name="CACHE_DRIVER" value="array"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
        <env name="SESSION_DRIVER" value="array"/>
    </php>
</phpunit>
```

#### Step 3: Create Test Directory Structure

```bash
mkdir -p tests/{Unit/{Models,Services},Feature/Api,Integration}
mkdir -p tests/Fixtures
```

#### Step 4: Create Base Test Case

Create `tests/TestCase.php`:

```php
<?php

namespace Tests;

use Laravel\Lumen\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * Creates the application.
     */
    public function createApplication()
    {
        return require __DIR__.'/../bootstrap/app.php';
    }

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Run migrations
        $this->artisan('migrate');
    }

    /**
     * Clean up after test.
     */
    protected function tearDown(): void
    {
        // Clear database
        DB::disconnect();

        parent::tearDown();
    }

    /**
     * Create authenticated user for testing
     */
    protected function actingAsUser($role = 'agent', $attributes = [])
    {
        $user = factory(\App\Models\User::class)->create(array_merge([
            'role' => $role
        ], $attributes));

        $token = \App\Services\JwtService::generateToken($user);

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ]);

        return $user;
    }
}
```

### B. Frontend Testing Setup

#### Step 1: Install Testing Dependencies

```bash
cd /root/AidlY/frontend

npm install --save-dev \
  @testing-library/react \
  @testing-library/jest-dom \
  @testing-library/user-event \
  jest \
  jest-environment-jsdom \
  @types/jest \
  msw
```

#### Step 2: Create Jest Configuration

Create `jest.config.js`:

```javascript
const nextJest = require('next/jest')

const createJestConfig = nextJest({
  // Provide the path to your Next.js app to load next.config.js and .env files
  dir: './',
})

const customJestConfig = {
  setupFilesAfterEnv: ['<rootDir>/jest.setup.js'],
  testEnvironment: 'jest-environment-jsdom',
  moduleNameMapper: {
    '^@/(.*)$': '<rootDir>/$1',
  },
  collectCoverageFrom: [
    'app/**/*.{js,jsx,ts,tsx}',
    'components/**/*.{js,jsx,ts,tsx}',
    'lib/**/*.{js,jsx,ts,tsx}',
    'hooks/**/*.{js,jsx,ts,tsx}',
    '!**/*.d.ts',
    '!**/node_modules/**',
    '!**/.next/**',
  ],
  coverageThreshold: {
    global: {
      statements: 75,
      branches: 70,
      functions: 75,
      lines: 75,
    },
  },
}

module.exports = createJestConfig(customJestConfig)
```

#### Step 3: Create Jest Setup File

Create `jest.setup.js`:

```javascript
import '@testing-library/jest-dom'

// Mock next/navigation
jest.mock('next/navigation', () => ({
  useRouter() {
    return {
      push: jest.fn(),
      replace: jest.fn(),
      prefetch: jest.fn(),
    }
  },
  usePathname() {
    return ''
  },
  useSearchParams() {
    return new URLSearchParams()
  },
}))

// Mock localStorage
const localStorageMock = {
  getItem: jest.fn(),
  setItem: jest.fn(),
  removeItem: jest.fn(),
  clear: jest.fn(),
}
global.localStorage = localStorageMock

// Mock window.matchMedia
Object.defineProperty(window, 'matchMedia', {
  writable: true,
  value: jest.fn().mockImplementation(query => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: jest.fn(),
    removeListener: jest.fn(),
    addEventListener: jest.fn(),
    removeEventListener: jest.fn(),
    dispatchEvent: jest.fn(),
  })),
})
```

---

## 4. Test Structure

### Backend Test Organization

```
services/ticket-service/tests/
├── Unit/                           # 60% of tests
│   ├── Models/
│   │   ├── TicketTest.php         # Model logic, scopes, relationships
│   │   ├── TicketCommentTest.php
│   │   └── ClientTest.php
│   ├── Services/
│   │   ├── NotificationServiceTest.php
│   │   ├── TicketAssignmentServiceTest.php
│   │   └── EmailServiceTest.php
│   └── Helpers/
│       └── UtilityFunctionsTest.php
├── Feature/                        # 30% of tests
│   └── Api/
│       ├── TicketApiTest.php      # API endpoints, auth, validation
│       ├── CommentApiTest.php
│       └── AssignmentApiTest.php
├── Integration/                    # 10% of tests
│   ├── EmailToTicketFlowTest.php  # Multi-service workflows
│   ├── TicketAssignmentFlowTest.php
│   └── NotificationFlowTest.php
├── Fixtures/                       # Test data
│   ├── TicketFactory.php
│   └── UserFactory.php
└── TestCase.php                    # Base test class
```

### Frontend Test Organization

```
frontend/
├── __tests__/
│   ├── components/                 # Component tests (60%)
│   │   ├── ticket/
│   │   │   ├── reply-thread-history.test.tsx
│   │   │   └── ai-suggestions-panel.test.tsx
│   │   └── ui/
│   │       ├── button.test.tsx
│   │       └── dialog.test.tsx
│   ├── hooks/                      # Custom hooks tests (20%)
│   │   ├── use-notifications.test.ts
│   │   └── use-realtime-updates.test.ts
│   ├── lib/                        # Utility tests (10%)
│   │   ├── api.test.ts
│   │   └── utils.test.ts
│   └── integration/                # E2E tests (10%)
│       ├── ticket-workflow.test.tsx
│       └── auth-flow.test.tsx
├── __mocks__/
│   ├── handlers.ts                 # MSW request handlers
│   └── server.ts                   # MSW server setup
└── jest.setup.js
```

---

## 5. Coverage Targets

### Minimum Coverage Requirements

```yaml
Overall Project: 80%

Backend Services:
  - Models: 90%
  - Controllers: 80%
  - Services: 85%
  - Middleware: 75%
  - Routes: 80%

Frontend:
  - Components: 75%
  - Hooks: 80%
  - Utils: 85%
  - API Layer: 80%

Integration:
  - Critical Flows: 70%
```

### Coverage Exclusions (Acceptable)

- Third-party library wrappers
- Generated code
- Configuration files
- Migration files
- Seeders
- Simple getters/setters

---

## 6. Step-by-Step Testing Guide

### Phase 1: Backend Unit Tests (Days 1-4)

#### Day 1: Model Tests

**Goal:** Test Eloquent models, relationships, scopes

**Tasks:**
1. Test Ticket model (see example in next section)
2. Test TicketComment model
3. Test Client model
4. Test Category model

**Success Criteria:**
- All model methods tested
- Relationships verified
- Scopes work correctly
- Model events trigger properly

#### Day 2: Service Tests

**Goal:** Test business logic in service classes

**Tasks:**
1. Test NotificationService
2. Test TicketAssignmentService
3. Test EmailProcessingService

**Success Criteria:**
- Service methods tested in isolation
- External dependencies mocked
- Edge cases covered

#### Day 3-4: Controller Tests (Feature Tests)

**Goal:** Test API endpoints, validation, responses

**Tasks:**
1. Test TicketController endpoints
2. Test AuthController endpoints
3. Test ClientController endpoints

**Success Criteria:**
- All endpoints return correct status codes
- Validation works properly
- Authentication enforced
- Response structure correct

### Phase 2: Frontend Tests (Days 5-9)

#### Day 5-6: Component Tests

**Goal:** Test React components render and behave correctly

**Tasks:**
1. Test ticket list page
2. Test ticket detail page
3. Test reply thread component
4. Test AI suggestions component

**Success Criteria:**
- Components render without errors
- User interactions work
- Props passed correctly
- State updates properly

#### Day 7-8: Hook Tests

**Goal:** Test custom React hooks

**Tasks:**
1. Test useNotifications hook
2. Test useRealtimeUpdates hook
3. Test useToast hook

**Success Criteria:**
- Hooks return expected values
- Side effects work correctly
- Cleanup functions run

#### Day 9: Utility & API Tests

**Goal:** Test utility functions and API layer

**Tasks:**
1. Test API client (axios instances, interceptors)
2. Test utility functions (formatters, validators)

**Success Criteria:**
- API calls structured correctly
- Auth tokens attached
- Error handling works

### Phase 3: Integration Tests (Days 10-12)

#### Day 10-11: Critical Flow Tests

**Goal:** Test end-to-end workflows

**Tasks:**
1. Email → Ticket creation flow
2. Ticket assignment → Notification flow
3. Agent reply → Email send flow

**Success Criteria:**
- Multiple services work together
- Data flows correctly
- Error handling robust

#### Day 12: E2E Tests (Frontend)

**Goal:** Test user journeys in browser

**Tasks:**
1. Login → Dashboard → View Ticket
2. Claim Ticket → Reply → Mark Resolved
3. Admin: Create User → Assign Ticket

**Success Criteria:**
- User can complete tasks
- UI responds correctly
- Data persists

### Phase 4: Coverage & Refinement (Days 13-14)

#### Day 13: Measure Coverage

**Run coverage reports:**
```bash
# Backend
cd services/ticket-service
vendor/bin/phpunit --coverage-html coverage-report

# Frontend
cd frontend
npm run test:coverage
```

**Identify gaps:**
- Lines not covered
- Branches not tested
- Edge cases missed

#### Day 14: Fill Gaps & Refine

**Write additional tests for:**
- Uncovered code paths
- Error scenarios
- Edge cases
- Complex business logic

---

## 7. Test Examples

### Example 1: Model Unit Test (Backend)

See: `/root/AidlY/services/ticket-service/tests/Unit/Models/TicketTest.php`

### Example 2: API Feature Test (Backend)

See: `/root/AidlY/services/ticket-service/tests/Feature/Api/TicketApiTest.php`

### Example 3: Integration Test (Backend)

See: `/root/AidlY/services/ticket-service/tests/Integration/EmailToTicketFlowTest.php`

### Example 4: Component Test (Frontend)

See: `/root/AidlY/frontend/__tests__/components/ticket/reply-thread-history.test.tsx`

### Example 5: Hook Test (Frontend)

See: `/root/AidlY/frontend/__tests__/hooks/use-notifications.test.ts`

---

## 8. Running Tests

### Backend Tests

```bash
# Run all tests
cd services/ticket-service
vendor/bin/phpunit

# Run specific test suite
vendor/bin/phpunit --testsuite=Unit
vendor/bin/phpunit --testsuite=Feature
vendor/bin/phpunit --testsuite=Integration

# Run specific test file
vendor/bin/phpunit tests/Unit/Models/TicketTest.php

# Run with coverage
vendor/bin/phpunit --coverage-html coverage-report
vendor/bin/phpunit --coverage-text

# Run tests in parallel (faster)
composer require --dev brianium/paratest
vendor/bin/paratest --processes=4
```

### Frontend Tests

```bash
# Run all tests
npm test

# Run in watch mode (development)
npm run test:watch

# Run with coverage
npm run test:coverage

# Run specific test file
npm test -- reply-thread-history.test.tsx

# Update snapshots
npm test -- -u
```

### All Services (Script)

Create `/root/AidlY/run-all-tests.sh`:

```bash
#!/bin/bash

echo "Running all tests for AidlY..."

# Backend services
SERVICES=("auth-service" "ticket-service" "client-service" "email-service" "notification-service" "ai-integration-service" "analytics-service")

for service in "${SERVICES[@]}"; do
    echo "Testing $service..."
    cd "services/$service"
    vendor/bin/phpunit || exit 1
    cd ../..
done

# Frontend tests
echo "Testing frontend..."
cd frontend
npm test -- --coverage || exit 1
cd ..

echo "All tests passed!"
```

---

## 9. CI/CD Integration

### GitHub Actions Workflow

Create `.github/workflows/tests.yml`:

```yaml
name: Run Tests

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main, develop ]

jobs:
  backend-tests:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        service: [auth-service, ticket-service, client-service, email-service]
    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: mbstring, pdo, pdo_sqlite
          coverage: xdebug

      - name: Install dependencies
        working-directory: ./services/${{ matrix.service }}
        run: composer install --prefer-dist --no-progress

      - name: Run tests
        working-directory: ./services/${{ matrix.service }}
        run: vendor/bin/phpunit --coverage-clover coverage.xml

      - name: Upload coverage
        uses: codecov/codecov-action@v3
        with:
          file: ./services/${{ matrix.service }}/coverage.xml
          flags: ${{ matrix.service }}

  frontend-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3

      - name: Setup Node.js
        uses: actions/setup-node@v3
        with:
          node-version: '18'
          cache: 'npm'
          cache-dependency-path: frontend/package-lock.json

      - name: Install dependencies
        working-directory: ./frontend
        run: npm ci

      - name: Run tests
        working-directory: ./frontend
        run: npm run test:coverage

      - name: Upload coverage
        uses: codecov/codecov-action@v3
        with:
          file: ./frontend/coverage/coverage-final.json
          flags: frontend
```

---

## 10. Troubleshooting

### Common Issues

#### Issue 1: "Class not found" in tests

**Solution:**
```bash
# Regenerate autoload files
composer dump-autoload
```

#### Issue 2: Database migrations failing in tests

**Solution:**
```php
// In TestCase.php setUp()
$this->artisan('migrate:fresh');
```

#### Issue 3: Frontend tests timing out

**Solution:**
```javascript
// Increase timeout in jest.config.js
testTimeout: 10000, // 10 seconds
```

#### Issue 4: Mock Service Worker not intercepting requests

**Solution:**
```javascript
// In jest.setup.js
import { server } from './__mocks__/server'

beforeAll(() => server.listen())
afterEach(() => server.resetHandlers())
afterAll(() => server.close())
```

---

## Appendix: Test Coverage Cheat Sheet

### What to Test

✅ **DO Test:**
- Business logic
- Data transformations
- API endpoints
- User interactions
- Edge cases
- Error handling
- Validation rules
- State management

❌ **DON'T Test:**
- Third-party libraries
- Simple getters/setters
- Framework code
- Configuration files
- Generated code

### Test Naming Convention

```php
// Backend (PHPUnit)
test_it_creates_ticket_with_valid_data()
test_it_fails_validation_with_missing_subject()
test_it_assigns_ticket_to_agent()
```

```typescript
// Frontend (Jest)
it('renders ticket list correctly')
it('shows error message when API fails')
it('calls onSubmit when form is valid')
```

### Coverage Commands Quick Reference

```bash
# Backend
vendor/bin/phpunit --coverage-text              # Console output
vendor/bin/phpunit --coverage-html coverage     # HTML report
vendor/bin/phpunit --coverage-clover coverage.xml  # XML for CI

# Frontend
npm run test:coverage                           # Full report
npm test -- --coverage --collectCoverageFrom='lib/**/*.ts'  # Specific files
```

---

## Next Steps

1. **Start with backend models** - easiest to test, highest value
2. **Move to API endpoints** - integration between layers
3. **Add frontend component tests** - user-facing features
4. **Fill in integration tests** - end-to-end flows
5. **Iterate until 80% coverage** - focus on critical paths first

**Remember:** Quality > Quantity. Aim for meaningful tests that catch real bugs, not just increasing coverage numbers.

---

**Questions or Issues?** Create an issue in the repository or contact the development team.
