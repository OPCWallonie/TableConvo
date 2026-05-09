<?php

namespace App\Console\Commands;

use App\Actions\Session\SendSessionRemindersAction;
use Illuminate\Console\Command;

class SendSessionRemindersCommand extends Command
{
    protected $signature = 'sessions:send-reminders';

    protected $description = 'Envoie les rappels de session aux inscrits dont la session a lieu dans la fenêtre cible.';

    public function handle(SendSessionRemindersAction $action): int
    {
        $count = $action->execute();
        $this->info("{$count} rappel(s) envoyé(s).");
        return self::SUCCESS;
    }
}
