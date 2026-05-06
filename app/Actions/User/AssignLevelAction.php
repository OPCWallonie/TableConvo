<?php

namespace App\Actions\User;

use App\Models\Level;
use App\Models\User;

class AssignLevelAction
{
    public function execute(User $user, Level $level, User $admin): User
    {
        $user->update([
            'level_id' => $level->id,
            'level_assigned_at' => now(),
        ]);

        activity()
            ->performedOn($user)
            ->causedBy($admin)
            ->withProperties(['level_code' => $level->code])
            ->log("Niveau {$level->code} attribué");

        return $user->fresh();
    }
}
