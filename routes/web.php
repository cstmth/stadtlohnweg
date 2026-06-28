<?php

use App\Http\Controllers\OAuthController;
use App\Http\Controllers\RunScheduledTasksController;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsureProfileComplete;
use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Öffentlicher Belegungsplan – mit und ohne Konto nutzbar.
Route::livewire('/', 'pages::calendar')->name('home');

// "Meine Reservierungen" – mit Konto kontoübergreifend, ohne Konto pro Browser (localStorage).
Route::livewire('meine-reservierungen', 'pages::my-reservations')->name('reservations.mine');

// Sprachwechsel: in Session und (mit Konto) in der Datenbank speichern.
Route::post('sprache', function (Request $request) {
    $locale = (string) $request->input('locale');

    if (! in_array($locale, SetLocale::SUPPORTED, true)) {
        $locale = config('app.locale');
    }

    $request->session()->put('locale', $locale);

    if ($request->user()) {
        $request->user()->update(['locale' => $locale]);
    }

    return back();
})->name('locale.update');

// OAuth (Google)
Route::get('auth/{provider}', [OAuthController::class, 'redirect'])->name('oauth.redirect');
Route::get('auth/{provider}/callback', [OAuthController::class, 'callback'])->name('oauth.callback');

// Konto-Löschung für OAuth-Nutzer per erneuter Anmeldung bestätigen.
Route::get('konto/loeschen/{provider}', [OAuthController::class, 'redirectForDeletion'])
    ->middleware('auth')
    ->name('account.delete.reauth');

// Zimmernummer nach OAuth-Registrierung ergänzen.
Route::livewire('profil-vervollstaendigen', 'pages::auth.complete-profile')
    ->middleware('auth')
    ->name('profile.complete');

// Statische Seiten (Impressum, Datenschutz, Hilfe).
Route::livewire('impressum', 'pages::legal.imprint')->name('imprint');
Route::livewire('datenschutz', 'pages::legal.privacy')->name('privacy');
Route::livewire('hilfe', 'pages::legal.help')->name('help');

// Von Cloud Scheduler ausgelöste geplante Aufgaben (Ersatz für System-Cron).
Route::get('tasks/run-scheduler', RunScheduledTasksController::class)
    ->withoutMiddleware([EnsureEmailIsVerified::class, EnsureProfileComplete::class])
    ->name('tasks.scheduler');

// Manuelle Ausführung von Artisan-Befehlen über das Web (mit Token-Schutz).
Route::get('tasks/artisan', \App\Http\Controllers\RunArtisanCommandController::class)
    ->withoutMiddleware([EnsureEmailIsVerified::class, EnsureProfileComplete::class])
    ->name('tasks.artisan');

require __DIR__.'/settings.php';
