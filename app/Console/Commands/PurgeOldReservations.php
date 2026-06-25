<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PurgeOldReservations extends Command
{
    protected $signature = 'reservations:purge';

    protected $description = 'Löscht abgelaufene Reservierungen aus Datenschutzgründen nach der Aufbewahrungsfrist.';

    public function handle(): int
    {
        $cutoff = Carbon::today()->subDays(Reservation::RETENTION_DAYS);

        $deleted = Reservation::query()
            ->whereDate('reserved_date', '<', $cutoff)
            ->delete();

        $this->info("Gelöschte Reservierungen: {$deleted} (älter als {$cutoff->toDateString()}).");

        return self::SUCCESS;
    }
}
