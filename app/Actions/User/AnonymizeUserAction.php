<?php

namespace App\Actions\User;

use App\Models\User;

class AnonymizeUserAction
{
    public function execute(User $user): void
    {
        $user->update([
            'first_name' => 'Compte',
            'last_name'  => 'supprimé',
            'email'      => "deleted-{$user->id}@deleted.local",
            'phone'      => null,
        ]);
    }
}
