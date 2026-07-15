<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-900 dark:bg-zinc-900 dark:text-white" style="display: grid; grid-template-rows: auto auto 1fr auto; grid-template-columns: 100%; grid-template-areas: 'header' 'banner' 'main' 'footer';">
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
                    @if (auth()->check() && auth()->user()->is_admin)
                        <flux:navmenu.item icon="wrench-screwdriver" :href="route('admin.edit')" :current="request()->routeIs('admin.edit')" wire:navigate>
                            {{ __('admin') }}
                        </flux:navmenu.item>
                    @endif
                </flux:navmenu>
            </flux:dropdown>

            <a href="{{ route('home') }}" wire:navigate class="flex items-center gap-2 font-semibold">
                <flux:icon name="home" variant="mini" class="text-sky-600" />
                <span class="max-sm:hidden">{{ __('site_name') }}</span>
                <span class="sm:hidden">{{ __('site_name_short') }}</span>
            </a>

            <flux:navbar class="ms-6 max-lg:hidden">
                <flux:navbar.item icon="calendar-days" :href="route('home')" :current="request()->routeIs('home')" wire:navigate>
                    {{ __('nav_schedule') }}
                </flux:navbar.item>
                <flux:navbar.item icon="list-bullet" :href="route('reservations.mine')" :current="request()->routeIs('reservations.mine')" wire:navigate>
                    {{ __('my_reservations') }}
                </flux:navbar.item>
                @if (auth()->check() && auth()->user()->is_admin)
                    <flux:navbar.item icon="wrench-screwdriver" :href="route('admin.edit')" :current="request()->routeIs('admin.edit')" wire:navigate>
                        {{ __('admin') }}
                    </flux:navbar.item>
                @endif
            </flux:navbar>

            <flux:spacer />

            <div class="flex items-center gap-2">
                <div x-data class="contents">
                    <flux:button variant="ghost" size="sm" icon="sun" :aria-label="__('appearance')" class="dark:!hidden" x-on:click="$flux.appearance = 'dark'" />
                    <flux:button variant="ghost" size="sm" icon="moon" :aria-label="__('appearance')" class="!hidden dark:!inline-flex" x-on:click="$flux.appearance = 'light'" />
                </div>
                <x-language-switcher />

                @auth
                    <x-desktop-user-menu />
                @else
                    <flux:button :href="route('login')" variant="ghost" size="sm" wire:navigate>{{ __('log_in') }}</flux:button>
                    <flux:button :href="route('register')" variant="primary" size="sm" wire:navigate>{{ __('register') }}</flux:button>
                @endauth
            </div>
        </flux:header>

        @php $banner = \App\Models\Banner::current(); @endphp
        @if ($banner->enabled && ($bannerText = $banner->textFor(app()->getLocale())))
            <div @class([
                '[grid-area:banner] border-b px-4 py-2 text-center text-sm',
                'border-zinc-200 bg-zinc-100 text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200' => $banner->color === 'neutral',
                'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-800 dark:bg-sky-950/50 dark:text-sky-200' => $banner->color === 'info',
                'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950/50 dark:text-amber-200' => $banner->color === 'warning',
            ])>
                {{ $bannerText }}
            </div>
        @endif

        <flux:main container class="py-8">
            {{ $slot }}
        </flux:main>

        <footer class="border-t border-zinc-200 dark:border-zinc-700 py-6" style="grid-area: footer;">
            <div class="container mx-auto flex flex-wrap justify-center gap-x-6 gap-y-2 px-4 text-sm text-zinc-500 dark:text-zinc-400">
                <a href="{{ route('imprint') }}" wire:navigate class="hover:text-zinc-900 dark:hover:text-white">{{ __('imprint') }}</a>
                <a href="{{ route('privacy') }}" wire:navigate class="hover:text-zinc-900 dark:hover:text-white">{{ __('privacy_policy') }}</a>
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
