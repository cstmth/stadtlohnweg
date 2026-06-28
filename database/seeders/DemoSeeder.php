<?php

namespace Database\Seeders;

use App\Models\Reservation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $blocks = array_keys(Reservation::BLOCKS);
        $appliances = array_keys(Reservation::APPLIANCES);
        $hours = range(Reservation::FIRST_HOUR, Reservation::LAST_HOUR);
        $rooms = ['A101', 'A102', 'A115', 'A115.2', 'A203', 'B110', 'B214', 'B301', 'C105', 'C210', 'C312', 'D101', 'D205'];

        $start = Carbon::today();
        $end = Carbon::today()->addMonths(Reservation::MAX_ADVANCE_MONTHS);

        $slots = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            foreach ($blocks as $block) {
                foreach ($appliances as $appliance) {
                    foreach ($hours as $hour) {
                        $slots[] = [
                            'block' => $block,
                            'appliance' => $appliance,
                            'reserved_date' => $cursor->toDateString(),
                            'hour' => $hour,
                        ];
                    }
                }
            }
            $cursor->addDay();
        }

        shuffle($slots);
        $take = (int) round(count($slots) * 0.15);

        $now = now();
        $pin = bcrypt('1234');
        $inserts = [];

        foreach (array_slice($slots, 0, $take) as $slot) {
            $inserts[] = [
                ...$slot,
                'room_number' => $rooms[array_rand($rooms)],
                'user_id' => null,
                'pin' => $pin,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($inserts, 500) as $chunk) {
            Reservation::insert($chunk);
        }

        $this->command->info("Erstellt: {$take} Reservierungen (".round($take / count($slots) * 100)." % Auslastung).");
    }
}
