<?php

namespace App\Policies;

use App\Models\CompanyJoinRequest;
use App\Models\User;

class CompanyJoinRequestPolicy
{
    public function view(User $actor, CompanyJoinRequest $joinRequest): bool
    {
        return $actor->id === $joinRequest->user_id
            || $actor->isCompanyAdmin($joinRequest->company)
            || $actor->hasRole('admin');
    }

    public function approve(User $actor, CompanyJoinRequest $joinRequest): bool
    {
        return $actor->isCompanyAdmin($joinRequest->company) || $actor->hasRole('admin');
    }

    public function reject(User $actor, CompanyJoinRequest $joinRequest): bool
    {
        return $actor->isCompanyAdmin($joinRequest->company) || $actor->hasRole('admin');
    }

    public function cancel(User $actor, CompanyJoinRequest $joinRequest): bool
    {
        return $actor->id === $joinRequest->user_id;
    }
}
