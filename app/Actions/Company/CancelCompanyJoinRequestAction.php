<?php

namespace App\Actions\Company;

use App\Enums\CompanyJoinRequestStatus;
use App\Models\CompanyJoinRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CancelCompanyJoinRequestAction
{
    /**
     * Le demandeur annule sa propre demande tant qu'elle est pending.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 403 si l'acteur n'est pas le demandeur.
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 409 si le statut n'est pas pending.
     */
    public function execute(User $actor, CompanyJoinRequest $joinRequest): void
    {
        abort_unless($actor->id === $joinRequest->user_id, 403);
        abort_unless($joinRequest->status === CompanyJoinRequestStatus::Pending, 409, 'not_pending');

        DB::transaction(function () use ($joinRequest) {
            $joinRequest->update([
                'status'      => CompanyJoinRequestStatus::Cancelled,
                'resolved_at' => now(),
            ]);
        });

        activity()
            ->causedBy($actor)
            ->performedOn($joinRequest)
            ->log('Demande d\'adhésion annulée par le demandeur');
    }
}
