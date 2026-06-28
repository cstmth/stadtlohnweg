<div class="mx-auto max-w-2xl">
    <flux:heading size="xl" level="1" class="mb-6">{{ __('settings') }}</flux:heading>

    <nav class="mb-6 flex gap-1 overflow-x-auto border-b border-zinc-200 dark:border-zinc-700">
        <a href="{{ route('profile.edit') }}" wire:navigate
           class="shrink-0 px-3 py-2 text-sm font-medium border-b-2 -mb-px {{ request()->routeIs('profile.edit') ? 'border-zinc-900 text-zinc-900 dark:border-white dark:text-white' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}">
            {{ __('profile') }}
        </a>
        <a href="{{ route('security.edit') }}" wire:navigate
           class="shrink-0 px-3 py-2 text-sm font-medium border-b-2 -mb-px {{ request()->routeIs('security.edit') ? 'border-zinc-900 text-zinc-900 dark:border-white dark:text-white' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}">
            {{ __('security') }}
        </a>
    </nav>

    @if (! empty($heading))
        <flux:heading>{{ $heading }}</flux:heading>
    @endif
    @if (! empty($subheading))
        <flux:subheading>{{ $subheading }}</flux:subheading>
    @endif

    <div class="mt-5 w-full max-w-lg">
        {{ $slot }}
    </div>
</div>
