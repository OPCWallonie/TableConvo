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

    public function execute(User $user, ConversationTable $table): Registration
    {
        $check = $this->checkRules->execute($user, $table);

        if (! $check['allowed']) {
            throw new RuntimeException($check['reason']);
        }

        return DB::transaction(function () use ($user, $table) {
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
