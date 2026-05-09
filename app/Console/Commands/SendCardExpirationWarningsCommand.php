<?php

namespace App\Console\Commands;

use App\Actions\Card\SendExpirationWarningsAction;
use Illuminate\Console\Command;

class SendCardExpirationWarningsCommand extends Command
{
    protected $signature = 'cards:warn-expiration';

    protected $description = "Envoie les alertes d'expiration imminente aux utilisateurs concernés.";

    public function handle(SendExpirationWarningsAction $action): int
    {
        $count = $action->execute();
        $this->info("{$count} alerte(s) envoyée(s).");
        return self::SUCCESS;
    }
}
