<?php

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

require __DIR__.'/settings.php';
