<?php

namespace App\Actions\Company;

use App\Models\Company;
use App\Models\User;
use App\Notifications\Company\CompanyAdminAssignedNotification;
use App\Notifications\Company\CompanyAdminRevokedNotification;
use App\Notifications\Company\CompanyAdminVacantNotification;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class TransferCompanyAdminAction
{
    /**
     * Succession automatique : désigne le membre le plus ancien restant comme company_admin.
     * Si aucun candidat éligible → notifie tous les admins TableConvo.
     *
     * @param  Company   $company       La company dont le company_admin est vacant.
     * @param  User|null $departingUser Le user qui part (pour lui retirer le rôle s'il l'a encore).
     */
    public function execute(Company $company, ?User $departingUser = null): void
    {
        // Retirer le rôle au partant si nécessaire
        if ($departingUser && $departingUser->hasRole('company_admin')) {
            $departingUser->removeRole('company_admin');
        }

        // Chercher le membre actif le plus ancien (hors le partant)
        $successor = $company->members()
            ->when($departingUser, fn ($q) => $q->where('users.id', '!=', $departingUser->id))
            ->whereNull('users.deleted_at')
            ->oldest('users.created_at')
            ->orderBy('users.id')
            ->first();

        if ($successor === null) {
            // Cas pathologique : plus aucun membre éligible
            DB::afterCommit(function () use ($company) {
                $superAdmins = User::role('admin')->get();
                $superAdmins->each(fn ($admin) => $admin->notify(new CompanyAdminVacantNotification($company)));
            });

            activity()
                ->performedOn($company)
                ->withProperties(['reason' => 'no_eligible_successor'])
                ->log('Société sans administrateur — intervention requise');

            return;
        }

        DB::transaction(function () use ($successor) {
            $successor->assignRole('company_admin');
        });

        DB::afterCommit(function () use ($successor, $company, $departingUser) {
            $successor->notify(new CompanyAdminAssignedNotification($company, isSuperAdminForced: false));

            if ($departingUser) {
                $departingUser->notify(new CompanyAdminRevokedNotification($company));
            }
        });

        activity()
            ->performedOn($company)
            ->withProperties(['new_admin_id' => $successor->id])
            ->log('Succession company_admin — transfert automatique');
    }
}
