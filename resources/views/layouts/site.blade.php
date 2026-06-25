<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-900 dark:bg-zinc-900 dark:text-white">
        <flux:header container class="border-b border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            {{-- Mobile-Navigation --}}
            <flux:dropdown class="lg:hidden" position="bottom" align="start">
                <flux:button icon="bars-2" variant="ghost" size="sm" class="mr-1" inset="left" :aria-label="__('menu')" />
                <flux:navmenu>
                    <flux:navmenu.item icon="calendar-days" :href="route('home')" :current="request()->routeIs('home')" wire:navigate>
                        {{ __('nav_schedule') }}
                    </flux:navmenu.item>
                    <flux:navmenu.item icon="list-bullet" :href="route('reservations.mine')" :current="request()->routeIs('reservations.mine')" wire:navigate>
                        {{ __('my_reservations') }}
                    </flux:navmenu.item>
                </flux:navmenu>
            </flux:dropdown>

            <a href="{{ route('home') }}" wire:navigate class="flex items-center gap-2 font-semibold">
                <flux:icon name="sparkles" variant="micro" class="text-sky-500" />
                <span class="max-sm:hidden">Waschkeller Stadtlohnweg</span>
                <span class="sm:hidden">Waschkeller</span>
            </a>

            <flux:navbar class="ms-6 max-lg:hidden">
                <flux:navbar.item icon="calendar-days" :href="route('home')" :current="request()->routeIs('home')" wire:navigate>
                    {{ __('nav_schedule') }}
                </flux:navbar.item>
                <flux:navbar.item icon="list-bullet" :href="route('reservations.mine')" :current="request()->routeIs('reservations.mine')" wire:navigate>
                    {{ __('my_reservations') }}
                </flux:navbar.item>
            </flux:navbar>

            <flux:spacer />

            <div class="flex items-center gap-2">
                <x-language-switcher />

                @auth
                    <x-desktop-user-menu />
                @else
                    <flux:button :href="route('login')" variant="ghost" size="sm" wire:navigate>{{ __('log_in') }}</flux:button>
                    <flux:button :href="route('register')" variant="primary" size="sm" wire:navigate>{{ __('register') }}</flux:button>
                @endauth
            </div>
        </flux:header>

        <flux:main container class="py-8">
            {{ $slot }}
        </flux:main>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
