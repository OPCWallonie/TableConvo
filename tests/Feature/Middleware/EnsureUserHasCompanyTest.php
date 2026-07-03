<?php

use App\Models\Company;
use App\Models\User;
use Spatie\Permission\Models\Role;

// ─────────────────────────────────────────────────────────────────────────────
// Tests sur les vraies routes /panier et /panier/checkout
// Prouve que le middleware est câblé et que abort(422) dans
// CreateOrderFromCartAction est inaccessible par le flow web normal.
// ─────────────────────────────────────────────────────────────────────────────

test('GET /panier — user sans société est redirigé vers profil, pas de 422', function () {
    $user = User::factory()->create(['company_id' => null]);

    $response = $this->actingAs($user)->get(route('panier'));

    $response->assertRedirect(route('espace.profil'));
    $response->assertSessionHas('status', 'company_missing');
});

test('POST /panier/checkout — user sans société est redirigé vers profil, pas de 422', function () {
    $user = User::factory()->create(['company_id' => null]);

    $response = $this->actingAs($user)->post(route('panier.checkout'));

    $response->assertRedirect(route('espace.profil'));
    $response->assertSessionHas('status', 'company_missing');
});

test('GET /panier — user avec société accède normalement (non-régression)', function () {
    $company = Company::factory()->create();
    $user    = User::factory()->for($company)->create();

    // On ne teste pas le rendu Livewire complet, juste que le middleware laisse passer
    $response = $this->actingAs($user)->get(route('panier'));

    $response->assertSuccessful();
});

test('espace.profil est accessible sans société (pas de boucle de redirection)', function () {
    $user = User::factory()->create(['company_id' => null]);

    $response = $this->actingAs($user)->get(route('espace.profil'));

    $response->assertSuccessful(); // 200, pas de redirect infini
});

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web']);

    // Enregistrer une route de test protégée par le middleware
    \Illuminate\Support\Facades\Route::get('/_test/panier', fn () => 'ok')
        ->middleware(['web', 'auth', 'ensure.company'])
        ->name('_test.panier');
});

test('un user avec company passe le middleware', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);

    $response = $this->actingAs($user)->get('/_test/panier');

    $response->assertOk();
});

test('un user sans company est redirigé vers son profil', function () {
    $user = User::factory()->create(['company_id' => null]);

    $response = $this->actingAs($user)->get('/_test/panier');

    $response->assertRedirect(route('espace.profil'));
    $response->assertSessionHas('status', 'company_missing');
});

test('un admin TableConvo est redirigé vers le panel Filament', function () {
    $admin = User::factory()->create(['company_id' => null]);
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get('/_test/panier');

    $response->assertRedirect(route('filament.admin.pages.dashboard'));
    $response->assertSessionHas('status', 'admin_no_purchase');
});

test('un invité (non connecté) est redirigé vers login', function () {
    $response = $this->get('/_test/panier');

    $response->assertRedirect(route('login'));
});

test('un admin TableConvo n\'est PAS redirigé vers profil même sans company', function () {
    $admin = User::factory()->create(['company_id' => null]);
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get('/_test/panier');

    // Doit aller vers /admin, PAS vers espace.profil
    $response->assertRedirect(route('filament.admin.pages.dashboard'));
    $this->assertNotEquals(route('espace.profil'), $response->headers->get('Location'));
});
