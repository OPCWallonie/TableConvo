<?php

namespace App\Actions\Company;

use App\Models\Company;
use App\Models\User;
use App\Notifications\Company\CompanyAdminAssignedNotification;
use App\Notifications\Company\CompanyAdminRevokedNotification;
use Illuminate\Support\Facades\DB;

class AssignCompanyAdminAction
{
    /**
     * Réassignation forcée du company_admin par un super admin TableConvo.
     * Retire le rôle à tous les anciens company_admins de la company et l'assigne au nouveau.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 403 si l'acteur n'est pas super admin.
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 422 si newAdmin n'appartient pas à la company.
     */
    public function execute(User $actor, Company $company, User $newAdmin): void
    {
        abort_unless($actor->hasRole('admin'), 403);
        abort_unless($newAdmin->company_id === $company->id, 422, 'user_not_member');

        $formerAdmins = $company->members()
            ->role('company_admin')
            ->where('users.id', '!=', $newAdmin->id)
            ->get();

        DB::transaction(function () use ($formerAdmins, $newAdmin) {
            $formerAdmins->each(fn ($u) => $u->removeRole('company_admin'));
            $newAdmin->assignRole('company_admin');
        });

        DB::afterCommit(function () use ($newAdmin, $company, $formerAdmins, $actor) {
            $newAdmin->notify(new CompanyAdminAssignedNotification($company, isSuperAdminForced: true));
            $formerAdmins->each(fn ($u) => $u->notify(new CompanyAdminRevokedNotification($company)));
        });

        activity()
            ->causedBy($actor)
            ->performedOn($company)
            ->withProperties([
                'new_admin_id'    => $newAdmin->id,
                'former_admin_ids' => $formerAdmins->pluck('id'),
                'forced_by_super_admin' => true,
            ])
            ->log('Réassignation company_admin forcée par super admin');
    }
}
