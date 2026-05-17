<?php

use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Card;
use App\Models\ConversationTable;
use App\Models\GlobalWaitlistEntry;
use App\Models\Level;
use App\Models\Order;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makeUserForceDeleteAdmin(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    return $admin;
}

// ForceDeleteAction (page header) n'est visible que lorsque le record est trashed.
// On soft-delete d'abord, puis on appelle forceDelete.

it('allows user force-delete when no relations exist', function () {
    $admin = makeUserForceDeleteAdmin();
    $user  = User::factory()->create();
    $user->delete();

    Livewire::actingAs($admin)
        ->test(EditUser::class, ['record' => $user->getRouteKey()])
        ->callAction('forceDelete');

    expect(User::withTrashed()->find($user->id))->toBeNull();
});

it('blocks user force-delete when the user has orders', function () {
    $admin = makeUserForceDeleteAdmin();
    $user  = User::factory()->create();
    Order::factory()->for($user)->create();
    $user->delete();

    Livewire::actingAs($admin)
        ->test(EditUser::class, ['record' => $user->getRouteKey()])
        ->callAction('forceDelete')
        ->assertNotified();

    expect(User::withTrashed()->find($user->id))->not->toBeNull();
});

it('blocks user force-delete when the user has registrations', function () {
    $admin = makeUserForceDeleteAdmin();
    $user  = User::factory()->create();
    Registration::factory()->for($user)->for(ConversationTable::factory()->create())->create();
    $user->delete();

    Livewire::actingAs($admin)
        ->test(EditUser::class, ['record' => $user->getRouteKey()])
        ->callAction('forceDelete')
        ->assertNotified();

    expect(User::withTrashed()->find($user->id))->not->toBeNull();
});

it('blocks user force-delete when the user has cards', function () {
    $admin = makeUserForceDeleteAdmin();
    $user  = User::factory()->create();
    Card::factory()->for($user)->create();
    $user->delete();

    Livewire::actingAs($admin)
        ->test(EditUser::class, ['record' => $user->getRouteKey()])
        ->callAction('forceDelete')
        ->assertNotified();

    expect(User::withTrashed()->find($user->id))->not->toBeNull();
});

it('blocks user force-delete when the user has created global waitlist entries', function () {
    $admin   = makeUserForceDeleteAdmin();
    $creator = User::factory()->create();

    $poolUser = User::factory()->withLevel(Level::factory()->create())->create();
    GlobalWaitlistEntry::factory()->pending()->create([
        'user_id'    => $poolUser->id,
        'level_id'   => $poolUser->level_id,
        'created_by' => $creator->id,
    ]);

    $creator->delete();

    Livewire::actingAs($admin)
        ->test(EditUser::class, ['record' => $creator->getRouteKey()])
        ->callAction('forceDelete')
        ->assertNotified();

    expect(User::withTrashed()->find($creator->id))->not->toBeNull();
});

it('blocks bulk force-delete if any selected user has dependencies', function () {
    $admin     = makeUserForceDeleteAdmin();
    $protected = User::factory()->create();
    $free      = User::factory()->create();

    Order::factory()->for($protected)->create();

    // ForceDeleteBulkAction est cachée en état par défaut (blank) du TrashedFilter.
    // La valeur '1' (mode "with trashed") la rend visible — les users non-supprimés apparaissent aussi.
    Livewire::actingAs($admin)
        ->test(ListUsers::class)
        ->set('tableFilters.trashed.value', '1')
        ->callTableBulkAction('forceDelete', [$protected, $free])
        ->assertNotified();

    // L'opération entière est annulée — les deux utilisateurs subsistent (non soft-deleted)
    expect(User::find($protected->id))->not->toBeNull();
    expect(User::find($free->id))->not->toBeNull();
});
