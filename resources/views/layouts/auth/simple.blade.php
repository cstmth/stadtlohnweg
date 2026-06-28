<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-900 dark:bg-zinc-900 dark:text-white" style="display: grid; grid-template-rows: auto 1fr auto; grid-template-columns: 100%; grid-template-areas: 'header' 'main' 'footer';">
        <flux:header container class="border-b border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <a href="{{ route('home') }}" wire:navigate class="flex items-center gap-2 font-semibold">
                <flux:icon name="home" variant="mini" class="text-sky-500" />
                <span class="max-sm:hidden">{{ __('site_name') }}</span>
                <span class="sm:hidden">{{ __('site_name_short') }}</span>
            </a>

            <flux:spacer />

            <div class="flex items-center gap-2">
                <div x-data class="contents">
                    <flux:button variant="ghost" size="sm" icon="sun" :aria-label="__('appearance')" class="dark:!hidden" x-on:click="$flux.appearance = 'dark'" />
                    <flux:button variant="ghost" size="sm" icon="moon" :aria-label="__('appearance')" class="!hidden dark:!inline-flex" x-on:click="$flux.appearance = 'light'" />
                </div>
                <x-language-switcher />
            </div>
        </flux:header>

        <flux:main container class="flex items-center justify-center py-12">
            <div class="w-full max-w-sm">
                {{ $slot }}
            </div>
        </flux:main>

        <footer class="border-t border-zinc-200 dark:border-zinc-700 py-6" style="grid-area: footer;">
            <div class="container mx-auto flex flex-wrap justify-center gap-x-6 gap-y-2 px-4 text-sm text-zinc-500 dark:text-zinc-400">
                <a href="{{ route('imprint') }}" wire:navigate class="hover:text-zinc-900 dark:hover:text-white">{{ __('imprint') }}</a>
                <a href="{{ route('privacy') }}" wire:navigate class="hover:text-zinc-900 dark:hover:text-white">{{ __('privacy_policy') }}</a>
                <a href="{{ route('help') }}" wire:navigate class="hover:text-zinc-900 dark:hover:text-white">{{ __('help') }}</a>
            </div>
        </footer>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
