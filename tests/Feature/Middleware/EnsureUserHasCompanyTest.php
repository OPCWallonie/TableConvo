<?php

use App\Models\Company;
use App\Models\User;
use Spatie\Permission\Models\Role;

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
