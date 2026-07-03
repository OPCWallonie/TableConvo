<?php

use App\Actions\Company\AutoJoinCompanyByEmailDomainAction;
use App\Models\Company;
use App\Models\User;
use App\Notifications\Company\CompanyAutoJoinedNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'company_admin', 'guard_name' => 'web']);
    Notification::fake();
});

test('rattache le user à la company sans lui assigner company_admin', function () {
    $company = Company::factory()->create(['email_domain' => 'acme-sa.be']);
    $user = User::factory()->create(['company_id' => null, 'email' => 'marie@acme-sa.be']);

    app(AutoJoinCompanyByEmailDomainAction::class)->execute($user, $company);

    expect($user->fresh()->company_id)->toBe($company->id);
    expect($user->fresh()->hasRole('company_admin'))->toBeFalse();
});

test('notifie le company_admin après rattachement', function () {
    $company = Company::factory()->create(['email_domain' => 'acme-sa.be']);
    $companyAdmin = User::factory()->create(['company_id' => $company->id]);
    $companyAdmin->assignRole('company_admin');

    $user = User::factory()->create(['company_id' => null, 'email' => 'marie@acme-sa.be']);

    app(AutoJoinCompanyByEmailDomainAction::class)->execute($user, $company);

    Notification::assertSentTo($companyAdmin, CompanyAutoJoinedNotification::class);
});

test('abort 403 si admin TableConvo tente l\'auto-join', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->create(['company_id' => null]);
    $admin->assignRole('admin');

    expect(fn () => app(AutoJoinCompanyByEmailDomainAction::class)->execute($admin, $company))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

test('abort 409 si le user est déjà rattaché', function () {
    $company1 = Company::factory()->create();
    $company2 = Company::factory()->create(['email_domain' => 'acme-sa.be']);
    $user = User::factory()->create(['company_id' => $company1->id, 'email' => 'marie@acme-sa.be']);

    expect(fn () => app(AutoJoinCompanyByEmailDomainAction::class)->execute($user, $company2))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});
