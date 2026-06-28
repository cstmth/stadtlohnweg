<?php

use App\Concerns\ProfileValidationRules;
use App\Models\Reservation;
/* @chisel-email-verification */
use Illuminate\Contracts\Auth\MustVerifyEmail;
/* @end-chisel-email-verification */
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::site')] #[Title('profile_settings')] class extends Component {
    use ProfileValidationRules;

    public string $name = '';
    public string $room_number = '';
    public string $email = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->room_number = (string) Auth::user()->room_number;
        $this->email = Auth::user()->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $this->room_number = Reservation::normalizeRoom($this->room_number);

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Flux::toast(variant: 'success', text: __('profile_updated'));
    }

    /* @chisel-email-verification */
    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('home', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    /* @end-chisel-email-verification */
}; ?>

<section class="w-full">
    <x-pages::settings.layout>
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('name')" type="text" required autofocus autocomplete="name" />

            <flux:input
                wire:model="room_number"
                :label="__('room_number')"
                type="text"
                required
                autocomplete="off"
                :placeholder="__('room_number_placeholder')"
                :description="__('room_number_hint')"
            />

            @if (Auth::user()->provider)
                <flux:input :label="__('email')" type="email" :value="Auth::user()->email" disabled />
            @else
                <div>
                    <flux:input wire:model="email" :label="__('email')" type="email" required autocomplete="email" />

                    {{-- @chisel-email-verification --}}
                    @if ($this->hasUnverifiedEmail)
                        <div>
                            <flux:text class="mt-4">
                                {{ __('email_unverified') }}

                                <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                    {{ __('verify_resend_link') }}
                                </flux:link>
                            </flux:text>

                            @if (session('status') === 'verification-link-sent')
                                <flux:text class="mt-2 font-medium !dark:text-green-400 !text-green-600">
                                    {{ __('verify_resent') }}
                                </flux:text>
                            @endif
                        </div>
                    @endif
                    {{-- @end-chisel-email-verification --}}
                </div>
            @endif

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full" data-test="update-profile-button">
                        {{ __('save') }}
                    </flux:button>
                </div>

            </div>
        </form>

        <livewire:pages::settings.delete-user-form />
    </x-pages::settings.layout>
</section>
