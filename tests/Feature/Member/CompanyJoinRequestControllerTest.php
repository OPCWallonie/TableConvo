<?php

use App\Enums\CompanyJoinRequestStatus;
use App\Models\Company;
use App\Models\CompanyJoinRequest;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin',         'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'company_admin', 'guard_name' => 'web']);
    Http::preventStrayRequests();
});

// ── create() ─────────────────────────────────────────────────────────────────

it('create redirects admin to filament dashboard', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('espace.societe.rejoindre'))
        ->assertRedirect(route('filament.admin.pages.dashboard'));
});

it('create redirects user already attached to profil', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();

    $this->actingAs($user)
        ->get(route('espace.societe.rejoindre'))
        ->assertRedirect(route('espace.profil'));
});

it('create shows form with pending request info', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => null]);
    $joinRequest = CompanyJoinRequest::factory()->create([
        'user_id'    => $user->id,
        'company_id' => $company->id,
        'status'     => CompanyJoinRequestStatus::Pending->value,
    ]);

    $this->actingAs($user)
        ->get(route('espace.societe.rejoindre'))
        ->assertOk()
        ->assertViewHas('pendingRequest', fn ($req) => $req->id === $joinRequest->id);
});

it('create shows form with null pending request when none exists', function () {
    $user = User::factory()->create(['company_id' => null]);

    $this->actingAs($user)
        ->get(route('espace.societe.rejoindre'))
        ->assertOk()
        ->assertViewHas('pendingRequest', null);
});

// ── lookup() ─────────────────────────────────────────────────────────────────

it('lookup returns invalid_format for malformed VAT', function () {
    $user = User::factory()->create(['company_id' => null]);

    $this->actingAs($user)
        ->postJson(route('espace.societe.rejoindre.lookup'), ['vat_number' => 'INVALID'])
        ->assertJson(['status' => 'invalid_format']);
});

it('lookup returns unknown when VAT not in DB but VIES knows the company', function () {
    Http::fake([
        'ec.europa.eu/*' => Http::response([
            'isValid' => true,
            'name'    => 'ACME SA',
            'address' => 'Rue Test 1, 1000 Bruxelles',
        ], 200),
    ]);

    $user = User::factory()->create(['company_id' => null]);

    $this->actingAs($user)
        ->postJson(route('espace.societe.rejoindre.lookup'), ['vat_number' => 'BE0123456789'])
        ->assertJson(['status' => 'unknown']);
});

it('lookup returns exists with can_auto_join true when email domain matches', function () {
    Http::fake([
        'ec.europa.eu/*' => Http::response(['isValid' => true, 'name' => 'ACME SA', 'address' => ''], 200),
    ]);

    Company::factory()->create([
        'vat_number'   => 'BE0123456789',
        'email_domain' => 'acme.be',
    ]);
    $user = User::factory()->create([
        'email'      => 'alice@acme.be',
        'company_id' => null,
    ]);

    $this->actingAs($user)
        ->postJson(route('espace.societe.rejoindre.lookup'), ['vat_number' => 'BE0123456789'])
        ->assertJson(['status' => 'exists', 'can_auto_join' => true]);
});

it('lookup returns exists with can_auto_join false when email domain does not match', function () {
    Http::fake([
        'ec.europa.eu/*' => Http::response(['isValid' => true, 'name' => 'ACME SA', 'address' => ''], 200),
    ]);

    Company::factory()->create([
        'vat_number'   => 'BE0123456789',
        'email_domain' => 'acme.be',
    ]);
    $user = User::factory()->create([
        'email'      => 'bob@other.be',
        'company_id' => null,
    ]);

    $this->actingAs($user)
        ->postJson(route('espace.societe.rejoindre.lookup'), ['vat_number' => 'BE0123456789'])
        ->assertJson(['status' => 'exists', 'can_auto_join' => false]);
});

// ── store() ──────────────────────────────────────────────────────────────────

it('store auto-joins when email domain matches and redirects to dashboard', function () {
    $company = Company::factory()->create([
        'vat_number'   => 'BE0123456789',
        'email_domain' => 'acme.be',
    ]);
    $user = User::factory()->create([
        'email'      => 'alice@acme.be',
        'company_id' => null,
    ]);

    $this->actingAs($user)
        ->post(route('espace.societe.rejoindre.store'), ['vat_number' => 'BE0123456789'])
        ->assertRedirect(route('espace.dashboard'))
        ->assertSessionHas('status', 'auto_joined');

    expect($user->fresh()->company_id)->toBe($company->id);
});

it('store creates join request when no email domain match and redirects to profil', function () {
    $company = Company::factory()->create([
        'vat_number'   => 'BE0123456789',
        'email_domain' => null,
    ]);
    $user = User::factory()->create([
        'email'      => 'bob@other.be',
        'company_id' => null,
    ]);

    $this->actingAs($user)
        ->post(route('espace.societe.rejoindre.store'), [
            'vat_number' => 'BE0123456789',
            'message'    => 'Je travaille au département RH.',
        ])
        ->assertRedirect(route('espace.profil'))
        ->assertSessionHas('status', 'request_pending');

    expect(CompanyJoinRequest::where('user_id', $user->id)->where('company_id', $company->id)->exists())->toBeTrue();
});

it('store redirects back with already_requested status on duplicate pending request', function () {
    $company = Company::factory()->create([
        'vat_number'   => 'BE0123456789',
        'email_domain' => null,
    ]);
    $user = User::factory()->create(['company_id' => null]);

    CompanyJoinRequest::factory()->create([
        'user_id'    => $user->id,
        'company_id' => $company->id,
        'status'     => CompanyJoinRequestStatus::Pending->value,
    ]);

    $this->actingAs($user)
        ->post(route('espace.societe.rejoindre.store'), ['vat_number' => 'BE0123456789'])
        ->assertRedirect()
        ->assertSessionHas('status', 'already_requested');
});

// ── cancel() ─────────────────────────────────────────────────────────────────

it('cancel cancels the pending request and redirects back with request_cancelled status', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => null]);
    CompanyJoinRequest::factory()->create([
        'user_id'    => $user->id,
        'company_id' => $company->id,
        'status'     => CompanyJoinRequestStatus::Pending->value,
    ]);

    $this->actingAs($user)
        ->post(route('espace.societe.ma-demande.annuler'))
        ->assertRedirect()
        ->assertSessionHas('status', 'request_cancelled');

    expect(
        CompanyJoinRequest::where('user_id', $user->id)
            ->where('status', CompanyJoinRequestStatus::Pending->value)
            ->exists()
    )->toBeFalse();
});
