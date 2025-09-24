<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Exception;

class JwtService
{
    private $secret;
    private $algo;
    private $ttl;
    private $refreshTtl;

    public function __construct()
    {
        $this->secret = env('JWT_SECRET', 'default_secret_key_change_this');
        $this->algo = env('JWT_ALGO', 'HS256');
        $this->ttl = env('JWT_TTL', 60); // minutes
        $this->refreshTtl = env('JWT_REFRESH_TTL', 20160); // minutes (2 weeks)
    }

    /**
     * Generate JWT token for user
     *
     * @param User $user
     * @param bool $isRefreshToken
     * @return string
     */
    public function generateToken(User $user, $isRefreshToken = false)
    {
        $now = time();
        $ttl = $isRefreshToken ? $this->refreshTtl : $this->ttl;

        $payload = [
            'iss' => env('APP_URL', 'http://localhost'), // Issuer
            'sub' => $user->id, // Subject (user ID)
            'iat' => $now, // Issued at
            'exp' => $now + ($ttl * 60), // Expiration time
            'nbf' => $now, // Not before
            'jti' => uniqid('', true), // JWT ID
            'type' => $isRefreshToken ? 'refresh' : 'access',
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'role' => $user->role,
            ]
        ];

        return JWT::encode($payload, $this->secret, $this->algo);
    }

    /**
     * Validate and decode JWT token
     *
     * @param string $token
     * @return object|null
     */
    public function validateToken($token)
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, $this->algo));

            // Check if token is blacklisted
            if ($this->isTokenBlacklisted($decoded->jti)) {
                return null;
            }

            return $decoded;
        } catch (ExpiredException $e) {
            return null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Refresh access token using refresh token
     *
     * @param string $refreshToken
     * @return array|null
     */
    public function refreshToken($refreshToken)
    {
        $decoded = $this->validateToken($refreshToken);

        if (!$decoded || $decoded->type !== 'refresh') {
            return null;
        }

        $user = User::find($decoded->sub);

        if (!$user || !$user->is_active) {
            return null;
        }

        // Blacklist old refresh token
        $this->blacklistToken($decoded->jti, $decoded->exp);

        // Generate new tokens
        return [
            'access_token' => $this->generateToken($user, false),
            'refresh_token' => $this->generateToken($user, true),
            'token_type' => 'Bearer',
            'expires_in' => $this->ttl * 60
        ];
    }

    /**
     * Blacklist a token
     *
     * @param string $jti
     * @param int $exp
     * @return void
     */
    public function blacklistToken($jti, $exp)
    {
        if (env('JWT_BLACKLIST_ENABLED', true)) {
            $ttl = $exp - time();
            if ($ttl > 0) {
                Cache::put("blacklist_token:{$jti}", true, $ttl);
            }
        }
    }

    /**
     * Check if token is blacklisted
     *
     * @param string $jti
     * @return bool
     */
    public function isTokenBlacklisted($jti)
    {
        if (!env('JWT_BLACKLIST_ENABLED', true)) {
            return false;
        }

        return Cache::has("blacklist_token:{$jti}");
    }

    /**
     * Get user from token
     *
     * @param string $token
     * @return User|null
     */
    public function getUserFromToken($token)
    {
        $decoded = $this->validateToken($token);

        if (!$decoded) {
            return null;
        }

        return User::find($decoded->sub);
    }

    /**
     * Extract token from Authorization header
     *
     * @param string $authorizationHeader
     * @return string|null
     */
    public function extractTokenFromHeader($authorizationHeader)
    {
        if (empty($authorizationHeader)) {
            return null;
        }

        if (preg_match('/Bearer\s+(.*)$/i', $authorizationHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }
}