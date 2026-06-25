<x-layouts::auth :title="__('forgot_password')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('forgot_password')" :description="__('forgot_subtitle')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('email_address')"
                type="email"
                required
                autofocus
                placeholder="email@example.com"
            />

            <flux:button variant="primary" type="submit" class="w-full" data-test="email-password-reset-link-button">
                {{ __('email_reset_link') }}
            </flux:button>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-400">
            <span>{{ __('or_return_to') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('log_in_lower') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
