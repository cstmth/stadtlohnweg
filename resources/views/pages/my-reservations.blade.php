<?php

use App\Models\Reservation;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::site')] #[Title('my_reservations')] class extends Component {
    public ?int $confirmingId = null;

    /**
     * Für Gäste: in diesem Browser gemerkte Reservierungen als { id: pin }.
     *
     * @var array<string, string>
     */
    public array $browserItems = [];

    /**
     * Wird von Alpine beim Laden mit den localStorage-Daten gefüllt (nur ohne Konto relevant).
     *
     * @param  array<string, string>  $items
     */
    public function loadBrowser(array $items): void
    {
        $this->browserItems = $items;
        unset($this->reservations);
    }

    /**
     * Eigene Reservierungen: mit Konto kontoübergreifend (bis 2 Wochen rückwirkend),
     * ohne Konto nur die in diesem Browser angelegten zukünftigen Buchungen.
     *
     * @return \Illuminate\Support\Collection<int, Reservation>
     */
    #[Computed]
    public function reservations()
    {
        if (Auth::check()) {
            return Auth::user()
                ->reservations()
                ->withinRetention()
                ->orderBy('reserved_date')
                ->orderBy('hour')
                ->get();
        }

        $ids = array_keys($this->browserItems);

        if ($ids === []) {
            return collect();
        }

        return Reservation::query()
            ->whereIn('id', $ids)
            ->whereNull('user_id')
            ->upcoming()
            ->orderBy('reserved_date')
            ->orderBy('hour')
            ->get();
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmingId = $id;
        Flux::modal('confirm-delete')->show();
    }

    public function delete(): void
    {
        if (Auth::check()) {
            $reservation = Auth::user()
                ->reservations()
                ->whereKey($this->confirmingId)
                ->first();
        } else {
            // Gast: nur löschen, wenn id + passende PIN aus diesem Browser stammen.
            $reservation = Reservation::query()
                ->whereKey($this->confirmingId)
                ->whereNull('user_id')
                ->first();

            $knownPin = $this->browserItems[(string) $this->confirmingId] ?? null;

            if ($reservation && (! $knownPin || ! Hash::check($knownPin, (string) $reservation->pin))) {
                $reservation = null;
            }
        }

        if ($reservation && ! $reservation->hasPassed()) {
            $id = $reservation->id;
            $reservation->delete();
            unset($this->browserItems[(string) $id]);
            $this->dispatch('mine-remove', id: $id);
            Flux::toast(variant: 'success', text: __('toast_cancelled'));
        }

        $this->confirmingId = null;
        Flux::modal('confirm-delete')->close();
        unset($this->reservations);
    }
}; ?>

<div
    x-data
    @if (! auth()->check())
        x-init="$wire.loadBrowser($store.mine.map())"
    @endif
    @mine-remove.window="$store.mine.remove($event.detail.id)"
>
    <div class="mb-6">
        <flux:heading size="xl">{{ __('my_reservations') }}</flux:heading>
        @auth
            <flux:text class="mt-1">
                {{ __('myres_retention', ['days' => \App\Models\Reservation::RETENTION_DAYS]) }}
            </flux:text>
        @else
            <flux:text class="mt-1">
                {!! __('myres_browser_sub') !!}
            </flux:text>
        @endauth
    </div>

    @guest
        <flux:callout class="mb-6" icon="information-circle">
            {{ __('myres_browser_callout') }}
            <flux:callout.link :href="route('register')" wire:navigate>{{ __('create_account') }}</flux:callout.link>
        </flux:callout>
    @endguest

    @if ($this->reservations->isEmpty())
        <flux:callout icon="calendar-days">
            {{ __('myres_empty') }}
            <flux:callout.link :href="route('home')" wire:navigate>{{ __('myres_reserve_now') }}</flux:callout.link>
        </flux:callout>
    @else
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800">
            <table class="w-full text-sm">
                <thead class="bg-zinc-50 text-left text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-900">
                    <tr>
                        <th class="px-4 py-3">{{ __('date') }}</th>
                        <th class="px-4 py-3">{{ __('time') }}</th>
                        <th class="px-4 py-3">{{ __('block') }}</th>
                        <th class="px-4 py-3">{{ __('appliance') }}</th>
                        @guest<th class="px-4 py-3">{{ __('room') }}</th>@endguest
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700">
                    @foreach ($this->reservations as $reservation)
                        @php $past = $reservation->hasPassed(); @endphp
                        <tr class="{{ $past ? 'text-zinc-400 dark:text-zinc-500' : '' }}">
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ $reservation->reserved_date->isoFormat('dd, DD.MM.YYYY') }}
                                @if ($reservation->reserved_date->isToday())
                                    <flux:badge size="sm" color="sky" inset="top bottom">{{ __('badge_today') }}</flux:badge>
                                @elseif ($past)
                                    <flux:badge size="sm" color="zinc" inset="top bottom">{{ __('badge_past') }}</flux:badge>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ sprintf('%02d:00–%02d:00', $reservation->hour, $reservation->hour + 1) }}</td>
                            <td class="px-4 py-3">{{ __(\App\Models\Reservation::BLOCKS[$reservation->block] ?? $reservation->block) }}</td>
                            <td class="px-4 py-3">{{ __(\App\Models\Reservation::APPLIANCES[$reservation->appliance] ?? $reservation->appliance) }}</td>
                            @guest<td class="px-4 py-3 font-medium">{{ $reservation->room_number }}</td>@endguest
                            <td class="px-4 py-3 text-right">
                                @unless ($past)
                                    <flux:button size="xs" variant="subtle" wire:click="confirmDelete({{ $reservation->id }})">
                                        {{ __('cancel_reservation') }}
                                    </flux:button>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <flux:modal name="confirm-delete" class="max-w-sm">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('cancel_reservation_q') }}</flux:heading>
                <flux:text class="mt-2">{{ __('confirm_cancel_notice') }}</flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('cancel') }}</flux:button></flux:modal.close>
                <flux:button wire:click="delete" variant="danger">{{ __('cancel_reservation') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
