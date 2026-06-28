@props([
    'title',
    'description' => null,
])

<div class="flex w-full flex-col text-center">
    <flux:heading size="xl">{{ $title }}</flux:heading>
    @if ($description)
        <flux:subheading>{{ $description }}</flux:subheading>
    @endif
</div>
