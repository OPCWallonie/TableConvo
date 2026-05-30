<?php

use App\Enums\CompanyJoinRequestStatus;
use App\Models\Company;
use App\Models\CompanyJoinRequest;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'company_admin', 'guard_name' => 'web']);
    Http::preventStrayRequests();
});

// ─── Cas 1 : nouvelle TVA ────────────────────────────────────────────────────

it('cas1: new VAT creates company and user with company_admin role', function () {
    mockVat();

    $this->post('/register', registrationPayload())->assertRedirect(route('espace.dashboard'));

    $company = Company::where('vat_number', 'BE0123456789')->first();
    expect($company)->not->toBeNull();

    $user = User::where('email', 'test@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->company_id)->toBe($company->id);
    expect($user->hasRole('company_admin'))->toBeTrue();
});

it('cas1: registers email_domain on company when email is professional', function () {
    mockVat();

    // example.com n'est pas dans la blocklist
    $this->post('/register', registrationPayload(['email' => 'alice@acme-sa.be']));

    $company = Company::where('vat_number', 'BE0123456789')->first();
    expect($company->email_domain)->toBe('acme-sa.be');
});

it('cas1: email_domain is null when email is a generic provider', function () {
    mockVat();

    $this->post('/register', registrationPayload(['email' => 'bob@gmail.com']));

    $company = Company::where('vat_number', 'BE0123456789')->first();
    expect($company->email_domain)->toBeNull();
});

// ─── Cas 2 : TVA existante + domaine email match → auto-join ─────────────────

it('cas2: existing VAT with matching email domain auto-joins and redirects to dashboard', function () {
    $company = Company::factory()->create([
        'vat_number'   => 'BE0123456789',
        'email_domain' => 'acme-sa.be',
    ]);

    mockVat();

    $response = $this->post('/register', registrationPayload(['email' => 'alice@acme-sa.be']));

    $response->assertRedirect(route('espace.dashboard'));
    $response->assertSessionHas('status', 'auto_joined');

    $user = User::where('email', 'alice@acme-sa.be')->first();
    expect($user->company_id)->toBe($company->id);
    // Cas 2 : membre standard, pas company_admin
    expect($user->hasRole('company_admin'))->toBeFalse();
});

it('cas2: no duplicate company is created on auto-join', function () {
    Company::factory()->create(['vat_number' => 'BE0123456789', 'email_domain' => 'acme-sa.be']);

    mockVat();

    $this->post('/register', registrationPayload(['email' => 'alice@acme-sa.be']));

    expect(Company::where('vat_number', 'BE0123456789')->count())->toBe(1);
});

// ─── Cas 3 : TVA existante + pas de match → join request ─────────────────────

it('cas3: existing VAT without domain match creates join request and redirects to profil', function () {
    $company = Company::factory()->create([
        'vat_number'   => 'BE0123456789',
        'email_domain' => null,
    ]);

    mockVat();

    $response = $this->post('/register', registrationPayload(['email' => 'newcomer@example.com']));

    $response->assertRedirect(route('espace.profil'));
    $response->assertSessionHas('status', 'request_pending');

    $user = User::where('email', 'newcomer@example.com')->first();
    expect($user->company_id)->toBeNull();
    expect(
        CompanyJoinRequest::where('user_id', $user->id)
            ->where('company_id', $company->id)
            ->where('status', CompanyJoinRequestStatus::Pending->value)
            ->exists()
    )->toBeTrue();
});

it('cas3: user without company can access profil after registration (EnsureUserHasCompany does not block)', function () {
    Company::factory()->create(['vat_number' => 'BE0123456789', 'email_domain' => null]);

    mockVat();

    $this->post('/register', registrationPayload(['email' => 'newcomer@example.com']));

    // L'user est authentifié et peut accéder au profil
    $this->assertAuthenticated();
    $this->get(route('espace.profil'))->assertOk();
});

// ─── Cas 4 : TVA invalide ────────────────────────────────────────────────────

it('cas4: invalid VAT format returns validation error', function () {
    $this->mock(\App\Services\Vat\VatValidationService::class, function ($mock) {
        $mock->shouldReceive('normalize')->andReturn('INVALID');
        $mock->shouldReceive('isFormatValid')->andReturn(false);
    });

    $response = $this->post('/register', registrationPayload(['vat_number' => 'INVALID']));

    $this->assertGuest();
    $response->assertSessionHasErrors('vat_number');
});

it('cas4: VIES validation failure returns validation error', function () {
    $this->mock(\App\Services\Vat\VatValidationService::class, function ($mock) {
        $mock->shouldReceive('normalize')->andReturn('BE0123456789');
        $mock->shouldReceive('isFormatValid')->andReturn(true);
        $mock->shouldReceive('validate')->andReturn(false);
    });

    $response = $this->post('/register', registrationPayload());

    $this->assertGuest();
    $response->assertSessionHasErrors('vat_number');
});

// ─── Edge cases ──────────────────────────────────────────────────────────────

it('cas1: missing company fields triggers validation error for new company', function () {
    mockVat();

    $response = $this->post('/register', [
        'first_name'            => 'Alice',
        'last_name'             => 'Test',
        'email'                 => 'alice@example.com',
        'password'              => 'password',
        'password_confirmation' => 'password',
        'vat_number'            => 'BE0123456789',
        // company_name, street, postal_code, city omis
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors(['company_name', 'street', 'postal_code', 'city']);
});
