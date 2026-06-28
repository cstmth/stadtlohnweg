<?php

use Livewire\Component;

new class extends Component {}; ?>

<section class="mt-10 space-y-6">
    <div class="relative mb-5">
        <flux:heading>{{ __('delete_account') }}</flux:heading>
        <flux:subheading>{{ __('delete_account_sub') }}</flux:subheading>
    </div>

    @if (Auth::user()->provider)
        <flux:modal.trigger name="confirm-user-deletion-oauth">
            <flux:button variant="danger" data-test="delete-user-button">
                {{ __('delete_account') }}
            </flux:button>
        </flux:modal.trigger>

        <flux:modal name="confirm-user-deletion-oauth" class="max-w-lg">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('delete_account_q') }}</flux:heading>
                    <flux:subheading>{{ __('delete_account_oauth_notice') }}</flux:subheading>
                </div>

                <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                    <flux:modal.close>
                        <flux:button variant="filled">{{ __('cancel') }}</flux:button>
                    </flux:modal.close>

                    <flux:button
                        variant="danger"
                        :href="route('account.delete.reauth', Auth::user()->provider)"
                        data-test="confirm-delete-user-button"
                    >
                        {{ __('delete_account') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @else
        <flux:modal.trigger name="confirm-user-deletion">
            <flux:button variant="danger" data-test="delete-user-button">
                {{ __('delete_account') }}
            </flux:button>
        </flux:modal.trigger>

        <livewire:pages::settings.delete-user-modal />
    @endif
</section>
