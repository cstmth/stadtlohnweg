@php
    $languages = ['de' => 'Deutsch', 'en' => 'English'];
    $current = app()->getLocale();
@endphp

<div
    {{ $attributes }}
    x-data="{ current: @js($current) }"
    @guest
        x-init="
            const stored = localStorage.getItem('washing.locale');
            if (stored && ['de', 'en'].includes(stored) && stored !== current) {
                $refs['form_' + stored]?.submit();
            }
        "
    @endguest
>
    <flux:dropdown position="bottom" align="end">
        <flux:button variant="ghost" size="sm" icon="language" :aria-label="__('change_language')">
            {{ strtoupper($current) }}
        </flux:button>

        <flux:menu>
            @foreach ($languages as $code => $label)
                <form
                    method="POST"
                    action="{{ route('locale.update') }}"
                    x-ref="form_{{ $code }}"
                    @submit="localStorage.setItem('washing.locale', '{{ $code }}')"
                >
                    @csrf
                    <input type="hidden" name="locale" value="{{ $code }}">
                    <flux:menu.item
                        as="button"
                        type="submit"
                        :icon="$current === $code ? 'check' : null"
                        class="w-full cursor-pointer"
                    >
                        {{ $label }}
                    </flux:menu.item>
                </form>
            @endforeach
        </flux:menu>
    </flux:dropdown>
</div>
