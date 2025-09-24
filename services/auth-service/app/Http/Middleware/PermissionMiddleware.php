<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\User;

class PermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $resource
     * @param  string  $action
     * @return mixed
     */
    public function handle($request, Closure $next, $resource, $action)
    {
        // This middleware should be used after JwtMiddleware
        if (!isset($request->auth)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $user = User::find($request->auth->sub);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Super admin bypasses all permission checks
        if ($user->role === 'admin') {
            $request->user = $user;
            return $next($request);
        }

        if (!$user->hasPermission($resource, $action)) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient permissions for this action'
            ], 403);
        }

        $request->user = $user;

        return $next($request);
    }
}