<?php

use App\Actions\Company\CancelCompanyJoinRequestAction;
use App\Enums\CompanyJoinRequestStatus;
use App\Models\Company;
use App\Models\CompanyJoinRequest;
use App\Models\User;

test('le demandeur peut annuler sa demande pending', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => null]);

    $joinRequest = CompanyJoinRequest::factory()->create([
        'company_id' => $company->id,
        'user_id'    => $user->id,
    ]);

    app(CancelCompanyJoinRequestAction::class)->execute($user, $joinRequest);

    expect($joinRequest->fresh()->status)->toBe(CompanyJoinRequestStatus::Cancelled);
    expect($joinRequest->fresh()->resolved_at)->not->toBeNull();
});

test('abort 403 si l\'acteur n\'est pas le demandeur', function () {
    $company = Company::factory()->create();
    $requester = User::factory()->create(['company_id' => null]);
    $otherUser = User::factory()->create(['company_id' => null]);

    $joinRequest = CompanyJoinRequest::factory()->create([
        'company_id' => $company->id,
        'user_id'    => $requester->id,
    ]);

    expect(fn () => app(CancelCompanyJoinRequestAction::class)->execute($otherUser, $joinRequest))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

test('abort 409 si la demande n\'est plus pending', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => null]);

    $joinRequest = CompanyJoinRequest::factory()->approved()->create([
        'company_id' => $company->id,
        'user_id'    => $user->id,
    ]);

    expect(fn () => app(CancelCompanyJoinRequestAction::class)->execute($user, $joinRequest))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});
