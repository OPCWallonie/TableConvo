<?php

namespace App\Policies;

use App\Models\ConversationTable;
use App\Models\User;

class ConversationTablePolicy
{
    public function viewAny(User $user): bool  { return $user->hasRole('admin'); }
    public function view(User $user, ConversationTable $record): bool { return $user->hasRole('admin'); }
    public function create(User $user): bool   { return $user->hasRole('admin'); }
    public function update(User $user, ConversationTable $record): bool { return $user->hasRole('admin'); }
    public function delete(User $user, ConversationTable $record): bool { return $user->hasRole('admin'); }
    public function restore(User $user, ConversationTable $record): bool { return $user->hasRole('admin'); }
    public function forceDelete(User $user, ConversationTable $record): bool { return $user->hasRole('admin'); }
}
