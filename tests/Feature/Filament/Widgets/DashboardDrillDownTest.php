<?php

use App\Filament\Resources\Cards\CardResource;
use App\Filament\Resources\ConversationTables\ConversationTableResource;
use App\Filament\Widgets\OperationalStatsWidget;
use App\Filament\Widgets\SessionFillRateChartWidget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makeDrillDownAdmin(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    return $admin;
}

it('OperationalStatsWidget renders Sessions stat with drill-down URL to ConversationTableResource', function () {
    $admin = makeDrillDownAdmin();

    Livewire::actingAs($admin)
        ->test(OperationalStatsWidget::class)
        ->assertSeeHtml(ConversationTableResource::getUrl('index'));
});

it('OperationalStatsWidget renders Cartes actives stat with drill-down URL to CardResource', function () {
    $admin = makeDrillDownAdmin();

    Livewire::actingAs($admin)
        ->test(OperationalStatsWidget::class)
        ->assertSeeHtml(CardResource::getUrl('index'));
});

it('SessionFillRateChartWidget renders custom view with footer drill-down link', function () {
    $admin = makeDrillDownAdmin();

    Livewire::actingAs($admin)
        ->test(SessionFillRateChartWidget::class)
        ->assertSee('Voir le détail')
        ->assertSeeHtml(ConversationTableResource::getUrl('index'));
});
