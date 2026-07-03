<?php

use App\Enums\CompanyJoinRequestStatus;
use App\Models\Company;
use App\Models\CompanyJoinRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'company_admin', 'guard_name' => 'web']);
});

// ─────────────────────────────────────────────────────────────────────────────
// Test 1 — Chemin nominal : nouvelle TVA → company + user créés (INCHANGÉ)
// ─────────────────────────────────────────────────────────────────────────────

it('registering with a new unique VAT number creates the company and user', function () {
    mockVat();

    $this->post('/register', registrationPayload())->assertRedirect(route('espace.dashboard'));

    expect(Company::where('vat_number', 'BE0123456789')->exists())->toBeTrue();
    $this->assertAuthenticated();
});

// ─────────────────────────────────────────────────────────────────────────────
// Test 2 — TVA déjà prise → orientation (join request), pas de rejet
// Changement délibéré Phase 9.6 D.1 : l'anti-hijacking ne rejette plus,
// il oriente vers auto-join (cas 2) ou join request (cas 3).
// ─────────────────────────────────────────────────────────────────────────────

it('registering with an existing VAT creates the user and a join request instead of rejecting', function () {
    Company::factory()->create(['vat_number' => 'BE0123456789', 'email_domain' => null]);

    mockVat();

    $response = $this->post('/register', registrationPayload(['email' => 'newcomer@example.com']));

    // L'user EST créé et loggé (pas rejeté)
    $this->assertAuthenticated();
    expect(User::where('email', 'newcomer@example.com')->exists())->toBeTrue();

    // Il est redirigé vers le profil avec le statut request_pending
    $response->assertRedirect(route('espace.profil'));
    $response->assertSessionHas('status', 'request_pending');
});

// ─────────────────────────────────────────────────────────────────────────────
// Test 3 — Aucune société dupliquée, mais l'user EST créé avec un join request
// Changement délibéré Phase 9.6 D.1
// ─────────────────────────────────────────────────────────────────────────────

it('does not create a duplicate company and routes to join request when VAT is already taken', function () {
    $company = Company::factory()->create(['vat_number' => 'BE0123456789', 'email_domain' => null]);

    mockVat();

    $this->post('/register', registrationPayload(['email' => 'newcomer@example.com']));

    // Pas de société dupliquée
    expect(Company::where('vat_number', 'BE0123456789')->count())->toBe(1);

    // L'user est bien créé (cas 3 : join request)
    $user = User::where('email', 'newcomer@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->company_id)->toBeNull();

    // Un join request pending a été créé
    expect(
        CompanyJoinRequest::where('user_id', $user->id)
            ->where('company_id', $company->id)
            ->where('status', CompanyJoinRequestStatus::Pending->value)
            ->exists()
    )->toBeTrue();
});

// ─────────────────────────────────────────────────────────────────────────────
// Test 4 — Activity log produit par RequestCompanyJoinAction (pas hijacking)
// Changement délibéré Phase 9.6 D.1 : le log "Tentative d'inscription" disparaît,
// remplacé par le log de l'Action "Demande d'adhésion soumise".
// ─────────────────────────────────────────────────────────────────────────────

it('logs a join request activity entry when registering with an existing VAT', function () {
    Company::factory()->create(['vat_number' => 'BE0123456789', 'email_domain' => null]);

    mockVat();

    $this->post('/register', registrationPayload(['email' => 'newcomer@example.com']));

    $activity = Activity::where('description', 'Demande d\'adhésion soumise')->latest()->first();
    expect($activity)->not->toBeNull();
});

// ─────────────────────────────────────────────────────────────────────────────
// Tests 5 & 6 — Garde-fous anti-hijacking : jamais company_admin sur company existante
// Couvre les deux vecteurs d'attaque possibles : cas 3 (email perso) et cas 2 (auto-join).
// ─────────────────────────────────────────────────────────────────────────────

it('registering with an existing VAT never grants the company_admin role on the existing company', function () {
    // Cas 3 — email perso (gmail) → pas d'auto-join → join request
    Company::factory()->create(['vat_number' => 'BE0123456789', 'email_domain' => 'acme-sa.be']);
    mockVat();

    $this->post('/register', registrationPayload(['email' => 'attacker@gmail.com']));
    $attacker = User::where('email', 'attacker@gmail.com')->first();

    expect($attacker)->not->toBeNull();
    expect($attacker->company_id)->toBeNull();
    expect($attacker->hasRole('company_admin'))->toBeFalse();
});

it('registering with a matching pro domain auto-joins but never grants company_admin', function () {
    // Cas 2 — email pro match → auto-join immédiat, mais jamais le rôle admin société
    Company::factory()->create(['vat_number' => 'BE0123456789', 'email_domain' => 'acme-sa.be']);
    mockVat();

    $this->post('/register', registrationPayload(['email' => 'newbie@acme-sa.be']));
    $user = User::where('email', 'newbie@acme-sa.be')->first();

    expect($user)->not->toBeNull();
    expect($user->hasRole('company_admin'))->toBeFalse();
});

// ─────────────────────────────────────────────────────────────────────────────
// Test 7 — Rate limiting : la 6e tentative reçoit HTTP 429 (INCHANGÉ)
// Le cache array est vidé en beforeEach global (Pest.php), état propre garanti.
// ─────────────────────────────────────────────────────────────────────────────

it('returns 429 after exceeding the rate limit on the register route', function () {
    mockVat();

    // 5 tentatives — aucune ne dépasse la limite
    foreach (range(1, 5) as $i) {
        $response = $this->post('/register', registrationPayload(['email' => "user{$i}@example.com"]));
        expect($response->status())->not->toBe(429);
    }

    // 6e tentative — rate limiter déclenché
    $response = $this->post('/register', registrationPayload(['email' => 'overflow@example.com']));
    $response->assertStatus(429);
});
