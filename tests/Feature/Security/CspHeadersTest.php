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

it('Filament admin pages also include a CSP header', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get('/admin');

    $response->assertSuccessful();
    $response->assertHeader('Content-Security-Policy');
});
