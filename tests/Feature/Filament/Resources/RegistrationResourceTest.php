<?php

use App\Enums\RegistrationStatus;
use App\Filament\Resources\Registrations\Pages\ListRegistrations;
use App\Filament\Resources\Registrations\RegistrationResource;
use App\Models\ConversationTable;
use App\Models\Level;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makeRegistrationAdmin(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    return $admin;
}

it('admin can list registrations', function () {
    $admin = makeRegistrationAdmin();
    Registration::factory()->create();

    $this->actingAs($admin)
        ->get(RegistrationResource::getUrl('index'))
        ->assertSuccessful();
});

it('non-admin cannot access the registrations resource', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(RegistrationResource::getUrl('index'))
        ->assertForbidden();
});

it('filter status works', function () {
    $admin = makeRegistrationAdmin();

    $registered = Registration::factory()->create(['status' => RegistrationStatus::Registered]);
    $cancelled  = Registration::factory()->create(['status' => RegistrationStatus::Cancelled]);

    $component = Livewire::actingAs($admin)
        ->test(ListRegistrations::class)
        ->set('tableFilters.status.value', RegistrationStatus::Registered->value);

    $records = $component->instance()->getTable()->getRecords();

    expect($records->pluck('id'))->toContain($registered->id);
    expect($records->pluck('id'))->not->toContain($cancelled->id);
});

it('filter session_future works', function () {
    $admin = makeRegistrationAdmin();

    $future = Registration::factory()->create([
        'conversation_table_id' => ConversationTable::factory()->create([
            'scheduled_at' => now()->addDays(7),
        ])->id,
    ]);
    $past = Registration::factory()->create([
        'conversation_table_id' => ConversationTable::factory()->create([
            'scheduled_at' => now()->subDays(7),
        ])->id,
    ]);

    $component = Livewire::actingAs($admin)
        ->test(ListRegistrations::class)
        ->set('tableFilters.session_future.isActive', true);

    $records = $component->instance()->getTable()->getRecords();

    expect($records->pluck('id'))->toContain($future->id);
    expect($records->pluck('id'))->not->toContain($past->id);
});

it('filter session_past_30_days works', function () {
    $admin = makeRegistrationAdmin();

    $recent = Registration::factory()->create([
        'conversation_table_id' => ConversationTable::factory()->create([
            'scheduled_at' => now()->subDays(10),
        ])->id,
    ]);
    $old = Registration::factory()->create([
        'conversation_table_id' => ConversationTable::factory()->create([
            'scheduled_at' => now()->subDays(40),
        ])->id,
    ]);
    $future = Registration::factory()->create([
        'conversation_table_id' => ConversationTable::factory()->create([
            'scheduled_at' => now()->addDays(5),
        ])->id,
    ]);

    $component = Livewire::actingAs($admin)
        ->test(ListRegistrations::class)
        ->set('tableFilters.session_past_30_days.isActive', true);

    $records = $component->instance()->getTable()->getRecords();

    expect($records->pluck('id'))->toContain($recent->id);
    expect($records->pluck('id'))->not->toContain($old->id);
    expect($records->pluck('id'))->not->toContain($future->id);
});

it('filter level_id works', function () {
    $admin = makeRegistrationAdmin();

    $levelA = Level::factory()->create(['code' => 'A1', 'sort_order' => 1]);
    $levelB = Level::factory()->create(['code' => 'B2', 'sort_order' => 4]);

    $regA = Registration::factory()->create([
        'conversation_table_id' => ConversationTable::factory()->create(['level_id' => $levelA->id])->id,
    ]);
    $regB = Registration::factory()->create([
        'conversation_table_id' => ConversationTable::factory()->create(['level_id' => $levelB->id])->id,
    ]);

    $component = Livewire::actingAs($admin)
        ->test(ListRegistrations::class)
        ->set('tableFilters.level_id.value', $levelA->id);

    $records = $component->instance()->getTable()->getRecords();

    expect($records->pluck('id'))->toContain($regA->id);
    expect($records->pluck('id'))->not->toContain($regB->id);
});

it('search by user full_name works', function () {
    $admin = makeRegistrationAdmin();

    $userA = User::factory()->create(['first_name' => 'Charlotte', 'last_name' => 'Dupont']);
    $userB = User::factory()->create(['first_name' => 'Émile', 'last_name' => 'Bernard']);

    Registration::factory()->create(['user_id' => $userA->id]);
    Registration::factory()->create(['user_id' => $userB->id]);

    $component = Livewire::actingAs($admin)
        ->test(ListRegistrations::class)
        ->set('tableSearch', 'Charlotte');

    $records = $component->instance()->getTable()->getRecords();

    expect($records->pluck('user_id'))->toContain($userA->id);
    expect($records->pluck('user_id'))->not->toContain($userB->id);
});

it('search by conversationTable topic works', function () {
    $admin = makeRegistrationAdmin();

    $tableA = ConversationTable::factory()->create(['topic' => 'Les transports en Belgique']);
    $tableB = ConversationTable::factory()->create(['topic' => 'La cuisine flamande']);

    Registration::factory()->create(['conversation_table_id' => $tableA->id]);
    Registration::factory()->create(['conversation_table_id' => $tableB->id]);

    $component = Livewire::actingAs($admin)
        ->test(ListRegistrations::class)
        ->set('tableSearch', 'transports');

    $records = $component->instance()->getTable()->getRecords();

    expect($records->pluck('conversation_table_id'))->toContain($tableA->id);
    expect($records->pluck('conversation_table_id'))->not->toContain($tableB->id);
});

it('view registration page renders with infolist content', function () {
    $admin = makeRegistrationAdmin();

    $user  = User::factory()->create(['first_name' => 'Marie', 'last_name' => 'Lecomte']);
    $table = ConversationTable::factory()->create(['topic' => 'Le vélo en ville']);

    $registration = Registration::factory()->create([
        'user_id'               => $user->id,
        'conversation_table_id' => $table->id,
        'status'                => RegistrationStatus::Registered,
    ]);

    $this->actingAs($admin)
        ->get(RegistrationResource::getUrl('view', ['record' => $registration]))
        ->assertSuccessful()
        ->assertSee('Inscription')
        ->assertSee('Session')
        ->assertSee('Détails complémentaires');
});

it('no create action is exposed on the registrations resource', function () {
    expect(RegistrationResource::canCreate())->toBeFalse();
});
