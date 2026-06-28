@props([
    'status',
])

@if ($status)
    <div {{ $attributes->merge(['class' => 'font-medium text-sm text-green-700']) }}>
        {{ $status }}
    </div>
@endif
