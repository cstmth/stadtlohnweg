<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    private function tomorrow(): string
    {
        return Carbon::tomorrow()->toDateString();
    }

    public function test_guest_can_book_a_slot_with_a_pin(): void
    {
        Livewire::test('pages::calendar')
            ->call('book', $this->tomorrow(), 9, 'left')
            ->set('roomNumber', 'a115.2')
            ->set('pin', '4321')
            ->call('store')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('reservations', [
            'block' => 'A',
            'appliance' => 'left',
            'hour' => 9,
            'room_number' => 'A115.2',
            'user_id' => null,
        ]);
    }

    public function test_guest_must_provide_a_four_digit_pin(): void
    {
        Livewire::test('pages::calendar')
            ->call('book', $this->tomorrow(), 9, 'left')
            ->set('roomNumber', 'A115')
            ->set('pin', '12')
            ->call('store')
            ->assertHasErrors(['pin']);

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_invalid_room_number_is_rejected(): void
    {
        Livewire::test('pages::calendar')
            ->call('book', $this->tomorrow(), 9, 'left')
            ->set('roomNumber', 'Z9999')
            ->set('pin', '1234')
            ->call('store')
            ->assertHasErrors(['roomNumber']);
    }

    public function test_authenticated_user_books_without_pin_and_uses_account_room(): void
    {
        $user = User::factory()->create(['room_number' => 'B200']);

        Livewire::actingAs($user)
            ->test('pages::calendar')
            ->call('book', $this->tomorrow(), 10, 'dryer')
            ->assertSet('roomNumber', 'B200')
            ->call('store')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('reservations', [
            'room_number' => 'B200',
            'user_id' => $user->id,
            'pin' => null,
        ]);
    }

    public function test_authenticated_user_does_not_enter_room_number(): void
    {
        $user = User::factory()->create(['room_number' => 'C303']);

        // Ohne gesetzte roomNumber-Eingabe muss die Buchung über das Konto klappen.
        Livewire::actingAs($user)
            ->test('pages::calendar')
            ->call('book', $this->tomorrow(), 11, 'left')
            ->call('store')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('reservations', [
            'room_number' => 'C303',
            'user_id' => $user->id,
        ]);
    }

    public function test_block_selection_is_persisted_for_logged_in_users(): void
    {
        $user = User::factory()->create(['preferred_block' => null]);

        Livewire::actingAs($user)
            ->test('pages::calendar')
            ->set('block', 'C');

        $this->assertSame('C', $user->refresh()->preferred_block);
    }

    public function test_logged_in_user_default_block_comes_from_profile(): void
    {
        $user = User::factory()->create(['preferred_block' => 'C']);

        Livewire::actingAs($user)
            ->test('pages::calendar')
            ->assertSet('block', 'C');
    }

    public function test_a_slot_cannot_be_double_booked(): void
    {
        Reservation::factory()->create([
            'block' => 'A',
            'appliance' => 'left',
            'reserved_date' => $this->tomorrow(),
            'hour' => 9,
        ]);

        Livewire::test('pages::calendar')
            ->call('book', $this->tomorrow(), 9, 'left')
            ->set('roomNumber', 'A115')
            ->set('pin', '1234')
            ->call('store');

        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_past_slots_are_not_bookable(): void
    {
        $component = Livewire::test('pages::calendar');

        $this->assertFalse(
            $component->instance()->isBookable(Carbon::yesterday()->toDateString(), 9, 'left')
        );
    }

    public function test_slots_beyond_one_month_are_not_bookable(): void
    {
        $component = Livewire::test('pages::calendar');

        $this->assertFalse(
            $component->instance()->isBookable(Carbon::today()->addMonths(2)->toDateString(), 9, 'left')
        );
    }

    public function test_guest_can_cancel_with_correct_pin(): void
    {
        $reservation = Reservation::factory()->create([
            'reserved_date' => $this->tomorrow(),
            'hour' => 9,
            'pin' => '1234',
            'user_id' => null,
        ]);

        Livewire::test('pages::calendar')
            ->call('manage', $reservation->id)
            ->set('cancelPin', '1234')
            ->call('cancel')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('reservations', ['id' => $reservation->id]);
    }

    public function test_guest_cannot_cancel_with_wrong_pin(): void
    {
        $reservation = Reservation::factory()->create([
            'reserved_date' => $this->tomorrow(),
            'hour' => 9,
            'pin' => '1234',
            'user_id' => null,
        ]);

        Livewire::test('pages::calendar')
            ->call('manage', $reservation->id)
            ->set('cancelPin', '0000')
            ->call('cancel')
            ->assertHasErrors(['cancelPin']);

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id]);
    }

    public function test_user_cannot_cancel_another_users_reservation(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $reservation = Reservation::factory()->create([
            'reserved_date' => $this->tomorrow(),
            'hour' => 9,
            'user_id' => $owner->id,
            'pin' => null,
        ]);

        Livewire::actingAs($other)
            ->test('pages::calendar')
            ->call('manage', $reservation->id)
            ->call('cancel');

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id]);
    }

    public function test_past_reservations_cannot_be_cancelled(): void
    {
        $user = User::factory()->create();

        $reservation = Reservation::factory()->create([
            'reserved_date' => Carbon::yesterday(),
            'hour' => 9,
            'user_id' => $user->id,
            'pin' => null,
        ]);

        Livewire::actingAs($user)
            ->test('pages::calendar')
            ->call('manage', $reservation->id)
            ->call('cancel');

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id]);
    }

    public function test_purge_command_removes_reservations_past_retention(): void
    {
        $old = Reservation::factory()->create([
            'reserved_date' => Carbon::today()->subDays(Reservation::RETENTION_DAYS + 1),
        ]);

        $recent = Reservation::factory()->create([
            'reserved_date' => Carbon::today()->subDays(Reservation::RETENTION_DAYS - 1),
        ]);

        $this->artisan('reservations:purge')->assertSuccessful();

        $this->assertDatabaseMissing('reservations', ['id' => $old->id]);
        $this->assertDatabaseHas('reservations', ['id' => $recent->id]);
    }

    public function test_only_upcoming_reservations_are_shown_publicly(): void
    {
        // Eine vergangene Reservierung darf nicht im öffentlichen Plan auftauchen.
        Reservation::factory()->create([
            'block' => 'A',
            'reserved_date' => Carbon::today()->subDays(3),
            'room_number' => 'A111',
        ]);

        Livewire::test('pages::calendar')
            ->assertDontSee('A111');
    }
}
