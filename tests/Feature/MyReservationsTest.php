<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class MyReservationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_only_browser_reservations(): void
    {
        $mine = Reservation::factory()->create([
            'reserved_date' => Carbon::tomorrow(),
            'room_number' => 'A101',
            'user_id' => null,
            'pin' => '1234',
        ]);

        $other = Reservation::factory()->create([
            'reserved_date' => Carbon::tomorrow(),
            'room_number' => 'B202',
            'user_id' => null,
            'pin' => '9999',
        ]);

        Livewire::test('pages::my-reservations')
            ->call('loadBrowser', [(string) $mine->id => '1234'])
            ->assertSee('A101')
            ->assertDontSee('B202');
    }

    public function test_guest_can_delete_a_browser_reservation_without_pin_entry(): void
    {
        $reservation = Reservation::factory()->create([
            'reserved_date' => Carbon::tomorrow(),
            'user_id' => null,
            'pin' => '1234',
        ]);

        Livewire::test('pages::my-reservations')
            ->call('loadBrowser', [(string) $reservation->id => '1234'])
            ->call('confirmDelete', $reservation->id)
            ->call('delete');

        $this->assertDatabaseMissing('reservations', ['id' => $reservation->id]);
    }

    public function test_guest_cannot_delete_with_wrong_stored_pin(): void
    {
        $reservation = Reservation::factory()->create([
            'reserved_date' => Carbon::tomorrow(),
            'user_id' => null,
            'pin' => '1234',
        ]);

        Livewire::test('pages::my-reservations')
            ->call('loadBrowser', [(string) $reservation->id => '0000'])
            ->call('confirmDelete', $reservation->id)
            ->call('delete');

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id]);
    }

    public function test_authenticated_user_sees_their_reservations(): void
    {
        $user = User::factory()->create();

        $reservation = Reservation::factory()->create([
            'block' => 'A',
            'reserved_date' => Carbon::tomorrow(),
            'room_number' => 'C303',
            'user_id' => $user->id,
            'pin' => null,
        ]);

        Livewire::actingAs($user)
            ->test('pages::my-reservations')
            ->assertSee(__('block_a'))
            // Für eingeloggte Nutzer wird die Zimmernummer nicht angezeigt.
            ->assertDontSee('C303');
    }

    public function test_page_is_reachable_for_guests(): void
    {
        $this->get(route('reservations.mine'))->assertOk();
    }
}
