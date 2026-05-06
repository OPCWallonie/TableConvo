<?php

use App\Filament\Pages\ManageBookingSettings;
use App\Filament\Pages\ManageCardSettings;
use App\Filament\Pages\ManageCompanySettings;
use App\Filament\Pages\ManageEmailSettings;
use App\Filament\Pages\ManageInvoicingSettings;
use App\Filament\Pages\ManageLegalSettings;
use App\Filament\Pages\ManageMollieSettings;
use App\Filament\Pages\ManageSessionDefaultsSettings;
use App\Models\User;
use App\Settings\CompanySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

it('admin can access ManageCompanySettings', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get('/admin/manage-company-settings')
        ->assertSuccessful();
});

it('regular user is redirected away from settings pages', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/manage-company-settings')
        ->assertStatus(403);
});

it('admin can access all settings pages', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $pages = [
        '/admin/manage-company-settings',
        '/admin/manage-invoicing-settings',
        '/admin/manage-mollie-settings',
        '/admin/manage-legal-settings',
        '/admin/manage-booking-settings',
        '/admin/manage-card-settings',
        '/admin/manage-session-defaults-settings',
        '/admin/manage-email-settings',
    ];

    foreach ($pages as $page) {
        $this->actingAs($admin)
            ->get($page)
            ->assertSuccessful();
    }
});

it('saving company settings updates the value in database', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $settings = app(CompanySettings::class);
    $settings->company_name = 'Before';
    $settings->save();

    $this->actingAs($admin);

    Livewire::test(ManageCompanySettings::class)
        ->set('data.company_name', 'TableConvo SRL')
        ->call('save');

    $settings->refresh();
    expect($settings->company_name)->toBe('TableConvo SRL');
});
