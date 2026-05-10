<?php

use App\Models\User;
use App\Settings\ThemeSettings;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

it('a public page contains the CSS variables in the head', function () {
    $response = $this->get(route('agenda'));

    $response->assertStatus(200);
    $response->assertSee('--color-primary', false);
    $response->assertSee('--color-accent', false);
    $response->assertSee('--color-surface', false);
});

it('changing ThemeSettings color_primary reflects on next page load', function () {
    $settings = app(ThemeSettings::class);
    $settings->color_primary = '#b91c1c';
    $settings->save();

    $response = $this->get(route('agenda'));

    $response->assertSee('#b91c1c', false);
});

it('admin Filament pages do NOT contain the CSS variables', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get('/admin');

    $response->assertSuccessful();
    $response->assertDontSee('--color-primary', false);
});
