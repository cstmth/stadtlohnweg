<?php

namespace Database\Factories;

use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    public function definition(): array
    {
        $letter = fake()->randomElement(['A', 'B', 'C', 'D']);
        $number = str_pad((string) fake()->numberBetween(0, 499), 3, '0', STR_PAD_LEFT);
        $room = $letter.$number.(fake()->boolean(25) ? '.'.fake()->numberBetween(1, 2) : '');

        return [
            'block' => fake()->randomElement(array_keys(Reservation::BLOCKS)),
            'appliance' => fake()->randomElement(array_keys(Reservation::APPLIANCES)),
            'reserved_date' => Carbon::today()->addDays(fake()->numberBetween(0, 13)),
            'hour' => fake()->numberBetween(Reservation::FIRST_HOUR, Reservation::LAST_HOUR),
            'room_number' => $room,
            'user_id' => null,
            'pin' => '1234',
        ];
    }

    public function guest(): static
    {
        return $this->state(fn () => ['user_id' => null, 'pin' => '1234']);
    }
}
