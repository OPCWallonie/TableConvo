<?php

use App\Filament\Pages\ManageThemeSettings;
use App\Models\User;
use App\Settings\ThemeSettings;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

it('admin can access the theme settings page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(ManageThemeSettings::getUrl())
        ->assertSuccessful();
});

it('non-admin gets 403 on the theme settings page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(ManageThemeSettings::getUrl())
        ->assertStatus(403);
});

it('admin can save a new primary color', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $settings = app(ThemeSettings::class);
    $settings->color_primary = '#2563eb';
    $settings->save();

    Livewire::actingAs($admin)
        ->test(ManageThemeSettings::class)
        ->set('data.color_primary', '#16a34a')
        ->call('save');

    $settings->refresh();
    expect($settings->color_primary)->toBe('#16a34a');
});

it('admin can save a new card design', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $settings = app(ThemeSettings::class);
    $settings->card_design = 'stamp';
    $settings->save();

    Livewire::actingAs($admin)
        ->test(ManageThemeSettings::class)
        ->set('data.card_design', 'wallet')
        ->call('save');

    $settings->refresh();
    expect($settings->card_design)->toBe('wallet');
});

it('saving an invalid card design value is rejected', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $settings = app(ThemeSettings::class);
    $settings->card_design = 'stamp';
    $settings->save();

    Livewire::actingAs($admin)
        ->test(ManageThemeSettings::class)
        ->set('data.card_design', 'invalid-design')
        ->call('save');

    $settings->refresh();
    expect($settings->card_design)->toBe('stamp');
});

it('contrast ratio is computed correctly for standard colors', function () {
    $page = new ManageThemeSettings();
    $ratio = (new ReflectionMethod($page, 'contrastRatio'))->invoke($page, '#ffffff', '#2563eb');

    // white on blue-600 should give a contrast ratio above 3.5:1
    expect($ratio)->toBeGreaterThan(3.0);
});

it('contrast warning logic detects insufficient contrast', function () {
    $page = new ManageThemeSettings();
    $contrastMethod = new ReflectionMethod($page, 'contrastRatio');

    // Very similar colors — low contrast
    $lowContrast = $contrastMethod->invoke($page, '#eeeeee', '#ffffff');
    expect($lowContrast)->toBeLessThan(4.5);

    // Black on white — maximum contrast
    $highContrast = $contrastMethod->invoke($page, '#000000', '#ffffff');
    expect($highContrast)->toBeGreaterThan(4.5);
});
