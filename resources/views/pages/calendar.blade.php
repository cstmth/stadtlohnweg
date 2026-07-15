<?php

use App\Models\DefectiveAppliance;
use App\Models\DoenekenEvent;
use App\Models\Reservation;
use Flux\Flux;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::site')] #[Title('schedule_title')] class extends Component {
    /** Aktuell angezeigter Block/Keller ('A' oder 'C'). */
    public string $block = 'A';

    // --- Buchungs-Modal ---
    public string $bookDate = '';
    public int $bookHour = 0;
    public string $bookAppliance = '';
    public string $roomNumber = '';
    public string $pin = '';

    // --- Storno-Modal ---
    public ?int $manageId = null;
    public string $cancelPin = '';

    /** Wurde die betrachtete Reservierung in diesem Browser angelegt (PIN bekannt)? */
    public bool $knownFromBrowser = false;

    /** ID der zuletzt gebuchten Gastbuchung — damit die Zelle sofort grün rendert. */
    public ?int $lastBookedId = null;

    /** Wie viele Tage vor heute als "vergangen" angezeigt werden. */
    private const PAST_DAYS = 2;

    /** Wie viele Tage hinter dem buchbaren Bereich als Hinweis angezeigt werden. */
    private const FUTURE_HINT_DAYS = 2;

    public function mount(): void
    {
        if (Auth::check() && filled(Auth::user()->preferred_block)) {
            $this->block = Auth::user()->preferred_block;
        }
    }

    public function updatedBlock(string $value): void
    {
        if (! array_key_exists($value, Reservation::BLOCKS)) {
            $this->block = 'A';

            return;
        }

        if (Auth::check()) {
            Auth::user()->update(['preferred_block' => $value]);
        }

        $this->dispatch('block-changed', block: $value);
    }

    // ------------------------------------------------------------------
    // Tage berechnen
    // ------------------------------------------------------------------

    /**
     * Alle anzuzeigenden Tage: 2 vergangene + buchbare + 2 Hinweis-Tage.
     *
     * @return array<int, Carbon>
     */
    #[Computed]
    public function days(): array
    {
        $start = Carbon::today()->subDays(self::PAST_DAYS);
        $maxBookable = Carbon::today()->addMonths(Reservation::MAX_ADVANCE_MONTHS);
        $end = $maxBookable->copy()->addDays(self::FUTURE_HINT_DAYS);

        $days = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $days[] = $cursor->copy();
            $cursor->addDay();
        }

        return $days;
    }

    /**
     * Klassifiziert jeden Tag als 'past', 'bookable' oder 'future'.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function dayTypes(): array
    {
        $today = Carbon::today();
        $maxBookable = Carbon::today()->addMonths(Reservation::MAX_ADVANCE_MONTHS);

        $types = [];
        foreach ($this->days as $day) {
            $key = $day->format('Y-m-d');
            if ($day->lt($today)) {
                $types[$key] = 'past';
            } elseif ($day->gt($maxBookable)) {
                $types[$key] = 'future';
            } else {
                $types[$key] = 'bookable';
            }
        }

        return $types;
    }

    /**
     * Reservierungen des gesamten sichtbaren Bereichs, indiziert nach "Datum|Stunde|Gerät".
     *
     * @return \Illuminate\Support\Collection<string, Reservation>
     */
    #[Computed]
    public function reservations()
    {
        $days = $this->days;
        $start = $days[0]->format('Y-m-d');
        $end = end($days)->format('Y-m-d');

        return Reservation::query()
            ->where('block', $this->block)
            ->whereBetween('reserved_date', [$start, $end])
            ->get()
            ->keyBy(fn (Reservation $r) => $r->reserved_date->format('Y-m-d').'|'.$r->hour.'|'.$r->appliance);
    }

    /**
     * Döneken-Info pro Tag: ['text' => string|null, 'open' => bool]
     * @return array<string, array{open: bool, text: string|null}>
     */
    #[Computed]
    public function doeneken(): array
    {
        $days = $this->days;
        $start = $days[0]->format('Y-m-d');
        $end = end($days)->format('Y-m-d');

        $overrides = DoenekenEvent::whereBetween('date', [$start, $end])
            ->get()
            ->keyBy(fn ($e) => $e->date->format('Y-m-d'));

        $result = [];

        foreach ($days as $day) {
            $dateStr = $day->format('Y-m-d');
            $isDefaultDay = in_array($day->dayOfWeek, [Carbon::TUESDAY, Carbon::FRIDAY]);
            $override = $overrides->get($dateStr);
            $luckyNumber = (new \Random\Randomizer(new \Random\Engine\Mt19937(abs(crc32($dateStr)))))->getInt(0, 9);

            if ($override) {
                if ($override->closed) {
                    $result[$dateStr] = ['open' => false, 'text' => $override->custom_text ?: __('doeneken_irregular_closed')];
                } else {
                    $text = $override->custom_text
                        ? str_replace('{lucky}', (string) $luckyNumber, $override->custom_text)
                        : __('doeneken_line1')."\n".__('doeneken_line2', ['number' => $luckyNumber]);
                    $result[$dateStr] = ['open' => true, 'text' => $text];
                }
            } elseif ($isDefaultDay) {
                $result[$dateStr] = [
                    'open' => true,
                    'text' => __('doeneken_line1')."\n".__('doeneken_line2', ['number' => $luckyNumber]),
                ];
            } else {
                $result[$dateStr] = ['open' => false, 'text' => null];
            }
        }

        return $result;
    }

    #[Computed]
    public function defective(): array
    {
        return DefectiveAppliance::where('block', $this->block)
            ->pluck('appliance')
            ->toArray();
    }

    // ------------------------------------------------------------------
    // Buchen
    // ------------------------------------------------------------------

    public function book(string $date, int $hour, string $appliance): void
    {
        if (! $this->isBookable($date, $hour, $appliance)) {
            Flux::toast(variant: 'warning', text: __('toast_slot_unavailable'));

            return;
        }

        $this->resetValidation();
        $this->bookDate = $date;
        $this->bookHour = $hour;
        $this->bookAppliance = $appliance;
        $this->roomNumber = Auth::check() ? (string) Auth::user()->room_number : '';
        $this->pin = '';

        Flux::modal('book')->show();
    }

    public function store(): void
    {
        if (Auth::check()) {
            $this->roomNumber = (string) Auth::user()->room_number;
        } else {
            $this->roomNumber = Reservation::normalizeRoom($this->roomNumber);

            $this->validate([
                'roomNumber' => ['required', 'string', 'regex:'.Reservation::ROOM_REGEX],
                'pin' => ['required', 'digits:4'],
            ], attributes: [
                'roomNumber' => __('room_number'),
                'pin' => 'PIN',
            ]);
        }

        if (! $this->isBookable($this->bookDate, $this->bookHour, $this->bookAppliance)) {
            Flux::modal('book')->close();
            Flux::toast(variant: 'warning', text: __('toast_slot_unavailable'));

            return;
        }

        $pinPlain = $this->pin;

        try {
            $reservation = Reservation::create([
                'block' => $this->block,
                'appliance' => $this->bookAppliance,
                'reserved_date' => $this->bookDate,
                'hour' => $this->bookHour,
                'room_number' => $this->roomNumber,
                'user_id' => Auth::id(),
                'pin' => Auth::check() ? null : $pinPlain,
            ]);
        } catch (QueryException) {
            Flux::modal('book')->close();
            Flux::toast(variant: 'warning', text: __('toast_slot_taken'));

            return;
        }

        unset($this->reservations);

        Flux::modal('book')->close();

        if (Auth::check()) {
            Flux::toast(variant: 'success', text: __('toast_saved'));
        } else {
            $this->lastBookedId = $reservation->id;
            $this->dispatch('mine-add', id: $reservation->id, pin: $pinPlain);
            Flux::toast(variant: 'success', text: __('toast_saved_guest'));
        }

        $this->reset('pin');
    }

    // ------------------------------------------------------------------
    // Stornieren
    // ------------------------------------------------------------------

    public function manage(int $id, ?string $pin = null): void
    {
        $this->resetValidation();
        $this->manageId = $id;
        $this->cancelPin = (string) ($pin ?? '');
        $this->knownFromBrowser = filled($pin);

        Flux::modal('manage')->show();
    }

    #[Computed]
    public function managed(): ?Reservation
    {
        return $this->manageId ? Reservation::find($this->manageId) : null;
    }

    #[Computed]
    public function canCancelDirectly(): bool
    {
        $res = $this->managed;

        return $res !== null && $res->user_id !== null && $res->user_id === Auth::id();
    }

    #[Computed]
    public function isGuestReservation(): bool
    {
        $res = $this->managed;

        return $res !== null && $res->user_id === null;
    }

    #[Computed]
    public function managedIsPast(): bool
    {
        return $this->managed !== null && $this->managed->hasPassed();
    }

    public function cancel(): void
    {
        $res = $this->managed;

        if ($res === null) {
            Flux::modal('manage')->close();

            return;
        }

        if ($res->hasPassed()) {
            Flux::modal('manage')->close();
            Flux::toast(variant: 'warning', text: __('toast_cannot_cancel_past'));

            return;
        }

        $isAdmin = Auth::check() && Auth::user()->is_admin;

        if ($res->user_id !== null) {
            if ($res->user_id !== Auth::id() && ! $isAdmin) {
                Flux::toast(variant: 'warning', text: __('toast_belongs_other'));

                return;
            }
        } elseif (! $isAdmin) {
            $this->validate(
                ['cancelPin' => ['required', 'digits:4']],
                attributes: ['cancelPin' => 'PIN'],
            );

            $pinLimitKey = 'guest-pin:'.request()->ip().'|'.$res->id;

            if (RateLimiter::tooManyAttempts($pinLimitKey, 5)) {
                $this->knownFromBrowser = false;
                $this->cancelPin = '';
                $this->addError('cancelPin', __('toast_pin_too_many_attempts'));

                return;
            }

            if (! Hash::check($this->cancelPin, (string) $res->pin)) {
                RateLimiter::hit($pinLimitKey, 60);
                // Falsche (evtl. veraltete, aus dem Browser vorausgefüllte) PIN: manuelles
                // Eingabefeld anzeigen, damit erneutes Versuchen überhaupt möglich ist.
                $this->knownFromBrowser = false;
                $this->cancelPin = '';
                $this->addError('cancelPin', __('toast_pin_wrong'));

                return;
            }

            RateLimiter::clear($pinLimitKey);
        }

        $id = $res->id;
        $res->delete();

        unset($this->reservations);

        Flux::modal('manage')->close();
        $this->dispatch('mine-remove', id: $id);
        Flux::toast(variant: 'success', text: __('toast_cancelled'));

        $this->reset('cancelPin', 'manageId', 'knownFromBrowser');
    }

    // ------------------------------------------------------------------
    // Hilfslogik
    // ------------------------------------------------------------------

    public function isBookable(string $date, int $hour, string $appliance): bool
    {
        if (! array_key_exists($appliance, Reservation::APPLIANCES)) {
            return false;
        }

        if (! in_array($hour, Reservation::hours(), true)) {
            return false;
        }

        $slotEnd = Carbon::parse($date)->setTime($hour + 1, 0);
        $maxDate = Carbon::today()->addMonths(Reservation::MAX_ADVANCE_MONTHS)->endOfDay();

        if ($slotEnd->isPast() || Carbon::parse($date)->gt($maxDate)) {
            return false;
        }

        $key = Carbon::parse($date)->format('Y-m-d').'|'.$hour.'|'.$appliance;

        return ! $this->reservations->has($key);
    }
}; ?>

