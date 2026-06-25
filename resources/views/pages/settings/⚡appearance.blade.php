<?php

use Livewire\Component;
use Livewire\Attributes\Title;

new #[Title('appearance_settings')] class extends Component {
    //
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('appearance_settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('appearance')" :subheading="__('appearance_sub')">
        <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
            <flux:radio value="light" icon="sun">{{ __('theme_light') }}</flux:radio>
            <flux:radio value="dark" icon="moon">{{ __('theme_dark') }}</flux:radio>
            <flux:radio value="system" icon="computer-desktop">{{ __('theme_system') }}</flux:radio>
        </flux:radio.group>
    </x-pages::settings.layout>
</section>
