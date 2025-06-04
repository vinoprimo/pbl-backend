<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        '/api/login',
        '/api/register',
        '/api/logout',
        '/api/forgot-password',
        '/api/reset-password',
        '/api/email/verification-notification',
        'api/broadcasting/auth',
        'api/payments/callback',
        // Add other routes that should be excluded from CSRF
    ];
}
