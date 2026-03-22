<?php

namespace App\Http\Middleware;

use App\Helpers\UrlHelper;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  Request  $request
     * @return string|null
     */
    protected function redirectTo($request): ?string
    {
        if (!$request->expectsJson()) {
            if ($request->is('personal/*')) {
                return UrlHelper::personalRoute('personal.auth.telegram');
            }
            return route('login');
        }
    }
}
