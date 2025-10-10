<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\JwtService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;

class AuthController extends Controller
{
    protected $jwtService;

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    /**
     * Register a new user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'sometimes|string|in:admin,agent,supervisor,customer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = new User();
            $user->id = (string) Str::uuid();
            $user->name = $request->name;
            $user->email = $request->email;
            $user->password = $request->password; // Uses setter to hash
            $user->role = $request->role ?? 'agent';
            $user->is_active = true;
            $user->save();

            // Generate tokens
            $accessToken = $this->jwtService->generateToken($user, false);
            $refreshToken = $this->jwtService->generateToken($user, true);

            return response()->json([
                'success' => true,
                'message' => 'User successfully registered',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'department_id' => $user->department_id,
                    'avatar_url' => $user->avatar_url,
                    'email_verified_at' => $user->email_verified_at,
                    'two_factor_enabled' => $user->two_factor_enabled,
                    'is_active' => $user->is_active,
                    'last_login_at' => $user->last_login_at,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ],
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer',
                'expires_in' => env('JWT_TTL', 60) * 60
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'User registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Login user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Check if account is active
        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Account is deactivated. Please contact support.'
            ], 403);
        }

        // Verify password
        if (!Hash::check($request->password, $user->password_hash)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Update last login
        $user->updateLastLogin($request->ip());

        // Generate tokens
        $accessToken = $this->jwtService->generateToken($user, false);
        $refreshToken = $this->jwtService->generateToken($user, true);

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'department_id' => $user->department_id,
                'avatar_url' => $user->avatar_url,
                'email_verified_at' => $user->email_verified_at,
                'two_factor_enabled' => $user->two_factor_enabled,
                'is_active' => $user->is_active,
                'last_login_at' => $user->last_login_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => env('JWT_TTL', 60) * 60
        ]);
    }

    /**
     * Logout user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        try {
            $token = $this->jwtService->extractTokenFromHeader($request->header('Authorization'));

            if ($token) {
                $decoded = $this->jwtService->validateToken($token);
                if ($decoded) {
                    // Blacklist the token
                    $this->jwtService->blacklistToken($decoded->jti, $decoded->exp);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Successfully logged out'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Refresh token
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'refresh_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $tokens = $this->jwtService->refreshToken($request->refresh_token);

        if (!$tokens) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid refresh token'
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed successfully',
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'token_type' => $tokens['token_type'],
            'expires_in' => $tokens['expires_in']
        ]);
    }

    /**
     * Get authenticated user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function me(Request $request)
    {
        $token = $this->jwtService->extractTokenFromHeader($request->header('Authorization'));
        $user = $this->jwtService->getUserFromToken($token);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'department_id' => $user->department_id,
                'avatar_url' => $user->avatar_url,
                'email_verified_at' => $user->email_verified_at,
                'two_factor_enabled' => $user->two_factor_enabled,
                'is_active' => $user->is_active,
                'last_login_at' => $user->last_login_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ]
        ]);
    }

    /**
     * Request password reset
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            // Return success even if user not found (security best practice)
            return response()->json([
                'success' => true,
                'message' => 'If the email exists, a password reset link has been sent.'
            ]);
        }

        // Generate password reset token
        $token = Str::random(64);

        // Store token in database
        DB::table('password_resets')->insert([
            'email' => $user->email,
            'token' => Hash::make($token),
            'created_at' => Carbon::now()
        ]);

        // TODO: Send email with reset link
        // For now, return the token (in production, this should be sent via email)

        return response()->json([
            'success' => true,
            'message' => 'If the email exists, a password reset link has been sent.',
            'token' => $token // Remove this in production
        ]);
    }

    /**
     * Reset password
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'email' => 'required|string|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Find the password reset record
        $passwordReset = DB::table('password_resets')
            ->where('email', $request->email)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$passwordReset || !Hash::check($request->token, $passwordReset->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset token'
            ], 400);
        }

        // Check if token is expired (1 hour)
        if (Carbon::now()->diffInMinutes($passwordReset->created_at) > 60) {
            return response()->json([
                'success' => false,
                'message' => 'Password reset token has expired'
            ], 400);
        }

        // Update user password
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $user->password = $request->password;
        $user->save();

        // Delete password reset record
        DB::table('password_resets')->where('email', $request->email)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password has been reset successfully'
        ]);
    }

    /**
     * Change password for authenticated user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed|different:current_password',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $token = $this->jwtService->extractTokenFromHeader($request->header('Authorization'));
        $user = $this->jwtService->getUserFromToken($token);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Verify current password
        if (!Hash::check($request->current_password, $user->password_hash)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect'
            ], 400);
        }

        // Update password
        $user->password = $request->new_password;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully'
        ]);
    }

    /**
     * Update profile for authenticated user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $token = $this->jwtService->extractTokenFromHeader($request->header('Authorization'));
        $user = $this->jwtService->getUserFromToken($token);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Update profile fields
        if ($request->has('name')) {
            $user->name = $request->name;
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'department_id' => $user->department_id,
                'avatar_url' => $user->avatar_url,
                'email_verified_at' => $user->email_verified_at,
                'two_factor_enabled' => $user->two_factor_enabled,
                'is_active' => $user->is_active,
                'last_login_at' => $user->last_login_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ]
        ]);
    }
}