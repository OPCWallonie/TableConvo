<?php

use App\Actions\Company\RejectCompanyJoinRequestAction;
use App\Enums\CompanyJoinRequestStatus;
use App\Models\Company;
use App\Models\CompanyJoinRequest;
use App\Models\User;
use App\Notifications\Company\CompanyJoinRejectedNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'company_admin', 'guard_name' => 'web']);
    Notification::fake();
});

test('rejette la demande avec raison et notifie le demandeur', function () {
    $company = Company::factory()->create();
    $companyAdmin = User::factory()->create(['company_id' => $company->id]);
    $companyAdmin->assignRole('company_admin');
    $requester = User::factory()->create(['company_id' => null]);

    $joinRequest = CompanyJoinRequest::factory()->create([
        'company_id' => $company->id,
        'user_id'    => $requester->id,
    ]);

    app(RejectCompanyJoinRequestAction::class)->execute($companyAdmin, $joinRequest, 'Pas un employé.');

    expect($joinRequest->fresh()->status)->toBe(CompanyJoinRequestStatus::Rejected);
    expect($joinRequest->fresh()->rejection_reason)->toBe('Pas un employé.');
    Notification::assertSentTo($requester, CompanyJoinRejectedNotification::class);
});

test('abort 403 si l\'acteur n\'est pas company_admin de la company', function () {
    $company = Company::factory()->create();
    $impostor = User::factory()->create(['company_id' => null]);
    $joinRequest = CompanyJoinRequest::factory()->create(['company_id' => $company->id]);

    expect(fn () => app(RejectCompanyJoinRequestAction::class)->execute($impostor, $joinRequest))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

test('abort 409 si la demande n\'est pas pending', function () {
    $company = Company::factory()->create();
    $companyAdmin = User::factory()->create(['company_id' => $company->id]);
    $companyAdmin->assignRole('company_admin');

    $joinRequest = CompanyJoinRequest::factory()->rejected()->create([
        'company_id' => $company->id,
        'user_id'    => User::factory()->create(['company_id' => null])->id,
    ]);

    expect(fn () => app(RejectCompanyJoinRequestAction::class)->execute($companyAdmin, $joinRequest))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});
