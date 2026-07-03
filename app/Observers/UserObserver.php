<?php

namespace App\Observers;

use App\Actions\Company\TransferCompanyAdminAction;
use App\Models\User;

class UserObserver
{
    public function __construct(
        private readonly TransferCompanyAdminAction $transferAction,
    ) {}

    public function deleted(User $user): void
    {
        $this->handleCompanyAdminDeparture($user);
    }

    public function updated(User $user): void
    {
        // Si le user change de company (réassignation admin), vérifier la succession sur l'ancienne
        if ($user->wasChanged('company_id') && $user->getOriginal('company_id') !== null) {
            $oldCompanyId = $user->getOriginal('company_id');

            // Charger l'ancienne company pour vérifier si le user en était admin
            $oldCompany = \App\Models\Company::find($oldCompanyId);
            if ($oldCompany && $user->hasRole('company_admin')) {
                $remainingAdmins = $oldCompany->members()
                    ->where('users.id', '!=', $user->id)
                    ->role('company_admin')
                    ->count();

                if ($remainingAdmins === 0) {
                    $this->transferAction->execute($oldCompany, $user);
                }
            }
        }
    }

    private function handleCompanyAdminDeparture(User $user): void
    {
        if (! $user->hasRole('company_admin') || $user->company_id === null) {
            return;
        }

        $company = $user->company;
        if ($company === null) {
            return;
        }

        // Vérifier si ce user était le seul company_admin
        $remainingAdmins = $company->members()
            ->where('users.id', '!=', $user->id)
            ->whereNull('users.deleted_at')
            ->role('company_admin')
            ->count();

        if ($remainingAdmins === 0) {
            $this->transferAction->execute($company, $user);
        }
    }
}
