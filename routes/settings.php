<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::livewire('settings/profile', 'pages::settings.profile')->name('profile.edit');
});

Route::middleware(['auth'])->group(function () {
    Route::livewire('settings/security', 'pages::settings.security')
        ->name('security.edit');
});

Route::livewire('verwaltung', 'pages::settings.admin')
    ->middleware('auth')
    ->name('admin.edit');

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
