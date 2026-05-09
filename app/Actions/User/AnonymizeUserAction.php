<?php

namespace App\Actions\User;

use App\Models\User;
use App\Notifications\AccountAnonymizedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class AnonymizeUserAction
{
    public function execute(User $user, ?User $performedBy = null): void
    {
        $originalEmail = $user->email;
        $firstName     = $user->first_name;

        DB::transaction(function () use ($user, $performedBy, $originalEmail, $firstName): void {
            $user->update([
                'first_name' => 'Compte',
                'last_name'  => 'Supprimé',
                'email'      => "anonymized-{$user->id}@anonymized.local",
                'phone'      => null,
            ]);

            $user->delete();

            activity()
                ->performedOn($user)
                ->causedBy($performedBy)
                ->withProperties(['original_email' => $originalEmail])
                ->log('Compte anonymisé');

            DB::afterCommit(function () use ($originalEmail, $firstName): void {
                Notification::route('mail', $originalEmail)
                    ->notify(new AccountAnonymizedNotification($firstName));
            });
        });
    }
}
