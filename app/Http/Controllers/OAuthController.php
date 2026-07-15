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

        $user->update([
            'delete_token' => bin2hex(random_bytes(32)),
            'delete_token_expires_at' => now()->addMinutes(5),
        ]);

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
            $tokenExpired = $pendingDelete->delete_token_expires_at === null
                || $pendingDelete->delete_token_expires_at->isPast();

            if (! $tokenExpired) {
                $pendingDelete->reservations()->delete();

                Auth::logout();
                $pendingDelete->delete();
                session()->invalidate();
                session()->regenerateToken();

                return redirect()->route('home');
            }

            // Löschanfrage nie abgeschlossen (z. B. Tab geschlossen) — abgelaufenes Token verwerfen,
            // damit ein späterer normaler Login das Konto nicht versehentlich löscht.
            $pendingDelete->update(['delete_token' => null, 'delete_token_expires_at' => null]);
        }

        $user = User::where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if (! $user) {
            // Ein Konto mit dieser E-Mail über einen anderen Weg (Passwort oder anderer Provider) darf
            // nicht automatisch mit diesem OAuth-Login verknüpft werden (Kontoübernahme-Risiko).
            if (User::where('email', $socialUser->getEmail())->exists()) {
                return redirect()->route('login')->withErrors([
                    'email' => __('oauth_email_conflict'),
                ]);
            }

            $user = User::create([
                'name' => $socialUser->getName() ?? $socialUser->getEmail(),
                'email' => $socialUser->getEmail(),
                'provider' => $provider,
                'provider_id' => $socialUser->getId(),
                'email_verified_at' => now(),
            ]);
        }

        Auth::login($user, true);

        if (blank($user->room_number)) {
            return redirect()->route('profile.complete');
        }

        return redirect()->intended(route('home'));
    }
}
