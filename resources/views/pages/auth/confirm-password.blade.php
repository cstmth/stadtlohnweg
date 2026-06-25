<x-layouts::auth :title="__('confirm_password')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('confirm_password')"
            :description="__('confirm_password_area')"
        />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <x-passkey-verify
            options-route="passkey.confirm-options"
            submit-route="passkey.confirm"
            :label="__('passkey_confirm')"
            :loading-label="__('confirming')"
            :separator="__('or_confirm_password')"
        />

        <form method="POST" action="{{ route('password.confirm.store') }}" class="flex flex-col gap-6">
            @csrf

            <flux:input
                name="password"
                :label="__('password')"
                type="password"
                required
                autocomplete="current-password"
                :placeholder="__('password')"
                viewable
            />

            <flux:button variant="primary" type="submit" class="w-full" data-test="confirm-password-button">
                {{ __('confirm') }}
            </flux:button>
        </form>
    </div>
</x-layouts::auth>
