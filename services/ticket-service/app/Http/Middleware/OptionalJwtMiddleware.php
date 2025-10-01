<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class OptionalJwtMiddleware
{
    /**
     * Handle an incoming request with optional JWT authentication.
     * If token is present and valid, set user data. If not, proceed without authentication.
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $this->getTokenFromRequest($request);

        // If no token, just proceed without setting user
        if (!$token) {
            return $next($request);
        }

        try {
            // Try to verify token with auth service
            $userData = $this->verifyTokenWithAuthService($token);

            if ($userData) {
                // Set user data in request for authenticated requests
                $request->merge(['user' => $userData]);
                $request->attributes->set('auth_user', $userData);
            }

            // Proceed regardless of token validity (it's optional)
            return $next($request);

        } catch (\Exception $e) {
            // Log but don't block - authentication is optional
            \Log::debug('Optional JWT verification failed', [
                'error' => $e->getMessage()
            ]);

            return $next($request);
        }
    }

    /**
     * Extract token from request
     */
    private function getTokenFromRequest(Request $request): ?string
    {
        // Try Authorization header first
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        // Try query parameter as fallback
        return $request->query('token');
    }

    /**
     * Verify token with auth service
     */
    private function verifyTokenWithAuthService(string $token): ?array
    {
        try {
            // Make request to auth service to verify token
            $authServiceUrl = config('services.auth.url', 'http://localhost:8001');

            $response = $this->makeHttpRequest($authServiceUrl . '/api/v1/auth/me', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ]
            ]);

            if ($response && $response['status'] === 200) {
                $data = json_decode($response['body'], true);

                if (isset($data['user'])) {
                    return $data['user'];
                } elseif (isset($data['id'])) {
                    return $data; // Direct user data
                }
            }

            return null;

        } catch (\Exception $e) {
            \Log::debug('Auth service verification failed', [
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Make HTTP request
     */
    private function makeHttpRequest(string $url, array $options = []): ?array
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        if (isset($options['headers'])) {
            $headers = [];
            foreach ($options['headers'] as $key => $value) {
                $headers[] = $key . ': ' . $value;
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            throw new \Exception('cURL Error: ' . $error);
        }

        return [
            'status' => $status,
            'body' => $body
        ];
    }
}
