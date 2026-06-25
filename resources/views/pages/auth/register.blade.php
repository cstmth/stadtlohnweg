<x-layouts::auth :title="__('register')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('create_an_account')" :description="__('register_subtitle')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
            @csrf
            <!-- Name -->
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

            <!-- Room Number -->
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

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('email_address')"
                :value="old('email')"
                type="email"
                required
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Password -->
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

            <!-- Confirm Password -->
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

            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" class="w-full" data-test="register-user-button">
                    {{ __('create_account') }}
                </flux:button>
            </div>
        </form>

        <x-social-login />

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('already_have_account') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('log_in') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
