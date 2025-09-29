<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Allow internal API requests to bypass admin check
        if ($request->get('internal_request')) {
            return $next($request);
        }

        $userRole = $request->get('user_role', 'user');

        if (!in_array($userRole, ['admin', 'supervisor'])) {
            return response()->json([
                'success' => false,
                'message' => 'Admin access required'
            ], 403);
        }

        return $next($request);
    }
}