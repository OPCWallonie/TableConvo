<?php

use App\Actions\Company\ApproveCompanyJoinRequestAction;
use App\Enums\CompanyJoinRequestStatus;
use App\Models\Company;
use App\Models\CompanyJoinRequest;
use App\Models\User;
use App\Notifications\Company\CompanyJoinApprovedNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'company_admin', 'guard_name' => 'web']);
    Notification::fake();
});

test('approuve la demande et rattache le user à la company', function () {
    $company = Company::factory()->create();
    $companyAdmin = User::factory()->create(['company_id' => $company->id]);
    $companyAdmin->assignRole('company_admin');
    $requester = User::factory()->create(['company_id' => null]);

    $joinRequest = CompanyJoinRequest::factory()->create([
        'company_id' => $company->id,
        'user_id'    => $requester->id,
        'status'     => CompanyJoinRequestStatus::Pending,
    ]);

    app(ApproveCompanyJoinRequestAction::class)->execute($companyAdmin, $joinRequest);

    expect($joinRequest->fresh()->status)->toBe(CompanyJoinRequestStatus::Approved);
    expect($joinRequest->fresh()->resolved_by_user_id)->toBe($companyAdmin->id);
    expect($requester->fresh()->company_id)->toBe($company->id);
});

test('notifie le demandeur après approbation', function () {
    $company = Company::factory()->create();
    $companyAdmin = User::factory()->create(['company_id' => $company->id]);
    $companyAdmin->assignRole('company_admin');
    $requester = User::factory()->create(['company_id' => null]);

    $joinRequest = CompanyJoinRequest::factory()->create([
        'company_id' => $company->id,
        'user_id'    => $requester->id,
    ]);

    app(ApproveCompanyJoinRequestAction::class)->execute($companyAdmin, $joinRequest);

    Notification::assertSentTo($requester, CompanyJoinApprovedNotification::class);
});

test('abort 403 si l\'acteur n\'est pas company_admin de la company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $impostor = User::factory()->create(['company_id' => $otherCompany->id]);
    $impostor->assignRole('company_admin');

    $requester = User::factory()->create(['company_id' => null]);
    $joinRequest = CompanyJoinRequest::factory()->create([
        'company_id' => $company->id,
        'user_id'    => $requester->id,
    ]);

    expect(fn () => app(ApproveCompanyJoinRequestAction::class)->execute($impostor, $joinRequest))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

test('abort 409 si la demande n\'est pas pending', function () {
    $company = Company::factory()->create();
    $companyAdmin = User::factory()->create(['company_id' => $company->id]);
    $companyAdmin->assignRole('company_admin');
    $requester = User::factory()->create(['company_id' => null]);

    $joinRequest = CompanyJoinRequest::factory()->approved()->create([
        'company_id' => $company->id,
        'user_id'    => $requester->id,
    ]);

    expect(fn () => app(ApproveCompanyJoinRequestAction::class)->execute($companyAdmin, $joinRequest))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});
