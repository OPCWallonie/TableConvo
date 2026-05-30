<?php

use App\Actions\Company\RequestCompanyJoinAction;
use App\Enums\CompanyJoinRequestStatus;
use App\Models\Company;
use App\Models\CompanyJoinRequest;
use App\Models\User;
use App\Notifications\Company\CompanyJoinRequestedNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'company_admin', 'guard_name' => 'web']);
    Notification::fake();
});

test('crée une demande pending et notifie le company_admin', function () {
    $company = Company::factory()->create();
    $companyAdmin = User::factory()->create(['company_id' => $company->id]);
    $companyAdmin->assignRole('company_admin');

    $user = User::factory()->create(['company_id' => null]);

    $joinRequest = app(RequestCompanyJoinAction::class)->execute($user, $company, 'Bonjour !');

    expect($joinRequest->status)->toBe(CompanyJoinRequestStatus::Pending);
    expect($joinRequest->message)->toBe('Bonjour !');
    expect(CompanyJoinRequest::where('user_id', $user->id)->where('company_id', $company->id)->exists())->toBeTrue();

    Notification::assertSentTo($companyAdmin, CompanyJoinRequestedNotification::class);
});

test('abort 409 si une demande pending existe déjà pour ce couple', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => null]);

    CompanyJoinRequest::factory()->create([
        'user_id'    => $user->id,
        'company_id' => $company->id,
        'status'     => CompanyJoinRequestStatus::Pending,
    ]);

    expect(fn () => app(RequestCompanyJoinAction::class)->execute($user, $company))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

test('abort 403 si le user est admin TableConvo', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->create(['company_id' => null]);
    $admin->assignRole('admin');

    expect(fn () => app(RequestCompanyJoinAction::class)->execute($admin, $company))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

test('abort 409 si le user est déjà rattaché à une company', function () {
    $existingCompany = Company::factory()->create();
    $targetCompany   = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $existingCompany->id]);

    expect(fn () => app(RequestCompanyJoinAction::class)->execute($user, $targetCompany))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

test('crée la demande sans company_admin disponible (pas de notification envoyée)', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => null]);

    $joinRequest = app(RequestCompanyJoinAction::class)->execute($user, $company);

    expect($joinRequest->status)->toBe(CompanyJoinRequestStatus::Pending);
    Notification::assertNothingSent();
});
