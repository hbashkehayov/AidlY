<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\JwtService;

class SessionController extends Controller
{
    protected $jwtService;

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    /**
     * Get all active sessions for the current user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $token = $this->jwtService->extractTokenFromHeader($request->header('Authorization'));
        $user = $this->jwtService->getUserFromToken($token);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // In a real implementation, this would fetch from session storage
        $sessions = [
            [
                'id' => 'session-1',
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
                'last_activity' => now()->toIso8601String(),
                'is_current' => true
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $sessions
        ]);
    }

    /**
     * Destroy a specific session
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        // In a real implementation, this would invalidate the specific session
        return response()->json([
            'success' => true,
            'message' => 'Session terminated successfully'
        ]);
    }

    /**
     * Destroy all sessions for the current user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroyAll(Request $request)
    {
        $token = $this->jwtService->extractTokenFromHeader($request->header('Authorization'));
        $user = $this->jwtService->getUserFromToken($token);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // In a real implementation, this would invalidate all sessions
        return response()->json([
            'success' => true,
            'message' => 'All sessions terminated successfully'
        ]);
    }
}