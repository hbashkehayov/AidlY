# AidlY Authentication Service API Documentation

## Base URL
```
http://localhost:8001
```

## Authentication
Most endpoints require JWT authentication. Include the token in the Authorization header:
```
Authorization: Bearer <your-jwt-token>
```

## API Endpoints

### Public Endpoints

#### Register User
- **POST** `/api/v1/auth/register`
- **Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "role": "agent" // optional: admin, agent, supervisor, customer
}
```
- **Response:** 201
```json
{
  "success": true,
  "message": "User successfully registered",
  "user": {
    "id": "uuid",
    "name": "John Doe",
    "email": "john@example.com",
    "role": "agent"
  },
  "access_token": "jwt-token",
  "refresh_token": "refresh-jwt-token",
  "token_type": "Bearer",
  "expires_in": 3600
}
```

#### Login
- **POST** `/api/v1/auth/login`
- **Body:**
```json
{
  "email": "john@example.com",
  "password": "password123"
}
```
- **Response:** 200
```json
{
  "success": true,
  "message": "Login successful",
  "user": {
    "id": "uuid",
    "name": "John Doe",
    "email": "john@example.com",
    "role": "agent"
  },
  "access_token": "jwt-token",
  "refresh_token": "refresh-jwt-token",
  "token_type": "Bearer",
  "expires_in": 3600
}
```

#### Refresh Token
- **POST** `/api/v1/auth/refresh`
- **Body:**
```json
{
  "refresh_token": "refresh-jwt-token"
}
```
- **Response:** 200
```json
{
  "success": true,
  "message": "Token refreshed successfully",
  "access_token": "new-jwt-token",
  "refresh_token": "new-refresh-token",
  "token_type": "Bearer",
  "expires_in": 3600
}
```

#### Forgot Password
- **POST** `/api/v1/auth/forgot-password`
- **Body:**
```json
{
  "email": "john@example.com"
}
```
- **Response:** 200
```json
{
  "success": true,
  "message": "If the email exists, a password reset link has been sent."
}
```

#### Reset Password
- **POST** `/api/v1/auth/reset-password`
- **Body:**
```json
{
  "token": "reset-token",
  "email": "john@example.com",
  "password": "newpassword123",
  "password_confirmation": "newpassword123"
}
```
- **Response:** 200
```json
{
  "success": true,
  "message": "Password has been reset successfully"
}
```

### Protected Endpoints (Require JWT)

#### Logout
- **POST** `/api/v1/auth/logout`
- **Headers:** `Authorization: Bearer <token>`
- **Response:** 200
```json
{
  "success": true,
  "message": "Successfully logged out"
}
```

#### Get Current User
- **GET** `/api/v1/auth/me`
- **Headers:** `Authorization: Bearer <token>`
- **Response:** 200
```json
{
  "success": true,
  "user": {
    "id": "uuid",
    "name": "John Doe",
    "email": "john@example.com",
    "role": "agent",
    "department_id": null,
    "avatar_url": null,
    "email_verified_at": null,
    "two_factor_enabled": false,
    "is_active": true,
    "last_login_at": "2024-01-01T12:00:00Z",
    "created_at": "2024-01-01T10:00:00Z",
    "updated_at": "2024-01-01T12:00:00Z"
  }
}
```

#### Change Password
- **POST** `/api/v1/auth/change-password`
- **Headers:** `Authorization: Bearer <token>`
- **Body:**
```json
{
  "current_password": "oldpassword123",
  "new_password": "newpassword123",
  "new_password_confirmation": "newpassword123"
}
```
- **Response:** 200
```json
{
  "success": true,
  "message": "Password changed successfully"
}
```

### User Management (Admin/Supervisor Only)

#### List Users
- **GET** `/api/v1/users`
- **Headers:** `Authorization: Bearer <token>`
- **Query Parameters:**
  - `page`: Page number (default: 1)
  - `limit`: Items per page (default: 20)
  - `role`: Filter by role
  - `is_active`: Filter by active status
- **Response:** 200
```json
{
  "success": true,
  "data": [/* array of users */],
  "meta": {
    "page": 1,
    "limit": 20,
    "total": 100
  }
}
```

#### Get User
- **GET** `/api/v1/users/{id}`
- **Headers:** `Authorization: Bearer <token>`
- **Response:** 200

#### Create User
- **POST** `/api/v1/users`
- **Headers:** `Authorization: Bearer <token>`
- **Body:** Same as register

#### Update User
- **PUT** `/api/v1/users/{id}`
- **Headers:** `Authorization: Bearer <token>`
- **Body:**
```json
{
  "name": "Updated Name",
  "email": "newemail@example.com",
  "role": "supervisor"
}
```

#### Delete User
- **DELETE** `/api/v1/users/{id}`
- **Headers:** `Authorization: Bearer <token>`

#### Activate User
- **POST** `/api/v1/users/{id}/activate`
- **Headers:** `Authorization: Bearer <token>`

#### Deactivate User
- **POST** `/api/v1/users/{id}/deactivate`
- **Headers:** `Authorization: Bearer <token>`

#### Unlock User
- **POST** `/api/v1/users/{id}/unlock`
- **Headers:** `Authorization: Bearer <token>`

### Role & Permission Management (Admin Only)

#### List Roles
- **GET** `/api/v1/roles`
- **Headers:** `Authorization: Bearer <token>`

#### Get Role Permissions
- **GET** `/api/v1/roles/{role}/permissions`
- **Headers:** `Authorization: Bearer <token>`

#### Assign Permissions to Role
- **POST** `/api/v1/roles/{role}/permissions`
- **Headers:** `Authorization: Bearer <token>`
- **Body:**
```json
{
  "permission_ids": ["uuid1", "uuid2"]
}
```

#### Remove Permission from Role
- **DELETE** `/api/v1/roles/{role}/permissions/{permissionId}`
- **Headers:** `Authorization: Bearer <token>`

### Session Management

#### List User Sessions
- **GET** `/api/v1/sessions`
- **Headers:** `Authorization: Bearer <token>`

#### Destroy Session
- **DELETE** `/api/v1/sessions/{id}`
- **Headers:** `Authorization: Bearer <token>`

#### Destroy All Sessions
- **DELETE** `/api/v1/sessions/all`
- **Headers:** `Authorization: Bearer <token>`

### Two-Factor Authentication

#### Enable 2FA
- **POST** `/api/v1/2fa/enable`
- **Headers:** `Authorization: Bearer <token>`

#### Disable 2FA
- **POST** `/api/v1/2fa/disable`
- **Headers:** `Authorization: Bearer <token>`
- **Body:**
```json
{
  "password": "user-password"
}
```

#### Verify 2FA Code
- **POST** `/api/v1/2fa/verify`
- **Headers:** `Authorization: Bearer <token>`
- **Body:**
```json
{
  "code": "123456"
}
```

#### Get Recovery Codes
- **GET** `/api/v1/2fa/recovery-codes`
- **Headers:** `Authorization: Bearer <token>`

#### Regenerate Recovery Codes
- **POST** `/api/v1/2fa/recovery-codes/regenerate`
- **Headers:** `Authorization: Bearer <token>`

## Error Responses

### 400 Bad Request
```json
{
  "success": false,
  "message": "Error description"
}
```

### 401 Unauthorized
```json
{
  "success": false,
  "message": "Unauthorized"
}
```

### 403 Forbidden
```json
{
  "success": false,
  "message": "Insufficient permissions"
}
```

### 404 Not Found
```json
{
  "success": false,
  "message": "Resource not found"
}
```

### 422 Validation Error
```json
{
  "success": false,
  "errors": {
    "field": ["Error message"]
  }
}
```

### 423 Locked
```json
{
  "success": false,
  "message": "Account is locked due to too many failed login attempts"
}
```

### 500 Server Error
```json
{
  "success": false,
  "message": "Internal server error"
}
```

## Rate Limiting

- Default: 60 requests per minute
- Authentication endpoints: 5 requests per minute for login/register

## Security Features

1. **JWT Tokens**
   - Access token expires in 1 hour
   - Refresh token expires in 2 weeks
   - Token blacklisting on logout

2. **Account Security**
   - Account lockout after 5 failed login attempts (30 minutes)
   - Password complexity requirements (min 8 characters)
   - Two-factor authentication support

3. **RBAC (Role-Based Access Control)**
   - Roles: admin, supervisor, agent, customer
   - Granular permission system
   - Middleware protection for routes

4. **Session Management**
   - Track active sessions
   - Remote session termination
   - Session expiry management

## Testing the API

### Using cURL

```bash
# Register
curl -X POST http://localhost:8001/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Test User","email":"test@example.com","password":"password123","password_confirmation":"password123"}'

# Login
curl -X POST http://localhost:8001/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password123"}'

# Get current user
curl -X GET http://localhost:8001/api/v1/auth/me \
  -H "Authorization: Bearer <your-token>"
```

### Using Postman

Import the following environment variables:
- `base_url`: http://localhost:8001
- `token`: (set after login)

Create requests for each endpoint and use `{{base_url}}` and `Bearer {{token}}` for authentication.