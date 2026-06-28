<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user
            && ! $user->provider
            && $user instanceof MustVerifyEmail
            && ! $user->hasVerifiedEmail()
            && ! $request->routeIs('verification.*', 'logout', 'livewire.*', 'default-livewire.*')
        ) {
            return redirect()->route('verification.notice');
        }

        return $next($request);
    }
}
