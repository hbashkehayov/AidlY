<?php

namespace App\Http\Middleware;

use Closure;

class VerifyWebhookSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // For now, pass through
        // TODO: Implement webhook signature verification
        return $next($request);
    }
}
