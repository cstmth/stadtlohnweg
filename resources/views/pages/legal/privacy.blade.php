<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::site')] #[Title('privacy_policy')] class extends Component {
    //
}; ?>

<div class="mx-auto max-w-2xl">
    <flux:heading size="xl" level="1" class="mb-6">{{ __('privacy_policy') }}</flux:heading>

    <div class="prose dark:prose-invert">
        {{-- Inhalt folgt --}}
    </div>
</div>
