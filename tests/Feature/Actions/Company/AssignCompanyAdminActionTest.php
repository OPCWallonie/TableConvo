<?php

use App\Actions\Company\AssignCompanyAdminAction;
use App\Models\Company;
use App\Models\User;
use App\Notifications\Company\CompanyAdminAssignedNotification;
use App\Notifications\Company\CompanyAdminRevokedNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\ActivityLog\Models\Activity;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'company_admin', 'guard_name' => 'web']);
    Notification::fake();
});

test('le super admin peut réassigner le company_admin', function () {
    $superAdmin = User::factory()->create(['company_id' => null]);
    $superAdmin->assignRole('admin');

    $company = Company::factory()->create();
    $oldAdmin = User::factory()->create(['company_id' => $company->id]);
    $oldAdmin->assignRole('company_admin');
    $newAdmin = User::factory()->create(['company_id' => $company->id]);

    app(AssignCompanyAdminAction::class)->execute($superAdmin, $company, $newAdmin);

    expect($newAdmin->fresh()->hasRole('company_admin'))->toBeTrue();
    expect($oldAdmin->fresh()->hasRole('company_admin'))->toBeFalse();
});

test('abort 403 si l\'acteur n\'est pas super admin', function () {
    $company = Company::factory()->create();
    $impostor = User::factory()->create(['company_id' => $company->id]);
    $impostor->assignRole('company_admin');
    $newAdmin = User::factory()->create(['company_id' => $company->id]);

    expect(fn () => app(AssignCompanyAdminAction::class)->execute($impostor, $company, $newAdmin))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

test('abort 422 si le nouveau admin n\'appartient pas à la company', function () {
    $superAdmin = User::factory()->create(['company_id' => null]);
    $superAdmin->assignRole('admin');

    $company = Company::factory()->create();
    $outsider = User::factory()->create(['company_id' => null]);

    expect(fn () => app(AssignCompanyAdminAction::class)->execute($superAdmin, $company, $outsider))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

test('retire le rôle company_admin à tous les anciens admins de la company', function () {
    $superAdmin = User::factory()->create(['company_id' => null]);
    $superAdmin->assignRole('admin');

    $company = Company::factory()->create();
    $admin1 = User::factory()->create(['company_id' => $company->id]);
    $admin1->assignRole('company_admin');
    $admin2 = User::factory()->create(['company_id' => $company->id]);
    $admin2->assignRole('company_admin');
    $newAdmin = User::factory()->create(['company_id' => $company->id]);

    app(AssignCompanyAdminAction::class)->execute($superAdmin, $company, $newAdmin);

    expect($admin1->fresh()->hasRole('company_admin'))->toBeFalse();
    expect($admin2->fresh()->hasRole('company_admin'))->toBeFalse();
    expect($newAdmin->fresh()->hasRole('company_admin'))->toBeTrue();
});

test('envoie les notifications au nouveau et aux anciens admins', function () {
    $superAdmin = User::factory()->create(['company_id' => null]);
    $superAdmin->assignRole('admin');

    $company = Company::factory()->create();
    $oldAdmin = User::factory()->create(['company_id' => $company->id]);
    $oldAdmin->assignRole('company_admin');
    $newAdmin = User::factory()->create(['company_id' => $company->id]);

    app(AssignCompanyAdminAction::class)->execute($superAdmin, $company, $newAdmin);

    Notification::assertSentTo($newAdmin, CompanyAdminAssignedNotification::class);
    Notification::assertSentTo($oldAdmin, CompanyAdminRevokedNotification::class);
});
