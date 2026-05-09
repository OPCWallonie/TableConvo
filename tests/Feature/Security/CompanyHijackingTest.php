<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Test 1 — Chemin nominal : nouvelle TVA → company + user créés
// ─────────────────────────────────────────────────────────────────────────────

it('registering with a new unique VAT number creates the company and user', function () {
    mockVat();

    $this->post('/register', registrationPayload())->assertRedirect(route('espace.dashboard'));

    expect(Company::where('vat_number', 'BE0123456789')->exists())->toBeTrue();
    $this->assertAuthenticated();
});

// ─────────────────────────────────────────────────────────────────────────────
// Test 2 — Vulnérabilité critique : TVA déjà prise → rejet (garde-régression)
// Ce test s'exécute contre le vrai contrôleur d'inscription (RegisteredUserController).
// ─────────────────────────────────────────────────────────────────────────────

it('registering a new user with a VAT number that already exists fails', function () {
    Company::factory()->create(['vat_number' => 'BE0123456789']);

    mockVat();

    $response = $this->post('/register', registrationPayload(['email' => 'attacker@example.com']));

    $this->assertGuest();
    $response->assertSessionHasErrors('vat_number');
    expect(session('errors')->first('vat_number'))->toContain('déjà enregistrée');
});

// ─────────────────────────────────────────────────────────────────────────────
// Test 3 — Aucune nouvelle company créée lors d'un hijacking tenté
// ─────────────────────────────────────────────────────────────────────────────

it('does not create a duplicate company when VAT is already taken', function () {
    Company::factory()->create(['vat_number' => 'BE0123456789']);

    mockVat();

    $this->post('/register', registrationPayload(['email' => 'attacker@example.com']));

    expect(Company::where('vat_number', 'BE0123456789')->count())->toBe(1);
    expect(User::where('email', 'attacker@example.com')->exists())->toBeFalse();
});

// ─────────────────────────────────────────────────────────────────────────────
// Test 4 — La tentative de hijacking est tracée dans l'activity log
// ─────────────────────────────────────────────────────────────────────────────

it('logs an activity entry when a duplicate VAT registration is attempted', function () {
    Company::factory()->create(['vat_number' => 'BE0123456789']);

    mockVat();

    $this->post('/register', registrationPayload(['email' => 'spy@example.com']));

    $activity = Activity::latest()->first();
    expect($activity)->not->toBeNull();
    expect($activity->description)->toBe("Tentative d'inscription avec numéro de TVA déjà enregistré");
    expect($activity->properties['vat_number'])->toBe('BE0123456789');
});

// ─────────────────────────────────────────────────────────────────────────────
// Test 5 — Rate limiting : la 6e tentative reçoit HTTP 429
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
