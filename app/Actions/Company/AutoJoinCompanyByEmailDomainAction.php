<?php

namespace App\Actions\Company;

use App\Models\Company;
use App\Models\User;
use App\Notifications\Company\CompanyAutoJoinedNotification;
use Illuminate\Support\Facades\DB;

class AutoJoinCompanyByEmailDomainAction
{
    /**
     * Rattache immédiatement un user à une company via correspondance de domaine email.
     * Le user reçoit le rôle member (pas company_admin).
     *
     * @return Company La company rejointe.
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 403 si admin TableConvo.
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 409 si user déjà rattaché.
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 404 si company null.
     */
    public function execute(User $user, Company $company): Company
    {
        abort_if($user->hasRole('admin'), 403);
        abort_if($user->company_id !== null, 409, 'already_attached');
        abort_if($company === null, 404);

        DB::transaction(function () use ($user, $company) {
            $user->update(['company_id' => $company->id]);
        });

        activity()
            ->causedBy($user)
            ->performedOn($company)
            ->withProperties(['domain' => $user->email])
            ->log('Auto-rattachement par domaine email');

        DB::afterCommit(function () use ($user, $company) {
            $company->admins->each(
                fn ($admin) => $admin->notify(new CompanyAutoJoinedNotification($user, $company))
            );
        });

        return $company;
    }
}
