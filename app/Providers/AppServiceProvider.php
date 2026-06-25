<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // Mindestens 8 Zeichen, mindestens drei von vier Kategorien:
        // Großbuchstaben, Kleinbuchstaben, Ziffern, Symbole.
        Password::defaults(fn (): Password => Password::min(8)
            ->rules(['regex:/^(?:(?=.*[a-z])(?=.*[A-Z])(?=.*\d)|(?=.*[a-z])(?=.*[A-Z])(?=.*[\W_])|(?=.*[a-z])(?=.*\d)(?=.*[\W_])|(?=.*[A-Z])(?=.*\d)(?=.*[\W_])).+$/'])
        );
    }
}
