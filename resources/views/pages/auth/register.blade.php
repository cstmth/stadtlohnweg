<x-layouts::auth :title="__('register')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('create_an_account')" />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <div class="flex flex-col gap-2">
            @if (config('services.google.client_id'))
                <flux:button :href="route('oauth.redirect', 'google')" variant="outline" class="w-full">
                    <svg class="mr-2 h-4 w-4" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                    {{ __('oauth_google') }}
                </flux:button>
            @endif

            <div x-data="{ open: {{ $errors->any() ? 'true' : 'false' }} }">
                <flux:button variant="outline" class="w-full" x-show="!open" x-on:click="open = true" icon="envelope">
                    {{ __('register_with_email') }}
                </flux:button>

                <div x-show="open" x-cloak>
                    <div class="relative my-4">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-zinc-200 dark:border-zinc-700"></div>
                        </div>
                        <div class="relative flex justify-center text-xs uppercase">
                            <span class="bg-zinc-50 px-2 text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400">{{ __('oauth_or') }}</span>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
                        @csrf
                        <flux:input
                            name="name"
                            :label="__('name')"
                            :value="old('name')"
                            type="text"
                            required
                            autofocus
                            autocomplete="name"
                            :placeholder="__('full_name')"
                        />

                        <flux:input
                            name="room_number"
                            :label="__('room_number')"
                            :value="old('room_number')"
                            type="text"
                            required
                            autocomplete="off"
                            :placeholder="__('room_number_placeholder')"
                            :description="__('room_number_hint')"
                        />

                        <flux:input
                            name="email"
                            :label="__('email_address')"
                            :value="old('email')"
                            type="email"
                            required
                            autocomplete="email"
                            placeholder="email@example.com"
                        />

                        <flux:input
                            name="password"
                            :label="__('password')"
                            type="password"
                            required
                            autocomplete="new-password"
                            :placeholder="__('password')"
                            passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                            viewable
                        />

                        <flux:input
                            name="password_confirmation"
                            :label="__('confirm_password')"
                            type="password"
                            required
                            autocomplete="new-password"
                            :placeholder="__('confirm_password')"
                            passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                            viewable
                        />

                        <flux:button type="submit" variant="primary" class="w-full" data-test="register-user-button">
                            {{ __('create_account') }}
                        </flux:button>
                    </form>
                </div>
            </div>
        </div>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('already_have_account') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('log_in') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
