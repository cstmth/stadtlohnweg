<x-layouts::auth :title="__('log_in')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('log_in_to_account')" />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <div class="flex flex-col gap-2" x-data="{ open: {{ $errors->any() ? 'true' : 'false' }} }">
            @if (config('services.google.client_id'))
                <flux:button :href="route('oauth.redirect', 'google')" variant="outline" class="w-full" x-show="!open">
                    <svg class="mr-2 h-4 w-4" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                    {{ __('oauth_google') }}
                </flux:button>
            @endif

            @assets
            @vite('resources/js/passkeys.js')
            @endassets

            <div x-data="{
                supported: false,
                loading: false,
                error: null,
                init() {
                    this.supported = Boolean(window.Passkeys?.isSupported());
                    window.addEventListener('passkeys:ready', () => {
                        this.supported = Boolean(window.Passkeys?.isSupported());
                    }, { once: true });
                },
                async verify() {
                    this.loading = true;
                    this.error = null;
                    try {
                        const response = await window.Passkeys.verify({
                            routes: {
                                options: '{{ route('passkey.login-options') }}',
                                submit: '{{ route('passkey.login') }}',
                            },
                        });
                        Livewire.navigate(response.redirect || '/');
                    } catch (e) {
                        if (e.constructor?.name !== 'UserCancelledError') {
                            this.error = e.message;
                        }
                    } finally {
                        this.loading = false;
                    }
                },
            }">
                <template x-if="supported">
                    <div>
                        <flux:button
                            variant="outline"
                            icon="finger-print"
                            class="w-full"
                            x-show="!open"
                            x-on:click="verify()"
                            x-bind:disabled="loading"
                        >
                            <span x-show="!loading">{{ __('passkey_signin') }}</span>
                            <span x-show="loading" x-cloak>{{ __('authenticating') }}</span>
                        </flux:button>
                        <p x-show="error" x-text="error" x-cloak
                           class="text-sm text-center text-red-600 dark:text-red-400 mt-1"></p>
                    </div>
                </template>
            </div>

            <div>
                <flux:button variant="outline" class="w-full" x-show="!open" x-on:click="open = true" icon="envelope">
                    {{ __('login_with_email') }}
                </flux:button>

                <div x-show="open" x-cloak class="flex flex-col gap-6">
                    <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
                        @csrf

                        <flux:input
                            name="email"
                            :label="__('email_address')"
                            :value="old('email')"
                            type="email"
                            required
                            autofocus
                            autocomplete="email"
                            placeholder="email@example.com"
                        />

                        <div class="relative">
                            <flux:input
                                name="password"
                                :label="__('password')"
                                type="password"
                                required
                                autocomplete="current-password"
                                :placeholder="__('password')"
                                viewable
                            />

                            @if (Route::has('password.request'))
                                <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                                    {{ __('forgot_password_q') }}
                                </flux:link>
                            @endif
                        </div>

                        <flux:checkbox name="remember" :label="__('remember_me')" :checked="old('remember')" />

                        <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                            {{ __('log_in') }}
                        </flux:button>
                    </form>
                </div>
            </div>
        </div>

        <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
            <span>{{ __('dont_have_account') }}</span>
            <flux:link :href="route('register')" wire:navigate>{{ __('sign_up') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
