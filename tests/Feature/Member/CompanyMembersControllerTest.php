<?php

use App\Enums\CompanyJoinRequestStatus;
use App\Models\Company;
use App\Models\CompanyJoinRequest;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin',         'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'company_admin', 'guard_name' => 'web']);
});

it('returns 403 to a plain member without company_admin role', function () {
    $company = Company::factory()->create();
    $member = User::factory()->for($company)->create();

    $this->actingAs($member)
        ->get(route('espace.societe.membres'))
        ->assertForbidden();
});

it('shows pending requests and members list to company_admin', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->for($company)->create();
    $admin->assignRole('company_admin');

    $requester = User::factory()->create(['company_id' => null]);
    CompanyJoinRequest::factory()->create([
        'user_id'    => $requester->id,
        'company_id' => $company->id,
        'status'     => CompanyJoinRequestStatus::Pending->value,
    ]);

    $this->actingAs($admin)
        ->get(route('espace.societe.membres'))
        ->assertOk()
        ->assertViewIs('espace.societe.membres')
        ->assertViewHas('pendingRequests', fn ($requests) => $requests->count() === 1)
        ->assertViewHas('company', fn ($c) => $c->id === $company->id);
});

it('approve approves a join request and redirects with request_approved status', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->for($company)->create();
    $admin->assignRole('company_admin');

    $requester = User::factory()->create(['company_id' => null]);
    $joinRequest = CompanyJoinRequest::factory()->create([
        'user_id'    => $requester->id,
        'company_id' => $company->id,
        'status'     => CompanyJoinRequestStatus::Pending->value,
    ]);

    $this->actingAs($admin)
        ->post(route('espace.societe.demandes.approuver', $joinRequest))
        ->assertRedirect()
        ->assertSessionHas('status', 'request_approved');

    expect($joinRequest->fresh()->status->value)->toBe('approved');
    expect($requester->fresh()->company_id)->toBe($company->id);
});

it('reject rejects a join request and redirects with request_rejected status', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->for($company)->create();
    $admin->assignRole('company_admin');

    $requester = User::factory()->create(['company_id' => null]);
    $joinRequest = CompanyJoinRequest::factory()->create([
        'user_id'    => $requester->id,
        'company_id' => $company->id,
        'status'     => CompanyJoinRequestStatus::Pending->value,
    ]);

    $this->actingAs($admin)
        ->post(route('espace.societe.demandes.rejeter', $joinRequest), [
            'rejection_reason' => 'Informations insuffisantes.',
        ])
        ->assertRedirect()
        ->assertSessionHas('status', 'request_rejected');

    expect($joinRequest->fresh()->status->value)->toBe('rejected');
});

it('admin of another company cannot approve a join request', function () {
    $company1 = Company::factory()->create();
    $company2 = Company::factory()->create();

    $admin = User::factory()->for($company1)->create();
    $admin->assignRole('company_admin');

    $requester = User::factory()->create(['company_id' => null]);
    $joinRequest = CompanyJoinRequest::factory()->create([
        'user_id'    => $requester->id,
        'company_id' => $company2->id,
        'status'     => CompanyJoinRequestStatus::Pending->value,
    ]);

    $this->actingAs($admin)
        ->post(route('espace.societe.demandes.approuver', $joinRequest))
        ->assertForbidden();
});

it('index always returns the connected user company, not another company', function () {
    $companyA = Company::factory()->create();
    Company::factory()->create(); // companyB existe mais n'est jamais visible

    $adminA = User::factory()->for($companyA)->create();
    $adminA->assignRole('company_admin');

    $this->actingAs($adminA)
        ->get(route('espace.societe.membres'))
        ->assertOk()
        ->assertViewHas('company', fn ($c) => $c->id === $companyA->id);
});