@php
    $hours = \App\Models\Reservation::hours();
    $allDays = $this->days;
    $dayTypes = $this->dayTypes;
    $applianceKeys = array_keys(\App\Models\Reservation::APPLIANCES);
    $defectiveAppliances = $this->defective;
    $colCount = count($allDays) * 3;

    // Zusammenhängende Bereiche für past/future zählen
    $pastDays = collect($allDays)->filter(fn ($d) => ($dayTypes[$d->format('Y-m-d')] ?? '') === 'past');
    $futureDays = collect($allDays)->filter(fn ($d) => ($dayTypes[$d->format('Y-m-d')] ?? '') === 'future');

    // TEMPORARY: watermark bookable days up to 2026-07-31 as testing-only. Remove this whole block once real bookings start.
    $testingOnlyUntil = \Illuminate\Support\Carbon::parse('2026-07-31');
    $testingDayIndexes = [];
    foreach ($allDays as $idx => $day) {
        if (($dayTypes[$day->format('Y-m-d')] ?? '') === 'bookable' && $day->lte($testingOnlyUntil)) {
            $testingDayIndexes[] = $idx;
        }
    }
    $testingDaysCount = count($testingDayIndexes);
    $testingLeftRem = $testingDaysCount > 0 ? 3.4 + $testingDayIndexes[0] * 3 * 3.4 : 0;
    $testingWidthRem = $testingDaysCount * 3 * 3.4;

    // Tile is wide enough to fit one full phrase plus a trailing gap, so the repeat never cuts a word off mid-tile.
    $testingWatermarkTileText = e(__('testing_only_watermark'));
    $testingWatermarkSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="467" height="112">'
        .'<text x="20" y="76" font-family="sans-serif" font-weight="bold" font-size="52" fill="rgba(161,161,170,0.5)">'.$testingWatermarkTileText.'</text>'
        .'</svg>';
    $testingWatermarkUrl = 'data:image/svg+xml,'.rawurlencode($testingWatermarkSvg);
