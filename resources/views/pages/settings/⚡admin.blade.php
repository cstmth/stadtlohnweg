<?php

use App\Models\DefectiveAppliance;
use App\Models\DoenekenEvent;
use App\Models\Reservation;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::site')] #[Title('admin')] class extends Component {
    public string $newAdminEmail = '';

    public string $eventDate = '';
    public string $eventText = '';
    public bool $eventClosed = false;

    public function mount(): void
    {
        abort_unless(Auth::user()->is_admin, 403);
    }

    public function addAdmin(): void
    {
        $this->validate([
            'newAdminEmail' => ['required', 'email'],
        ]);

        $user = User::where('email', $this->newAdminEmail)->first();

        if (! $user) {
            $this->addError('newAdminEmail', __('admin_user_not_found'));

            return;
        }

        if ($user->is_admin) {
            $this->addError('newAdminEmail', __('admin_already'));

            return;
        }

        $user->update(['is_admin' => true]);
        $this->reset('newAdminEmail');
        Flux::toast(variant: 'success', text: __('admin_added'));
    }

    public function removeAdmin(int $userId): void
    {
        abort_if($userId === Auth::id(), 403);

        User::where('id', $userId)->update(['is_admin' => false]);
        Flux::toast(variant: 'success', text: __('admin_removed'));
    }

    public function toggleDefective(string $block, string $appliance): void
    {
        $existing = DefectiveAppliance::where('block', $block)
            ->where('appliance', $appliance)
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            DefectiveAppliance::create(['block' => $block, 'appliance' => $appliance]);
        }
    }

    public function addEvent(): void
    {
        $this->validate([
            'eventDate' => ['required', 'date'],
            'eventText' => ['nullable', 'string', 'max:255'],
        ]);

        DoenekenEvent::updateOrCreate(
            ['date' => $this->eventDate],
            [
                'closed' => $this->eventClosed,
                'custom_text' => $this->eventText ?: null,
            ],
        );

        $this->reset('eventDate', 'eventText', 'eventClosed');
        Flux::toast(variant: 'success', text: __('doeneken_event_saved'));
    }

    public function deleteEvent(int $id): void
    {
        DoenekenEvent::where('id', $id)->delete();
        Flux::toast(variant: 'success', text: __('doeneken_event_deleted'));
    }

    public function with(): array
    {
        return [
            'admins' => User::where('is_admin', true)->orderBy('name')->get(),
            'defective' => DefectiveAppliance::all()->map(fn ($d) => $d->block.'|'.$d->appliance)->toArray(),
            'blocks' => Reservation::BLOCKS,
            'appliances' => Reservation::APPLIANCES,
            'events' => DoenekenEvent::orderBy('date')->get(),
        ];
    }
}; ?>

<section class="mx-auto w-full max-w-2xl">
    <flux:heading size="xl" level="1" class="mb-6">{{ __('admin') }}</flux:heading>

    {{-- Admin-Verwaltung --}}
    <div class="space-y-6">
            <div>
                <flux:heading>{{ __('admin_manage') }}</flux:heading>
                <flux:subheading>{{ __('admin_manage_sub') }}</flux:subheading>
            </div>

            <div class="space-y-2">
                @foreach ($admins as $admin)
                    <div class="flex items-center justify-between rounded-lg border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                        <div>
                            <p class="text-sm font-medium">{{ $admin->name }}</p>
                            <p class="text-xs text-zinc-500">{{ $admin->email }}</p>
                        </div>
                        @if ($admin->id !== Auth::id())
                            <flux:button variant="ghost" size="sm" icon="trash" icon:variant="outline"
                                wire:click="removeAdmin({{ $admin->id }})"
                                wire:confirm="{{ __('admin_remove_confirm') }}"
                                class="text-red-500 hover:text-red-600"
                            />
                        @endif
                    </div>
                @endforeach
            </div>

            <form wire:submit="addAdmin" class="flex gap-2">
                <flux:input wire:model="newAdminEmail" type="email" :placeholder="__('email_address')" class="flex-1" />
                <flux:button variant="primary" type="submit">{{ __('admin_add') }}</flux:button>
            </form>
        </div>

        {{-- Defekte Geräte --}}
        <div class="mt-12 space-y-6">
            <div>
                <flux:heading>{{ __('defective_heading') }}</flux:heading>
                <flux:subheading>{{ __('defective_sub') }}</flux:subheading>
            </div>

            <div class="space-y-2">
                @foreach ($blocks as $blockKey => $blockLabel)
                    @foreach ($appliances as $applianceKey => $applianceLabel)
                        @php $isDefective = in_array($blockKey.'|'.$applianceKey, $defective); @endphp
                        <button
                            type="button"
                            wire:click="toggleDefective('{{ $blockKey }}', '{{ $applianceKey }}')"
                            class="flex w-full items-center justify-between rounded-lg border px-4 py-3 cursor-pointer text-start transition-colors {{ $isDefective ? 'border-red-300 bg-red-50 dark:border-red-800 dark:bg-red-950/30' : 'border-zinc-200 dark:border-zinc-700' }}"
                        >
                            <span class="text-sm">
                                {{ __($blockLabel) }} · {{ __($applianceLabel) }}
                            </span>
                            <flux:checkbox
                                wire:key="defective-{{ $blockKey }}-{{ $applianceKey }}-{{ $isDefective ? '1' : '0' }}"
                                :checked="$isDefective"
                                tabindex="-1"
                                class="pointer-events-none"
                            />
                        </button>
                    @endforeach
                @endforeach
            </div>
        </div>

        {{-- Döneken-Events --}}
        <div class="mt-12 space-y-6">
            <div>
                <flux:heading>{{ __('doeneken_heading') }}</flux:heading>
                <flux:subheading>{{ __('doeneken_sub') }}</flux:subheading>
            </div>

            @if ($events->isNotEmpty())
                <div class="space-y-2">
                    @foreach ($events as $event)
                        <div class="flex items-center justify-between rounded-lg border px-4 py-3 {{ $event->closed ? 'border-zinc-300 bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-800' : 'border-amber-300 bg-amber-50 dark:border-amber-700 dark:bg-amber-950/30' }}">
                            <div>
                                <p class="text-sm font-medium">{{ $event->date->isoFormat('dd, DD.MM.YYYY') }}</p>
                                <p class="text-xs text-zinc-500">
                                    @if ($event->closed)
                                        {{ $event->custom_text ?: __('doeneken_irregular_closed') }}
                                    @elseif ($event->custom_text)
                                        {{ $event->custom_text }}
                                    @else
                                        {{ __('doeneken_open_label') }}
                                    @endif
                                </p>
                            </div>
                            <flux:button variant="ghost" size="sm" icon="trash" icon:variant="outline"
                                wire:click="deleteEvent({{ $event->id }})"
                                class="text-red-500 hover:text-red-600"
                            />
                        </div>
                    @endforeach
                </div>
            @endif

            <form wire:submit="addEvent" class="space-y-3">
                <flux:input wire:model="eventDate" type="date" />
                <flux:input wire:model="eventText" type="text" :placeholder="__('doeneken_text_placeholder')" :description="__('doeneken_text_hint')" />
                <flux:radio.group wire:model="eventClosed" variant="segmented">
                    <flux:radio value="0" :label="__('doeneken_open_toggle')" />
                    <flux:radio value="1" :label="__('doeneken_closed_toggle')" />
                </flux:radio.group>
                <div class="flex justify-end">
                    <flux:button variant="primary" type="submit">{{ __('save') }}</flux:button>
                </div>
            </form>
        </div>
</section>
