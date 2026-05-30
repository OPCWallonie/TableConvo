<?php

namespace App\Actions\Company;

use App\Enums\CompanyJoinRequestStatus;
use App\Models\Company;
use App\Models\CompanyJoinRequest;
use App\Models\User;
use App\Notifications\Company\CompanyJoinRequestedNotification;
use Illuminate\Support\Facades\DB;

class RequestCompanyJoinAction
{
    /**
     * Soumet une demande d'adhésion d'un user à une company existante.
     *
     * @return CompanyJoinRequest La demande créée.
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 403 si admin TableConvo.
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 409 si user déjà rattaché à une company.
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 404 si company null.
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 409 si demande pending déjà existante.
     */
    public function execute(User $user, Company $company, ?string $message = null): CompanyJoinRequest
    {
        abort_if($user->hasRole('admin'), 403);
        abort_if($user->company_id !== null, 409, 'already_attached');
        abort_if($company === null, 404);

        $alreadyPending = CompanyJoinRequest::where('user_id', $user->id)
            ->where('company_id', $company->id)
            ->where('status', CompanyJoinRequestStatus::Pending->value)
            ->exists();

        abort_if($alreadyPending, 409, 'already_requested');

        $joinRequest = DB::transaction(function () use ($user, $company, $message) {
            return CompanyJoinRequest::create([
                'company_id'   => $company->id,
                'user_id'      => $user->id,
                'status'       => CompanyJoinRequestStatus::Pending,
                'message'      => $message,
                'requested_at' => now(),
            ]);
        });

        DB::afterCommit(function () use ($company, $joinRequest) {
            $company->admins->each(
                fn ($admin) => $admin->notify(new CompanyJoinRequestedNotification($joinRequest))
            );
        });

        activity()
            ->causedBy($user)
            ->performedOn($joinRequest)
            ->withProperties(['company_id' => $company->id])
            ->log('Demande d\'adhésion soumise');

        return $joinRequest;
    }
}
