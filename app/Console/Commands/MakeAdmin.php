<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeAdmin extends Command
{
    protected $signature = 'admin:grant {email}';

    protected $description = 'Ernennt einen Nutzer zum Admin.';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error("Kein Nutzer mit E-Mail \"{$this->argument('email')}\" gefunden.");

            return self::FAILURE;
        }

        $user->update(['is_admin' => true]);

        $this->info("{$user->name} ({$user->email}) ist jetzt Admin.");

        return self::SUCCESS;
    }
}
