<?php

use App\Actions\Company\TransferCompanyAdminAction;
use App\Models\Company;
use App\Models\User;
use App\Notifications\Company\CompanyAdminAssignedNotification;
use App\Notifications\Company\CompanyAdminVacantNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'company_admin', 'guard_name' => 'web']);
    Notification::fake();
});

test('transfère le rôle au membre le plus ancien restant', function () {
    $company = Company::factory()->create();

    $departing = User::factory()->create(['company_id' => $company->id, 'created_at' => now()->subDays(10)]);
    $departing->assignRole('company_admin');

    $oldest = User::factory()->create(['company_id' => $company->id, 'created_at' => now()->subDays(5)]);
    $newer  = User::factory()->create(['company_id' => $company->id, 'created_at' => now()->subDays(1)]);

    app(TransferCompanyAdminAction::class)->execute($company, $departing);

    expect($oldest->fresh()->hasRole('company_admin'))->toBeTrue();
    expect($newer->fresh()->hasRole('company_admin'))->toBeFalse();
    Notification::assertSentTo($oldest, CompanyAdminAssignedNotification::class);
});

test('notifie le super admin si aucun membre éligible', function () {
    $company = Company::factory()->create();
    $superAdmin = User::factory()->create(['company_id' => null]);
    $superAdmin->assignRole('admin');

    $departing = User::factory()->create(['company_id' => $company->id]);
    $departing->assignRole('company_admin');

    app(TransferCompanyAdminAction::class)->execute($company, $departing);

    Notification::assertSentTo($superAdmin, CompanyAdminVacantNotification::class);
});

test('prend le membre le plus ancien en cas de multi-membres', function () {
    $company = Company::factory()->create();

    $departing = User::factory()->create(['company_id' => $company->id, 'created_at' => now()->subDays(30)]);
    $departing->assignRole('company_admin');

    // 3 membres restants à anciennetés différentes
    $m1 = User::factory()->create(['company_id' => $company->id, 'created_at' => now()->subDays(20)]);
    $m2 = User::factory()->create(['company_id' => $company->id, 'created_at' => now()->subDays(10)]);
    $m3 = User::factory()->create(['company_id' => $company->id, 'created_at' => now()->subDays(1)]);

    app(TransferCompanyAdminAction::class)->execute($company, $departing);

    expect($m1->fresh()->hasRole('company_admin'))->toBeTrue();
    expect($m2->fresh()->hasRole('company_admin'))->toBeFalse();
    expect($m3->fresh()->hasRole('company_admin'))->toBeFalse();
});

test('ne transfère pas si d\'autres company_admins existent déjà', function () {
    $company = Company::factory()->create();

    $departing = User::factory()->create(['company_id' => $company->id]);
    $departing->assignRole('company_admin');

    $existing = User::factory()->create(['company_id' => $company->id]);
    $existing->assignRole('company_admin');

    // L'observer ne devrait pas appeler Transfer ici (géré dans UserObserver::deleted)
    // Ce test vérifie que l'Action elle-même retire le rôle au partant et prend le plus ancien
    app(TransferCompanyAdminAction::class)->execute($company, $departing);

    // Le plus ancien restant (existing) garde son rôle
    expect($existing->fresh()->hasRole('company_admin'))->toBeTrue();
    expect($departing->fresh()->hasRole('company_admin'))->toBeFalse();
});

test('tie-breaker id : à created_at identique, le membre avec le plus petit id hérite', function () {
    $company = Company::factory()->create();

    $departing = User::factory()->create(['company_id' => $company->id, 'created_at' => now()->subDays(10)]);
    $departing->assignRole('company_admin');

    $sameTimestamp = now()->subDays(3);

    // Créés séquentiellement avec le même timestamp — les ID sont donc croissants
    $first  = User::factory()->create(['company_id' => $company->id, 'created_at' => $sameTimestamp]);
    $second = User::factory()->create(['company_id' => $company->id, 'created_at' => $sameTimestamp]);

    // $first a un ID inférieur à $second — le tie-breaker orderBy('id') doit le désigner
    expect($first->id)->toBeLessThan($second->id);

    app(TransferCompanyAdminAction::class)->execute($company, $departing);

    expect($first->fresh()->hasRole('company_admin'))->toBeTrue();
    expect($second->fresh()->hasRole('company_admin'))->toBeFalse();
});

test('retire le rôle au partant même s\'il l\'a encore', function () {
    $company = Company::factory()->create();

    $departing = User::factory()->create(['company_id' => $company->id]);
    $departing->assignRole('company_admin');

    $successor = User::factory()->create(['company_id' => $company->id]);

    app(TransferCompanyAdminAction::class)->execute($company, $departing);

    expect($departing->fresh()->hasRole('company_admin'))->toBeFalse();
    expect($successor->fresh()->hasRole('company_admin'))->toBeTrue();
});
