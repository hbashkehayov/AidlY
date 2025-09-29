<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $role)
    {
        $user = $request->user ?? auth()->user();

        if (!$user) {
            return $this->forbiddenResponse('User not authenticated');
        }

        $userRoles = $this->getUserRoles($user);

        if (!in_array($role, $userRoles)) {
            return $this->forbiddenResponse("Role '{$role}' required");
        }

        return $next($request);
    }

    /**
     * Get user roles from user object
     */
    private function getUserRoles($user): array
    {
        if (is_array($user)) {
            return isset($user['roles']) ? (array) $user['roles'] : [($user['role'] ?? 'user')];
        }

        if (is_object($user)) {
            return isset($user->roles) ? (array) $user->roles : [($user->role ?? 'user')];
        }

        return ['user'];
    }

    /**
     * Return forbidden response
     */
    private function forbiddenResponse(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'message' => 'Forbidden',
                'details' => $message
            ]
        ], 403);
    }
}