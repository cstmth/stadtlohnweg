<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class OAuthController extends Controller
{
    /**
     * @var array<int, string>
     */
    private const PROVIDERS = ['google'];

    public function redirect(string $provider)
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);

        return Socialite::driver($provider)->redirect();
    }

    /**
     * Erneute Anmeldung beim Provider anstoßen, um die Konto-Löschung zu bestätigen.
     */
    public function redirectForDeletion(string $provider)
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);

        $user = Auth::user();

        abort_unless($user && $user->provider === $provider, 403);

        $user->update(['delete_token' => bin2hex(random_bytes(32))]);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider)
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);

        $socialUser = Socialite::driver($provider)->user();

        // Prüfen ob eine Konto-Löschung ausstehend ist.
        $pendingDelete = User::where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->whereNotNull('delete_token')
            ->first();

        if ($pendingDelete) {
            $pendingDelete->reservations()->delete();

            Auth::logout();
            $pendingDelete->delete();
            session()->invalidate();
            session()->regenerateToken();

            return redirect()->route('home');
        }

        $user = User::where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if (! $user) {
            $user = User::where('email', $socialUser->getEmail())
                ->whereNull('provider')
                ->first();

            if ($user) {
                $user->update([
                    'provider' => $provider,
                    'provider_id' => $socialUser->getId(),
                ]);
            } else {
                $user = User::create([
                    'name' => $socialUser->getName() ?? $socialUser->getEmail(),
                    'email' => $socialUser->getEmail(),
                    'provider' => $provider,
                    'provider_id' => $socialUser->getId(),
                    'email_verified_at' => now(),
                ]);
            }
        }

        Auth::login($user, true);

        if (blank($user->room_number)) {
            return redirect()->route('profile.complete');
        }

        return redirect()->intended(route('home'));
    }
}
