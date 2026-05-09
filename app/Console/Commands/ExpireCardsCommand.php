<?php

namespace App\Console\Commands;

use App\Actions\Card\ExpireCardAction;
use Illuminate\Console\Command;

class ExpireCardsCommand extends Command
{
    protected $signature = 'cards:expire';

    protected $description = 'Marque comme expirées les cartes dont la validité a dépassé la date du jour.';

    public function handle(ExpireCardAction $action): int
    {
        $count = $action->execute();
        $this->info("{$count} carte(s) expirée(s).");
        return self::SUCCESS;
    }
}
