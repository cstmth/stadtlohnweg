<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user
            && $user->provider
            && blank($user->room_number)
            && ! $request->routeIs('profile.complete', 'logout', 'verification.*', 'livewire.*', 'default-livewire.*')
        ) {
            return redirect()->route('profile.complete');
        }

        return $next($request);
    }
}
