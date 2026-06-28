<?php

use App\Concerns\ProfileValidationRules;
use App\Models\Reservation;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::site')] #[Title('complete_profile')] class extends Component {
    use ProfileValidationRules;

    public string $room_number = '';

    public function mount(): void
    {
        if (filled(Auth::user()->room_number)) {
            $this->redirect(route('home'));
        }
    }

    public function save(): void
    {
        $this->room_number = Reservation::normalizeRoom($this->room_number);

        $this->validate([
            'room_number' => $this->roomNumberRules(),
        ]);

        Auth::user()->update(['room_number' => $this->room_number]);

        $this->redirect(route('home'));
    }
}; ?>

<div class="mx-auto max-w-sm py-8">
    <flux:heading size="xl" class="mb-2">{{ __('complete_profile') }}</flux:heading>
    <flux:subheading class="mb-6">{{ __('complete_profile_sub') }}</flux:subheading>

    <form wire:submit="save" class="flex flex-col gap-6">
        <flux:input
            wire:model="room_number"
            :label="__('room_number')"
            type="text"
            required
            autofocus
            autocomplete="off"
            :placeholder="__('room_number_placeholder')"
            :description="__('room_number_hint')"
        />

        <flux:button type="submit" variant="primary" class="w-full">
            {{ __('save') }}
        </flux:button>
    </form>
</div>
