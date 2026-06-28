<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PurgeInactiveAccounts extends Command
{
    protected $signature = 'accounts:purge';

    protected $description = 'Löscht Nutzerkonten, die seit über zwei Jahren inaktiv sind (DSGVO).';

    public function handle(): int
    {
        $cutoff = Carbon::now()->subYears(2);

        $users = User::query()
            ->where('updated_at', '<', $cutoff)
            ->whereDoesntHave('reservations')
            ->get();

        $deleted = 0;

        foreach ($users as $user) {
            $user->reservations()->delete();
            $user->delete();
            $deleted++;
        }

        $this->info("Gelöschte Konten: {$deleted} (inaktiv seit {$cutoff->toDateString()}).");

        return self::SUCCESS;
    }
}
