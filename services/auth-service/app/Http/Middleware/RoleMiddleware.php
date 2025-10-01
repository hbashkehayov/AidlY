<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\User;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$roles
     * @return mixed
     */
    public function handle($request, Closure $next, ...$roles)
    {
        // This middleware should be used after JwtMiddleware
        if (!isset($request->auth)) {
            \Log::warning('RoleMiddleware: No auth in request');
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $user = User::find($request->auth->sub);

        if (!$user) {
            \Log::warning('RoleMiddleware: User not found', ['user_id' => $request->auth->sub]);
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        \Log::info('RoleMiddleware: Checking role', [
            'user_id' => $user->id,
            'user_role' => $user->role,
            'required_roles' => $roles,
            'match' => in_array($user->role, $roles)
        ]);

        if (!in_array($user->role, $roles)) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient role permissions'
            ], 403);
        }

        $request->user = $user;

        return $next($request);
    }
}