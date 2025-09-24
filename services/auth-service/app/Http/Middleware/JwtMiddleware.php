<?php

namespace App\Http\Middleware;

use Closure;
use App\Services\JwtService;

class JwtMiddleware
{
    protected $jwtService;

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $token = $this->jwtService->extractTokenFromHeader($request->header('Authorization'));

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Token not provided'
            ], 401);
        }

        $decoded = $this->jwtService->validateToken($token);

        if (!$decoded) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token'
            ], 401);
        }

        // Check if it's an access token (not refresh token)
        if ($decoded->type !== 'access') {
            return response()->json([
                'success' => false,
                'message' => 'Invalid token type'
            ], 401);
        }

        // Add user info to request
        $request->auth = $decoded;
        $request->user_id = $decoded->sub;

        return $next($request);
    }
}