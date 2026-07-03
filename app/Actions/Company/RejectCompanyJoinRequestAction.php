<?php

namespace App\Actions\Company;

use App\Enums\CompanyJoinRequestStatus;
use App\Models\CompanyJoinRequest;
use App\Models\User;
use App\Notifications\Company\CompanyJoinRejectedNotification;
use Illuminate\Support\Facades\DB;

class RejectCompanyJoinRequestAction
{
    /**
     * Un company_admin rejette une demande d'adhésion pending.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 403 si l'acteur n'est pas company_admin de la company.
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 409 si le statut n'est pas pending.
     */
    public function execute(User $actor, CompanyJoinRequest $joinRequest, ?string $rejectionReason = null): void
    {
        abort_unless($actor->isCompanyAdmin($joinRequest->company) || $actor->hasRole('admin'), 403);
        abort_unless($joinRequest->status === CompanyJoinRequestStatus::Pending, 409, 'not_pending');

        $requester = $joinRequest->user;

        DB::transaction(function () use ($actor, $joinRequest, $rejectionReason) {
            $joinRequest->update([
                'status'              => CompanyJoinRequestStatus::Rejected,
                'resolved_at'         => now(),
                'resolved_by_user_id' => $actor->id,
                'rejection_reason'    => $rejectionReason,
            ]);
        });

        DB::afterCommit(function () use ($joinRequest, $requester) {
            $requester->notify(new CompanyJoinRejectedNotification($joinRequest));
        });

        activity()
            ->causedBy($actor)
            ->performedOn($joinRequest)
            ->withProperties(['rejected_user_id' => $requester->id, 'reason' => $rejectionReason])
            ->log('Demande d\'adhésion rejetée');
    }
}
