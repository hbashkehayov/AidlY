<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Exception;

class JwtService
{
    private $secret;
    private $algo;

    public function __construct()
    {
        $this->secret = env('JWT_SECRET', 'your-secret-jwt-key-please-change-this-in-production');
        $this->algo = env('JWT_ALGO', 'HS256');
    }

    /**
     * Extract token from Authorization header
     *
     * @param string $header
     * @return string|null
     */
    public function extractTokenFromHeader($header)
    {
        if (!$header) {
            return null;
        }

        $parts = explode(' ', $header);

        if (count($parts) !== 2 || $parts[0] !== 'Bearer') {
            return null;
        }

        return $parts[1];
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

            // Check if token is blacklisted (simplified check)
            if (isset($decoded->jti) && $this->isTokenBlacklisted($decoded->jti)) {
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
     * Check if token is blacklisted (simplified implementation)
     *
     * @param string $jti
     * @return bool
     */
    private function isTokenBlacklisted($jti)
    {
        // For now, assume no tokens are blacklisted
        // In a full implementation, check against blacklist in cache/database
        return false;
    }

    /**
     * Get user information from token
     *
     * @param string $token
     * @return object|null
     */
    public function getUserFromToken($token)
    {
        $decoded = $this->validateToken($token);

        if (!$decoded || !isset($decoded->user)) {
            return null;
        }

        return $decoded->user;
    }
}