@endphp

<div
    x-data="{ persistBlock: @js(! auth()->check()) }"
    x-init="
        if (persistBlock) {
            const saved = localStorage.getItem('washing.block');
            if (saved && saved !== $wire.block) { $wire.set('block', saved); }
        }
        $nextTick(() => {
            const grid = $refs.grid;
            const todayTh = grid?.querySelector('[data-today]');
            const stickyCol = grid?.querySelector('thead th[rowspan]');
            if (grid && todayTh && stickyCol) {
                const gridRect = grid.getBoundingClientRect();
                const thRect = todayTh.getBoundingClientRect();
                const stickyWidth = stickyCol.getBoundingClientRect().width;
                grid.scrollLeft += thRect.left - gridRect.left - stickyWidth;
            }
        });
    "
    @mine-add.window="$store.mine.add($event.detail.id, $event.detail.pin)"
    @mine-remove.window="$store.mine.remove($event.detail.id)"
    @block-changed.window="localStorage.setItem('washing.block', $event.detail.block)"
>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('schedule_title') }}</flux:heading>
            <flux:text class="mt-1">
                {{ __('schedule_subtitle') }}
            </flux:text>
        </div>

        {{-- Block-Auswahl --}}
        <flux:radio.group wire:model.live="block" variant="segmented">
            @foreach (\App\Models\Reservation::BLOCKS as $value => $label)
                <flux:radio value="{{ $value }}" label="{{ __($label) }}" />
            @endforeach
        </flux:radio.group>
    </div>

    {{-- Kalender-Tabelle: durchgehend scrollbar --}}
    <div class="relative rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800">
        {{-- Loading Overlay für Blockwechsel --}}
        <div wire:loading.block wire:target="block" class="absolute inset-0 z-50 rounded-xl bg-white/60 backdrop-blur-sm dark:bg-zinc-900/60">
            <div class="sticky top-1/2 flex w-full -translate-y-1/2 justify-center">
                <svg class="h-8 w-8 animate-spin text-sky-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            </div>
        </div>

        <div class="relative overflow-x-auto rounded-xl" x-ref="grid">
            <table class="table-fixed border-separate border-spacing-0 text-sm" style="width: {{ 3.5 + count($allDays) * 3 * 3.2 }}rem">
            <colgroup>
                <col style="width: 3.4rem">
                @foreach ($allDays as $day)
                    @foreach ($applianceKeys as $appliance)
                        <col style="width: 3.4rem{{ in_array($appliance, $defectiveAppliances) ? '; background-image: repeating-linear-gradient(-45deg, transparent, transparent 4px, rgba(161,161,170,0.18) 4px, rgba(161,161,170,0.18) 5px)' : '' }}">
                    @endforeach
                @endforeach
            </colgroup>
            <thead class="sticky top-0 z-20">
                <tr>
                    <th rowspan="2" class="sticky left-0 z-30 whitespace-nowrap border-b border-r border-zinc-200 bg-zinc-50 p-2 text-xs font-medium text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900">
                        {{ __('time') }}
                    </th>
                    @foreach ($allDays as $dayIdx => $day)
                        @php
                            $dateStr = $day->format('Y-m-d');
                            $type = $dayTypes[$dateStr];
                            $isToday = $day->isToday();
                            $isFirst = $dayIdx === 0;
                        @endphp
                        <th
                            colspan="3"
                            @if ($isToday) data-today @endif
                            @class([
                                'border-b border-b-zinc-200 p-2 text-center dark:border-b-zinc-700 bg-white dark:bg-zinc-800',
                                'border-l-2 border-l-zinc-300 dark:border-l-zinc-600' => ! $isFirst,
                                'border-l border-l-zinc-200 dark:border-l-zinc-700' => $isFirst,
                                'bg-sky-50! dark:bg-sky-950!' => $isToday,
                            ])
                        >
                            <div @class([
                                'font-semibold',
                                'text-sky-600 dark:text-sky-400' => $isToday,
                                'text-zinc-500 dark:text-zinc-400' => $type !== 'bookable' && ! $isToday,
                            ])>
                                {{ $day->isoFormat('dd') }}
                            </div>
                            <div class="text-xs text-zinc-500">{{ $day->isoFormat('DD.MM.') }}</div>
                        </th>
                    @endforeach
                </tr>
                <tr>
                    @foreach ($allDays as $dayIdx => $day)
                        @php $isFirst = $dayIdx === 0; @endphp
                        @foreach ($applianceKeys as $key)
                            <th @class([
                                'overflow-hidden border-b p-1 text-center text-[10px] font-medium whitespace-nowrap text-zinc-500 dark:border-b-zinc-700 bg-white dark:bg-zinc-800',
                                'border-l-2 border-l-zinc-300 dark:border-l-zinc-600' => $key === 'left' && ! $isFirst,
                                'border-l border-l-zinc-200 dark:border-l-zinc-700' => $key !== 'left' || $isFirst,
                            ])>
                                {{ __(['left' => 'appliance_left_short', 'right' => 'appliance_right_short', 'dryer' => 'appliance_dryer'][$key]) }}
                            </th>
                        @endforeach
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($hours as $rowIndex => $hour)
                    <tr>
                        <th class="sticky left-0 z-10 whitespace-nowrap border-r border-t border-zinc-200 bg-zinc-50 p-1 text-center text-xs font-medium text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900">
                            {{ sprintf('%02d–%02d', $hour, $hour + 1) }}
                        </th>

                        {{-- Vergangene Tage: Hinweis-Bereich --}}
                        @if ($rowIndex === 0 && $pastDays->isNotEmpty())
                            <td rowspan="{{ count($hours) + 1 }}" colspan="{{ $pastDays->count() * 3 }}"
                                class="border-l border-l-zinc-200 border-t border-t-zinc-200 bg-zinc-50/60 p-4 text-center align-middle dark:border-l-zinc-700 dark:border-t-zinc-700 dark:bg-zinc-900/40">
                                <div class="mx-auto flex max-w-[10rem] flex-col items-center gap-2 text-xs leading-relaxed text-zinc-500 break-words">
                                    <flux:icon name="trash" class="h-4 w-4" />
                                    {{ __('notice_past') }}
                                </div>
                            </td>
                        @endif

                        {{-- Buchbare Tage --}}
                        @foreach ($allDays as $day)
                            @php
                                $dateStr = $day->format('Y-m-d');
                                $type = $dayTypes[$dateStr];
                            @endphp
                            @if ($type === 'bookable')
                                @php $isToday = $day->isToday(); @endphp
                                @foreach ($applianceKeys as $appliance)
                                    @php
                                        $res = $this->reservations->get($dateStr.'|'.$hour.'|'.$appliance);
                                        $bookable = $res === null && $this->isBookable($dateStr, $hour, $appliance);
                                        $mine = $res && $res->user_id !== null && $res->user_id === auth()->id();
                                        $justBooked = $res && $res->id === $this->lastBookedId;
                                        $isDefective = in_array($appliance, $defectiveAppliances);
                                    @endphp
                                    <td @class([
                                        'border-t border-zinc-200 p-0.5 text-center dark:border-zinc-700',
                                        'bg-sky-50/40 dark:bg-sky-950/20' => $isToday && ! $isDefective,
                                        'border-l-2 border-l-zinc-300 dark:border-l-zinc-600' => $appliance === 'left' && ! $day->eq($allDays[0]),
                                        'border-l border-l-zinc-200 dark:border-l-zinc-700' => $appliance !== 'left' || $day->eq($allDays[0]),
                                    ])>
                                        @if ($res)
                                            <button
                                                x-data="{ loading: false }"
                                                type="button"
                                                data-res-id="{{ $res->id }}"
                                                title="{{ $res->room_number }}"
                                                @click="loading = true; $wire.manage({{ $res->id }}, $store.mine.get({{ $res->id }})).finally(() => loading = false)"
                                                :disabled="loading"
                                                @class([
                                                    'relative block w-full cursor-pointer truncate rounded-md px-1 py-1.5 text-xs font-medium transition',
                                                    'bg-emerald-100 text-emerald-800 hover:bg-emerald-200 dark:bg-emerald-900/50 dark:text-emerald-200' => $mine || $justBooked,
                                                    'bg-rose-100 text-rose-800 hover:bg-rose-200 dark:bg-rose-900/40 dark:text-rose-200' => ! $mine && ! $justBooked,
                                                ])
                                                :class="($store.mine.has({{ $res->id }}))
                                                    ? 'bg-emerald-100! text-emerald-800! hover:bg-emerald-200! dark:bg-emerald-900/50! dark:text-emerald-200!'
                                                    : ''"
                                            >
                                                <span x-show="!loading">{{ $res->room_number }}</span>
                                                <div x-show="loading" style="display: none;" class="absolute inset-0 flex items-center justify-center">
                                                    <svg class="h-3 w-3 animate-spin text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                </div>
                                            </button>
                                        @elseif ($bookable)
                                            <button
                                                x-data="{ loading: false }"
                                                type="button"
                                                @click="loading = true; $wire.book('{{ $dateStr }}', {{ $hour }}, '{{ $appliance }}').finally(() => loading = false)"
                                                :disabled="loading"
                                                class="relative flex h-[28px] w-full items-center justify-center cursor-pointer rounded-md px-1 py-1.5 text-xs text-zinc-400 transition hover:bg-sky-100 hover:text-sky-700 dark:hover:bg-sky-900/40 dark:hover:text-sky-300"
                                            >
                                                <svg x-show="loading" style="display: none;" class="h-4 w-4 animate-spin text-sky-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                            </button>
                                        @else
                                            <div class="px-1 py-1.5 text-xs text-zinc-400 dark:text-zinc-500">⎯</div>
                                        @endif
                                    </td>
                                @endforeach
                            @endif
                        @endforeach

                        {{-- Zukünftige Hinweis-Tage --}}
                        @if ($rowIndex === 0 && $futureDays->isNotEmpty())
                            <td rowspan="{{ count($hours) + 1 }}" colspan="{{ $futureDays->count() * 3 }}"
                                class="border-l-2 border-l-zinc-300 border-t border-t-zinc-200 bg-zinc-50/60 p-4 text-center align-middle dark:border-l-zinc-600 dark:border-t-zinc-700 dark:bg-zinc-900/40">
                                <div class="mx-auto flex max-w-[10rem] flex-col items-center gap-2 text-xs leading-relaxed text-zinc-500 break-words">
                                    <flux:icon name="calendar" class="h-4 w-4" />
                                    {{ __('notice_future') }}
                                </div>
                            </td>
                        @endif
                    </tr>
                @endforeach

                {{-- "Ab 22 Uhr"-Zeile --}}
                <tr>
                    <th class="sticky left-0 z-10 border-r border-t border-zinc-200 bg-zinc-50 p-1 text-center text-xs font-medium text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 h-14">
                        {{ __('from_21') }}
                    </th>
                    @foreach ($allDays as $day)
                        @php
                            $dateStr = $day->format('Y-m-d');
                            $type = $dayTypes[$dateStr] ?? null;
                        @endphp
                        @if ($type === 'bookable')
                            @php $dInfo = $this->doeneken[$dateStr] ?? ['open' => false, 'text' => null]; @endphp
                            <td colspan="3" @class([
                                'border-t border-zinc-200 p-1 text-center dark:border-zinc-700 bg-white dark:bg-zinc-800',
                                'border-l-2 border-l-zinc-300 dark:border-l-zinc-600' => ! $day->eq($allDays[0]),
                                'border-l border-l-zinc-200 dark:border-l-zinc-700' => $day->eq($allDays[0]),
                                'bg-amber-100! dark:bg-amber-900!' => $dInfo['open'],
                                'bg-sky-50! dark:bg-sky-950!' => $day->isToday() && ! $dInfo['open'],
                            ])>
                                @if ($dInfo['text'])
                                    <div @class([
                                        'text-xs leading-tight',
                                        'text-amber-700 dark:text-amber-300' => $dInfo['open'],
                                        'text-zinc-500 dark:text-zinc-400' => ! $dInfo['open'],
                                    ])>
                                        {!! nl2br(e($dInfo['text'])) !!}
                                    </div>
                                @endif
                            </td>
                        @endif
                    @endforeach
                </tr>
            </tbody>
        </table>

        {{-- TEMPORARY: watermark over the testing-only date range, remove once real bookings start --}}
        @if ($testingDaysCount > 0)
            <div
                class="pointer-events-none absolute inset-y-0 overflow-hidden"
                style="left: {{ $testingLeftRem }}rem; width: {{ $testingWidthRem }}rem; background-image: url('{{ $testingWatermarkUrl }}'); background-repeat: repeat-x; background-position: left center;"
            ></div>
        @endif
    </div>
    </div>

    {{-- Legende --}}
    <div class="mt-4 flex flex-wrap items-center gap-4 text-xs text-zinc-500">
        <span class="flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded bg-rose-200 dark:bg-rose-900/40"></span> {{ __('legend_booked') }}</span>
        <span class="flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded bg-emerald-200 dark:bg-emerald-900/50"></span> {{ __('legend_yours') }}</span>
        <span class="flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded border border-zinc-300 dark:border-zinc-600"></span> {{ __('legend_free') }}</span>
        <span class="flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded border border-zinc-300 dark:border-zinc-600" style="background-image: repeating-linear-gradient(-45deg, transparent, transparent 4px, rgba(161,161,170,0.18) 4px, rgba(161,161,170,0.18) 5px)"></span> {{ __('legend_defective') }}</span>
    </div>

    <div class="mt-8 flex gap-2 flex-col lg:flex-row lg:items-start">
        <flux:card class="flex-1">
            <flux:heading size="lg">{{ __('instructions_card_heading') }}</flux:heading>

            <flux:text class="mt-2">{{ __('instruction_1') }}</flux:text>
        </flux:card>

        <flux:card class="flex-1">
            <flux:heading size="lg">{{ __('rules_card_heading') }}</flux:heading>

            <ol class="mt-4 list-decimal pl-5">
            @foreach (range(1, 3) as $i)
                <li class="text-sm text-zinc-500 dark:text-white/70">
                    <flux:text class="ps-2">{{ __('rule_' . $i) }}</flux:text>
                </li>
            @endforeach
            </ol>

            <flux:text class="mt-4">{{ __('rules_below_list') }} </flux:text>
        </flux:card>
    </div>

    {{-- Buchungs-Modal --}}
    <flux:modal name="book" class="max-w-md">
        <form wire:submit="store" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('book_title') }}</flux:heading>
                @if ($bookDate)
                    <flux:text class="mt-2">
                        <strong>{{ __(\App\Models\Reservation::BLOCKS[$block] ?? $block) }}</strong> ·
                        {{ __(\App\Models\Reservation::APPLIANCES[$bookAppliance] ?? '') }}<br>
                        {{ \Illuminate\Support\Carbon::parse($bookDate)->isoFormat('dddd, DD.MM.YYYY') }},
                        {{ sprintf('%02d:00–%02d:00', $bookHour, $bookHour + 1) }}
                        @auth
                            <br>{{ __('room') }}: <strong>{{ auth()->user()->room_number }}</strong>
                        @endauth
                    </flux:text>
                    @auth
                        <flux:link :href="route('profile.edit')" wire:navigate class="mt-1 inline-block text-sm">
                            {{ __('change_room') }}
                        </flux:link>
                    @endauth
                @endif
            </div>

            @if ($bookAppliance && in_array($bookAppliance, $this->defective))
                <div class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-300">
                    {{ __('defective_warning') }}
                </div>
            @endif

            @guest
                <flux:input
                    wire:model="roomNumber"
                    :label="__('room_number')"
                    :placeholder="__('room_number_placeholder')"
                    :description="__('room_number_hint')"
                    required
                />

                <flux:input
                    wire:model="pin"
                    :label="__('pin_label')"
                    type="text"
                    inputmode="numeric"
                    maxlength="4"
                    :placeholder="__('pin_placeholder')"
                    :description="__('pin_hint')"
                    required
                />
            @endguest

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('book_submit') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Storno-Modal --}}
    <flux:modal name="manage" class="w-full max-w-lg">
        @if ($this->managed)
            <div class="space-y-6" wire:key="manage-{{ $this->managed->id }}">
                <div>
                    <flux:heading size="lg">{{ __('reservation') }}</flux:heading>
                    <flux:text class="mt-2">
                        <strong>{{ __(\App\Models\Reservation::BLOCKS[$this->managed->block] ?? $this->managed->block) }}</strong> ·
                        {{ __(\App\Models\Reservation::APPLIANCES[$this->managed->appliance] ?? '') }}<br>
                        {{ $this->managed->reserved_date->isoFormat('dddd, DD.MM.YYYY') }},
                        {{ sprintf('%02d:00–%02d:00', $this->managed->hour, $this->managed->hour + 1) }}<br>
                        {{ __('room') }}: <strong>{{ $this->managed->room_number }}</strong>
                    </flux:text>
                </div>

                @if ($this->managedIsPast)
                    <flux:callout variant="warning" icon="clock">
                        {{ __('toast_cannot_cancel_past') }}
                    </flux:callout>
                    <div class="flex justify-end">
                        <flux:modal.close><flux:button variant="ghost">{{ __('close') }}</flux:button></flux:modal.close>
                    </div>
                @elseif ($this->canCancelDirectly)
                    <flux:text>{{ __('manage_belongs_account') }}</flux:text>
                    <div class="flex justify-end gap-2">
                        <flux:modal.close><flux:button variant="ghost">{{ __('close') }}</flux:button></flux:modal.close>
                        <flux:button wire:click="cancel" variant="danger">{{ __('cancel_reservation') }}</flux:button>
                    </div>
                @elseif ($this->isGuestReservation)
                    @if ($this->knownFromBrowser)
                        <flux:callout variant="success" icon="check-circle">
                            {{ __('manage_browser_notice') }}
                        </flux:callout>
                    @else
                        <flux:input
                            wire:model="cancelPin"
                            :label="__('cancel_pin_label')"
                            type="text"
                            inputmode="numeric"
                            maxlength="4"
                            :placeholder="__('cancel_pin_placeholder')"
                        />
                    @endif
                    <div class="flex justify-end gap-2">
                        <flux:modal.close><flux:button variant="ghost">{{ __('close') }}</flux:button></flux:modal.close>
                        <flux:button wire:click="cancel" variant="danger">{{ __('cancel_reservation') }}</flux:button>
                    </div>
                @else
                    <flux:callout variant="warning" icon="lock-closed">
                        {{ __('manage_belongs_other') }}
                    </flux:callout>
                    <div class="flex justify-end gap-2">
                        <flux:modal.close><flux:button variant="ghost">{{ __('close') }}</flux:button></flux:modal.close>
                        @if (auth()->check() && auth()->user()->is_admin)
                            <flux:button wire:click="cancel" variant="danger">{{ __('admin_cancel_reservation') }}</flux:button>
                        @endif
                    </div>
                @endif
            </div>
        @endif
    </flux:modal>
</div>
