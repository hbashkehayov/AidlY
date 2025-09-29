<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\JwtService;
use Illuminate\Support\Str;

class TwoFactorController extends Controller
{
    protected $jwtService;

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    /**
     * Enable two-factor authentication
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function enable(Request $request)
    {
        $token = $this->jwtService->extractTokenFromHeader($request->header('Authorization'));
        $user = $this->jwtService->getUserFromToken($token);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // In a real implementation, this would generate TOTP secret
        $secret = base64_encode(Str::random(32));

        return response()->json([
            'success' => true,
            'message' => 'Two-factor authentication enabled',
            'data' => [
                'secret' => $secret,
                'qr_code' => 'data:image/svg+xml;base64,...', // QR code would be generated here
                'recovery_codes' => [
                    Str::random(10),
                    Str::random(10),
                    Str::random(10),
                    Str::random(10),
                    Str::random(10),
                    Str::random(10),
                    Str::random(10),
                    Str::random(10),
                ]
            ]
        ]);
    }

    /**
     * Disable two-factor authentication
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function disable(Request $request)
    {
        $token = $this->jwtService->extractTokenFromHeader($request->header('Authorization'));
        $user = $this->jwtService->getUserFromToken($token);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $user->two_factor_enabled = false;
        $user->two_factor_secret = null;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Two-factor authentication disabled'
        ]);
    }

    /**
     * Verify two-factor authentication code
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6'
        ]);

        // In a real implementation, this would verify TOTP code
        $isValid = $request->code === '123456'; // Mock verification

        if (!$isValid) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification code'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Code verified successfully'
        ]);
    }

    /**
     * Get recovery codes
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRecoveryCodes(Request $request)
    {
        $token = $this->jwtService->extractTokenFromHeader($request->header('Authorization'));
        $user = $this->jwtService->getUserFromToken($token);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // In a real implementation, this would fetch from database
        $codes = [
            Str::random(10),
            Str::random(10),
            Str::random(10),
            Str::random(10),
            Str::random(10),
            Str::random(10),
            Str::random(10),
            Str::random(10),
        ];

        return response()->json([
            'success' => true,
            'data' => $codes
        ]);
    }

    /**
     * Regenerate recovery codes
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function regenerateRecoveryCodes(Request $request)
    {
        $token = $this->jwtService->extractTokenFromHeader($request->header('Authorization'));
        $user = $this->jwtService->getUserFromToken($token);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Generate new recovery codes
        $codes = [
            Str::random(10),
            Str::random(10),
            Str::random(10),
            Str::random(10),
            Str::random(10),
            Str::random(10),
            Str::random(10),
            Str::random(10),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Recovery codes regenerated successfully',
            'data' => $codes
        ]);
    }
}