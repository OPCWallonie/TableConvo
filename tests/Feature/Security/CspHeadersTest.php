<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('public pages include a Content-Security-Policy header', function () {
    $response = $this->get(route('agenda'));

    $response->assertSuccessful();
    $response->assertHeader('Content-Security-Policy');
});

it('CSP header contains the expected directives', function () {
    $response = $this->get(route('agenda'));

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)
        ->toContain("default-src 'self'")
        ->toContain("style-src 'self' 'unsafe-inline'")
        ->toContain("font-src 'self' https://fonts.bunny.net")
        ->toContain("frame-ancestors 'none'");
});

it('Filament admin panel does NOT receive the custom CSP header', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get('/admin');

    $response->assertSuccessful();

    $csp = $response->headers->get('Content-Security-Policy');
    // CSP middleware is intentionally absent from the Filament panel
    expect($csp)->toBeNull();
});

it('CSP allows unsafe-eval for Alpine and Livewire compatibility', function () {
    $response = $this->get(route('home'));

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain("'unsafe-eval'");
});

it('CSP allows fonts.bunny.net in style-src for Figtree stylesheet', function () {
    $response = $this->get(route('home'));

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain('style-src')
        ->and($csp)->toContain('fonts.bunny.net');
});

it('CSP script-src contains both unsafe-inline and unsafe-eval together', function () {
    $response = $this->get(route('agenda'));

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)
        ->toContain("'unsafe-inline'")
        ->toContain("'unsafe-eval'");
});
