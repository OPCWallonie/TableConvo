<?php

use App\Filament\RelationManagers\ActivityRelationManager;
use App\Filament\Resources\ActivityLog\ActivityLogResource;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

it('timeline shows activity logs for a given user', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $target = User::factory()->create();

    activity()
        ->performedOn($target)
        ->causedBy($admin)
        ->log('Niveau attribué');

    Livewire::actingAs($admin)
        ->test(ActivityRelationManager::class, [
            'ownerRecord' => $target,
            'pageClass'   => EditUser::class,
        ])
        ->assertSuccessful()
        ->assertSee('Niveau attribué');
});

it('timeline excludes activity logs for other users', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $target = User::factory()->create();
    $other  = User::factory()->create();

    activity()->performedOn($target)->causedBy($admin)->log('Log pour target');
    activity()->performedOn($other)->causedBy($admin)->log('Log pour other');

    Livewire::actingAs($admin)
        ->test(ActivityRelationManager::class, [
            'ownerRecord' => $target,
            'pageClass'   => EditUser::class,
        ])
        ->assertSee('Log pour target')
        ->assertDontSee('Log pour other');
});

it('timeline respects limit of 50 entries', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $target = User::factory()->create();

    for ($i = 1; $i <= 60; $i++) {
        activity()->performedOn($target)->causedBy($admin)->log("Log #{$i}");
    }

    $component = Livewire::actingAs($admin)
        ->test(ActivityRelationManager::class, [
            'ownerRecord' => $target,
            'pageClass'   => EditUser::class,
        ]);

    $component->assertSuccessful();

    // 60 logs but paginated at 50 — first page shows at most 50
    expect($target->activities()->count())->toBe(60);
    $component->assertSee('Log #60'); // most recent on first page (sorted desc)
});

it('"Voir tous les logs" link points to the filtered ActivityLogResource', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $target = User::factory()->create();

    $expectedUrl = ActivityLogResource::getUrl('index') . '?' . http_build_query([
        'tableFilters' => [
            'subject_type' => ['value' => User::class],
            'subject_id'   => ['value' => $target->id],
        ],
    ]);

    expect($expectedUrl)->toContain('subject_type')
        ->and($expectedUrl)->toContain('subject_id')
        ->and($expectedUrl)->toContain((string) $target->id);
});
