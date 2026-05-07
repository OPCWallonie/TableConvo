<?php

namespace App\Actions\Registration;

use App\Enums\RegistrationStatus;
use App\Models\Card;
use App\Models\ConversationTable;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RegisterUserToTableAction
{
    public function __construct(
        private readonly CheckRegistrationRulesAction $checkRules
    ) {}

    public function execute(User $user, ConversationTable $table, bool $forWaitlist = false): Registration
    {
        $check = $this->checkRules->execute($user, $table, $forWaitlist);

        if (! $check['allowed']) {
            throw new RuntimeException($check['reason']);
        }

        return DB::transaction(function () use ($user, $table, $forWaitlist) {
            if ($forWaitlist) {
                $position = $table->registrations()
                    ->where('status', RegistrationStatus::Waitlist->value)
                    ->max('waitlist_position');

                $registration = Registration::create([
                    'user_id' => $user->id,
                    'conversation_table_id' => $table->id,
                    'card_id' => null,
                    'status' => RegistrationStatus::Waitlist,
                    'registered_at' => now(),
                    'waitlist_position' => ($position ?? 0) + 1,
                ]);

                activity()
                    ->performedOn($registration)
                    ->causedBy($user)
                    ->log('Inscription en liste d\'attente');

                return $registration;
            }

            $card = $user->activeCards()->first();
            $card->decrement('sessions_remaining');

            $registration = Registration::create([
                'user_id' => $user->id,
                'conversation_table_id' => $table->id,
                'card_id' => $card->id,
                'status' => RegistrationStatus::Registered,
                'registered_at' => now(),
            ]);

            activity()
                ->performedOn($registration)
                ->causedBy($user)
                ->log('Inscription confirmée');

            return $registration;
        });
    }
}
