<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class ForceHttps
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->secure() && app()->environment('production')) {
            // Force HTTPS
            URL::forceScheme('https');
            
            // Redirect to HTTPS
            return redirect()->secure($request->getRequestUri());
        }

        // Add security headers
        $response = $next($request);
        
        if (method_exists($response, 'header')) {
            $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
            $response->header('X-Content-Type-Options', 'nosniff');
            $response->header('X-XSS-Protection', '1; mode=block');
            $response->header('X-Frame-Options', 'SAMEORIGIN');
            $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');
            $response->header('Alt-Svc', 'h3=":443"; ma=86400');
        }

        return $response;
    }
}
