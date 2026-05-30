<?php

use App\Models\Company;
use App\Models\User;
use App\Notifications\Company\CompanyAdminAssignedNotification;
use App\Notifications\Company\CompanyAdminVacantNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'company_admin', 'guard_name' => 'web']);
    Notification::fake();
});

test('la soft-deletion d\'un company_admin déclenche la succession au plus ancien restant', function () {
    $company = Company::factory()->create();

    $departing = User::factory()->create(['company_id' => $company->id, 'created_at' => now()->subDays(10)]);
    $departing->assignRole('company_admin');

    $successor = User::factory()->create(['company_id' => $company->id, 'created_at' => now()->subDays(1)]);

    $departing->delete(); // soft delete → déclenche UserObserver::deleted

    expect($successor->fresh()->hasRole('company_admin'))->toBeTrue();
    Notification::assertSentTo($successor, CompanyAdminAssignedNotification::class);
});

test('pas de succession si un autre company_admin existe déjà', function () {
    $company = Company::factory()->create();

    $departing = User::factory()->create(['company_id' => $company->id]);
    $departing->assignRole('company_admin');

    $existingAdmin = User::factory()->create(['company_id' => $company->id]);
    $existingAdmin->assignRole('company_admin');

    $departing->delete();

    // L'existingAdmin garde son rôle, aucune succession nécessaire
    expect($existingAdmin->fresh()->hasRole('company_admin'))->toBeTrue();
});

test('si aucun successeur, le super admin est notifié', function () {
    $superAdmin = User::factory()->create(['company_id' => null]);
    $superAdmin->assignRole('admin');

    $company = Company::factory()->create();
    $onlyAdmin = User::factory()->create(['company_id' => $company->id]);
    $onlyAdmin->assignRole('company_admin');

    $onlyAdmin->delete();

    Notification::assertSentTo($superAdmin, CompanyAdminVacantNotification::class);
});
