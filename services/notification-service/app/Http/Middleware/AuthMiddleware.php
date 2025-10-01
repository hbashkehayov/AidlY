<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuthMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Check for JWT token in Authorization header
        $token = $request->bearerToken();

        if (!$token) {
            // Check for API key in headers for internal services
            $apiKey = $request->header('X-API-Key');
            if (!$apiKey || !in_array($apiKey, ['test-api-key-2024', 'default-api-key', env('INTERNAL_API_KEY')])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }

            // Set internal service flag
            $request->merge(['internal_request' => true]);
            return $next($request);
        }

        // For JWT validation, we'd normally validate against auth service
        // For now, basic validation to extract user ID from token
        try {
            $payload = $this->decodeJwtPayload($token);

            // Extract role from either payload.user.role or payload.role
            $userRole = $payload['user']['role'] ?? $payload['role'] ?? 'user';

            $request->merge([
                'user_id' => $payload['sub'] ?? null,
                'user_role' => $userRole
            ]);

            return $next($request);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid token'
            ], 401);
        }
    }

    /**
     * Simple JWT payload extraction (for demonstration)
     * In production, use proper JWT validation with auth service
     */
    private function decodeJwtPayload($token)
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new \Exception('Invalid token format');
        }

        $payload = json_decode(base64_decode($parts[1]), true);
        if (!$payload) {
            throw new \Exception('Invalid token payload');
        }

        return $payload;
    }
}