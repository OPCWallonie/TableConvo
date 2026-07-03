<?php

namespace App\Actions\Company;

use App\Enums\CompanyJoinRequestStatus;
use App\Models\CompanyJoinRequest;
use App\Models\User;
use App\Notifications\Company\CompanyJoinApprovedNotification;
use Illuminate\Support\Facades\DB;

class ApproveCompanyJoinRequestAction
{
    /**
     * Un company_admin approuve une demande d'adhésion pending.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 403 si l'acteur n'est pas company_admin de la company.
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 409 si le statut n'est pas pending.
     */
    public function execute(User $actor, CompanyJoinRequest $joinRequest): void
    {
        abort_unless($actor->isCompanyAdmin($joinRequest->company) || $actor->hasRole('admin'), 403);
        abort_unless($joinRequest->status === CompanyJoinRequestStatus::Pending, 409, 'not_pending');

        $requester = $joinRequest->user;

        DB::transaction(function () use ($actor, $joinRequest, $requester) {
            $joinRequest->update([
                'status'              => CompanyJoinRequestStatus::Approved,
                'resolved_at'         => now(),
                'resolved_by_user_id' => $actor->id,
            ]);

            $requester->update(['company_id' => $joinRequest->company_id]);
        });

        DB::afterCommit(function () use ($joinRequest, $requester) {
            $requester->notify(new CompanyJoinApprovedNotification($joinRequest));
        });

        activity()
            ->causedBy($actor)
            ->performedOn($joinRequest)
            ->withProperties(['approved_user_id' => $requester->id])
            ->log('Demande d\'adhésion approuvée');
    }
}
