<?php

use App\Models\Reservation;
use Flux\Flux;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::site')] #[Title('schedule_title')] class extends Component {
    /** Aktuell angezeigter Block/Keller ('A' oder 'C'). */
    public string $block = 'A';

    /** Montag der angezeigten Woche (Y-m-d). */
    public string $weekStart = '';

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

    public function mount(): void
    {
        $this->weekStart = $this->minWeekStart()->toDateString();

        // Mit Konto: zuletzt gewählten Block aus der Datenbank vorbelegen.
        if (Auth::check() && filled(Auth::user()->preferred_block)) {
            $this->block = Auth::user()->preferred_block;
        }
    }

    /**
     * Block-Wechsel merken: in der Datenbank (mit Konto) und im Browser (localStorage).
     */
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
    // Wochen-Navigation
    // ------------------------------------------------------------------

    private function minWeekStart(): Carbon
    {
        return Carbon::today()->startOfWeek(Carbon::MONDAY);
    }

    private function maxWeekStart(): Carbon
    {
        return Carbon::today()
            ->addMonths(Reservation::MAX_ADVANCE_MONTHS)
            ->startOfWeek(Carbon::MONDAY);
    }

    public function previousWeek(): void
    {
        $target = Carbon::parse($this->weekStart)->subWeek();
        $this->weekStart = $target->max($this->minWeekStart())->toDateString();
    }

    public function nextWeek(): void
    {
        $target = Carbon::parse($this->weekStart)->addWeek();
        $this->weekStart = $target->min($this->maxWeekStart())->toDateString();
    }

    public function today(): void
    {
        $this->weekStart = $this->minWeekStart()->toDateString();
    }

    #[Computed]
    public function canGoBack(): bool
    {
        return Carbon::parse($this->weekStart)->gt($this->minWeekStart());
    }

    #[Computed]
    public function canGoForward(): bool
    {
        return Carbon::parse($this->weekStart)->lt($this->maxWeekStart());
    }

    /** @return array<int, \Illuminate\Support\Carbon> */
    #[Computed]
    public function days(): array
    {
        $start = Carbon::parse($this->weekStart);

        return collect(range(0, 6))
            ->map(fn (int $offset) => $start->copy()->addDays($offset))
            ->all();
    }

    /**
     * Teilt die Woche in zusammenhängende Spalten-Zonen: vergangene Tage,
     * buchbare Tage (heute … +1 Monat) und zu weit entfernte Tage.
     *
     * @return array{past: array<int, Carbon>, bookable: array<int, Carbon>, future: array<int, Carbon>}
     */
    #[Computed]
    public function dayZones(): array
    {
        $today = Carbon::today();
        $maxDate = Carbon::today()->addMonths(Reservation::MAX_ADVANCE_MONTHS);

        $past = $bookable = $future = [];

        foreach ($this->days as $day) {
            if ($day->lt($today)) {
                $past[] = $day;
            } elseif ($day->gt($maxDate)) {
                $future[] = $day;
            } else {
                $bookable[] = $day;
            }
        }

        return ['past' => $past, 'bookable' => $bookable, 'future' => $future];
    }

    /**
     * Reservierungen der angezeigten Woche, indiziert nach "Datum|Stunde|Gerät".
     *
     * @return \Illuminate\Support\Collection<string, Reservation>
     */
    #[Computed]
    public function reservations()
    {
        $start = Carbon::parse($this->weekStart);
        $end = $start->copy()->addDays(6);

        return Reservation::query()
            ->where('block', $this->block)
            ->whereBetween('reserved_date', [$start->toDateString(), $end->toDateString()])
            ->upcoming()
            ->get()
            ->keyBy(fn (Reservation $r) => $r->reserved_date->format('Y-m-d').'|'.$r->hour.'|'.$r->appliance);
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
            // Mit Konto: Zimmernummer stammt aus dem Profil, keine Eingabe nötig.
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
            // Eindeutiger Index verhindert Doppelbuchungen bei gleichzeitigem Zugriff.
            Flux::modal('book')->close();
            Flux::toast(variant: 'warning', text: __('toast_slot_taken'));

            return;
        }

        // Den Belegungsplan neu berechnen, damit die Buchung sofort sichtbar ist.
        unset($this->reservations);

        Flux::modal('book')->close();

        if (Auth::check()) {
            Flux::toast(variant: 'success', text: __('toast_saved'));
        } else {
            // Gastbuchung im Browser merken (grüne Markierung + PIN-Vorausfüllung).
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

    /** Darf die aktuell betrachtete Reservierung von dieser Person direkt storniert werden? */
    #[Computed]
    public function canCancelDirectly(): bool
    {
        $res = $this->managed;

        return $res !== null && $res->user_id !== null && $res->user_id === Auth::id();
    }

    /** Handelt es sich um eine Gastbuchung (PIN-geschützt)? */
    #[Computed]
    public function isGuestReservation(): bool
    {
        $res = $this->managed;

        return $res !== null && $res->user_id === null;
    }

    public function cancel(): void
    {
        $res = $this->managed;

        if ($res === null) {
            Flux::modal('manage')->close();

            return;
        }

        if ($res->user_id !== null) {
            // Konto-Buchung: nur die Inhaberin/der Inhaber darf stornieren.
            if ($res->user_id !== Auth::id()) {
                Flux::toast(variant: 'warning', text: __('toast_belongs_other'));

                return;
            }
        } else {
            // Gastbuchung: PIN prüfen (im Browser bekannt oder manuell eingegeben).
            $this->validate(
                ['cancelPin' => ['required', 'digits:4']],
                attributes: ['cancelPin' => 'PIN'],
            );

            if (! Hash::check($this->cancelPin, (string) $res->pin)) {
                $this->addError('cancelPin', __('toast_pin_wrong'));

                return;
            }
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

    /** Ist der Slot grundsätzlich buchbar (frei, in der Zukunft, innerhalb eines Monats)? */
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

<div
    x-data="{ persistBlock: @js(! auth()->check()) }"
    x-init="
        if (persistBlock) {
            const saved = localStorage.getItem('washing.block');
            if (saved && saved !== $wire.block) { $wire.set('block', saved); }
        }
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
                <flux:radio value="{{ $value }}" label="{{ $label }}" />
            @endforeach
        </flux:radio.group>
    </div>

    {{-- Wochen-Navigation: Mitte bleibt fix, auch wenn ein Button fehlt --}}
    <div class="mb-4 flex items-center gap-3">
        <div class="flex w-28 shrink-0 justify-start">
            @if ($this->canGoBack)
                <flux:button icon="chevron-left" size="sm" wire:click="previousWeek">{{ __('week') }}</flux:button>
            @endif
        </div>

        <div class="flex-1 text-center">
            <flux:heading size="lg">
                {{ \Illuminate\Support\Carbon::parse($weekStart)->isoFormat('DD. MMM') }}
                –
                {{ \Illuminate\Support\Carbon::parse($weekStart)->addDays(6)->isoFormat('DD. MMM YYYY') }}
            </flux:heading>
            <flux:button variant="ghost" size="xs" wire:click="today" class="mt-1">{{ __('today_button') }}</flux:button>
        </div>

        <div class="flex w-28 shrink-0 justify-end">
            @if ($this->canGoForward)
                <flux:button icon:trailing="chevron-right" size="sm" wire:click="nextWeek">{{ __('week') }}</flux:button>
            @endif
        </div>
    </div>

    {{-- Kalender-Tabelle --}}
    @php
        $hours = \App\Models\Reservation::hours();
        $rowCount = count($hours);
        $zones = $this->dayZones;
    @endphp
    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800">
        <table class="w-full min-w-[1180px] table-fixed border-collapse text-sm">
            {{-- Feste Spaltenbreiten: Uhr-Spalte schmal, die 21 Gerätespalten exakt gleich breit --}}
            <colgroup>
                <col style="width: 3.5rem">
                @foreach ($this->days as $day)
                    @foreach (array_keys(\App\Models\Reservation::APPLIANCES) as $appliance)
                        <col>
                    @endforeach
                @endforeach
            </colgroup>
            <thead>
                <tr>
                    <th rowspan="2" class="sticky left-0 z-10 whitespace-nowrap border-b border-r border-zinc-200 bg-zinc-50 p-2 text-xs font-medium text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900">
                        {{ __('time') }}
                    </th>
                    @foreach ($this->days as $day)
                        @php $isToday = $day->isToday(); @endphp
                        <th colspan="3" class="border-b border-l border-zinc-200 p-2 text-center dark:border-zinc-700 {{ $isToday ? 'bg-sky-50 dark:bg-sky-950/40' : '' }}">
                            <div class="font-semibold {{ $isToday ? 'text-sky-600 dark:text-sky-400' : '' }}">
                                {{ $day->isoFormat('dd') }}
                            </div>
                            <div class="text-xs text-zinc-500">{{ $day->isoFormat('DD.MM.') }}</div>
                        </th>
                    @endforeach
                </tr>
                <tr>
                    @foreach ($this->days as $day)
                        @foreach (\App\Models\Reservation::APPLIANCES as $key => $label)
                            <th class="overflow-hidden border-b border-l border-zinc-200 p-1 text-center text-[10px] font-medium whitespace-nowrap text-zinc-500 dark:border-zinc-700">
                                <flux:tooltip :content="__($label)">
                                    <span>{{ __(['left' => 'appliance_left_short', 'right' => 'appliance_right_short', 'dryer' => 'appliance_dryer'][$key]) }}</span>
                                </flux:tooltip>
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

                        {{-- Bereich vor dem heutigen Datum: ein zentrierter Hinweis über alle Zeilen --}}
                        @if ($rowIndex === 0 && count($zones['past']) > 0)
                            <td rowspan="{{ $rowCount }}" colspan="{{ count($zones['past']) * 3 }}"
                                class="border-l border-t border-zinc-200 bg-zinc-50/60 p-4 text-center align-middle dark:border-zinc-700 dark:bg-zinc-900/40">
                                <span class="mx-auto block max-w-[10rem] text-xs leading-relaxed text-zinc-400 break-words">
                                    {{ __('notice_past') }}
                                </span>
                            </td>
                        @endif

                        {{-- Buchbarer Bereich --}}
                        @foreach ($zones['bookable'] as $day)
                            @php $dateStr = $day->format('Y-m-d'); $isToday = $day->isToday(); @endphp
                            @foreach (array_keys(\App\Models\Reservation::APPLIANCES) as $appliance)
                                @php
                                    $res = $this->reservations->get($dateStr.'|'.$hour.'|'.$appliance);
                                    $bookable = $res === null && $this->isBookable($dateStr, $hour, $appliance);
                                    $mine = $res && $res->user_id !== null && $res->user_id === auth()->id();
                                @endphp
                                <td class="border-l border-t border-zinc-200 p-0.5 text-center dark:border-zinc-700 {{ $isToday ? 'bg-sky-50/40 dark:bg-sky-950/20' : '' }}">
                                    @if ($res)
                                        <button
                                            type="button"
                                            data-res-id="{{ $res->id }}"
                                            title="{{ $res->room_number }}"
                                            @click="$wire.manage({{ $res->id }}, $store.mine.get({{ $res->id }}))"
                                            class="block w-full cursor-pointer truncate rounded-md px-1 py-1.5 text-xs font-medium transition"
                                            :class="($store.mine.has({{ $res->id }}) || @js($mine))
                                                ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200 dark:bg-emerald-900/50 dark:text-emerald-200'
                                                : 'bg-rose-100 text-rose-800 hover:bg-rose-200 dark:bg-rose-900/40 dark:text-rose-200'"
                                        >
                                            {{ $res->room_number }}
                                        </button>
                                    @elseif ($bookable)
                                        <button
                                            type="button"
                                            wire:click="book('{{ $dateStr }}', {{ $hour }}, '{{ $appliance }}')"
                                            class="block w-full cursor-pointer rounded-md px-1 py-1.5 text-xs text-zinc-400 transition hover:bg-sky-100 hover:text-sky-700 dark:hover:bg-sky-900/40 dark:hover:text-sky-300"
                                        >
                                            {{ __('free') }}
                                        </button>
                                    @else
                                        <div class="px-1 py-1.5 text-xs text-zinc-300 dark:text-zinc-600">–</div>
                                    @endif
                                </td>
                            @endforeach
                        @endforeach

                        {{-- Bereich hinter dem Buchungszeitraum: ein zentrierter Hinweis über alle Zeilen --}}
                        @if ($rowIndex === 0 && count($zones['future']) > 0)
                            <td rowspan="{{ $rowCount }}" colspan="{{ count($zones['future']) * 3 }}"
                                class="border-l border-t border-zinc-200 bg-zinc-50/60 p-4 text-center align-middle dark:border-zinc-700 dark:bg-zinc-900/40">
                                <span class="mx-auto block max-w-[10rem] text-xs leading-relaxed text-zinc-400 break-words">
                                    {{ __('notice_future') }}
                                </span>
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Legende --}}
    <div class="mt-4 flex flex-wrap items-center gap-4 text-xs text-zinc-500">
        <span class="flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded bg-rose-200 dark:bg-rose-900/40"></span> {{ __('legend_booked') }}</span>
        <span class="flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded bg-emerald-200 dark:bg-emerald-900/50"></span> {{ __('legend_yours') }}</span>
        <span class="flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded border border-zinc-300 dark:border-zinc-600"></span> {{ __('legend_free') }}</span>
    </div>

    {{-- Buchungs-Modal --}}
    <flux:modal name="book" class="max-w-md">
        <form wire:submit="store" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('book_title') }}</flux:heading>
                @if ($bookDate)
                    <flux:text class="mt-2">
                        <strong>{{ \App\Models\Reservation::BLOCKS[$block] ?? $block }}</strong> ·
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
    <flux:modal name="manage" class="max-w-md">
        @if ($this->managed)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('reservation') }}</flux:heading>
                    <flux:text class="mt-2">
                        <strong>{{ \App\Models\Reservation::BLOCKS[$this->managed->block] ?? $this->managed->block }}</strong> ·
                        {{ __(\App\Models\Reservation::APPLIANCES[$this->managed->appliance] ?? '') }}<br>
                        {{ $this->managed->reserved_date->isoFormat('dddd, DD.MM.YYYY') }},
                        {{ sprintf('%02d:00–%02d:00', $this->managed->hour, $this->managed->hour + 1) }}<br>
                        {{ __('room') }}: <strong>{{ $this->managed->room_number }}</strong>
                    </flux:text>
                </div>

                @if ($this->canCancelDirectly)
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
                    <div class="flex justify-end">
                        <flux:modal.close><flux:button variant="ghost">{{ __('close') }}</flux:button></flux:modal.close>
                    </div>
                @endif
            </div>
        @endif
    </flux:modal>
</div>
