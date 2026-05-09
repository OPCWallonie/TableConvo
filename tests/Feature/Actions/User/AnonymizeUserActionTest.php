<?php

use App\Actions\User\AnonymizeUserAction;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use App\Notifications\AccountAnonymizedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Test 1 — Les données personnelles sont effacées
// ─────────────────────────────────────────────────────────────────────────────

it('anonymizes user personal data', function () {
    $user = User::factory()->create([
        'first_name' => 'Alice',
        'last_name'  => 'Dupont',
        'email'      => 'alice@example.com',
        'phone'      => '+32 470 123 456',
    ]);

    app(AnonymizeUserAction::class)->execute($user);

    $fresh = User::withTrashed()->find($user->id);
    expect($fresh->first_name)->toBe('Compte');
    expect($fresh->last_name)->toBe('Supprimé');
    expect($fresh->email)->toBe("anonymized-{$user->id}@anonymized.local");
    expect($fresh->phone)->toBeNull();
});

// ─────────────────────────────────────────────────────────────────────────────
// Test 2 — L'utilisateur est soft-deleté à l'intérieur de l'action
// ─────────────────────────────────────────────────────────────────────────────

it('soft deletes the user', function () {
    $user = User::factory()->create();

    app(AnonymizeUserAction::class)->execute($user);

    expect(User::find($user->id))->toBeNull();
    expect(User::withTrashed()->find($user->id))->not->toBeNull();
    expect(User::withTrashed()->find($user->id)->deleted_at)->not->toBeNull();
});

// ─────────────────────────────────────────────────────────────────────────────
// Test 3 — L'activity log trace l'email d'origine et le performer
// ─────────────────────────────────────────────────────────────────────────────

it('logs activity with original email and performer', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $user = User::factory()->create(['email' => 'victim@example.com']);

    app(AnonymizeUserAction::class)->execute($user, $admin);

    $activity = Activity::latest()->first();
    expect($activity)->not->toBeNull();
    expect($activity->description)->toBe('Compte anonymisé');
    expect($activity->causer_id)->toBe($admin->id);
    expect($activity->properties['original_email'])->toBe('victim@example.com');
});

// ─────────────────────────────────────────────────────────────────────────────
// Test 4 — La notification de confirmation est envoyée à l'adresse d'origine
// ─────────────────────────────────────────────────────────────────────────────

it('sends confirmation notification to the original email address', function () {
    Notification::fake();

    $user = User::factory()->create([
        'first_name' => 'Bob',
        'email'      => 'bob@example.com',
    ]);

    app(AnonymizeUserAction::class)->execute($user);

    Notification::assertSentOnDemand(
        AccountAnonymizedNotification::class,
        fn (AccountAnonymizedNotification $n, array $channels, object $notifiable) =>
            $notifiable->routes['mail'] === 'bob@example.com'
            && $n->firstName === 'Bob'
    );
});

// ─────────────────────────────────────────────────────────────────────────────
// Test 5 (invariant RGPD/comptable critique) — Les snapshots de facture
// contiennent toujours les données d'origine après anonymisation du compte.
// Obligation légale : conservation 7 ans (art. III.86 CSA et loi comptable BE).
// ─────────────────────────────────────────────────────────────────────────────

it('preserves invoice billing_snapshot which still contains pre-anonymization data', function () {
    $user = User::factory()->create(['first_name' => 'Claire', 'last_name' => 'Martin']);

    $order   = Order::factory()->create([
        'user_id'          => $user->id,
        'company_snapshot' => ['name' => 'Martin Industries SA', 'vat_number' => 'BE0987654321'],
    ]);
    $invoice = Invoice::factory()->create([
        'order_id'         => $order->id,
        'billing_snapshot' => [
            'recipient' => ['name' => 'Martin Industries SA', 'vat_number' => 'BE0987654321'],
            'issuer'    => ['company_name' => 'TableConvo SRL'],
        ],
    ]);

    app(AnonymizeUserAction::class)->execute($user);

    $snapshot = $invoice->fresh()->billing_snapshot;
    expect($snapshot['recipient']['name'])->toBe('Martin Industries SA');
    expect($snapshot['recipient']['vat_number'])->toBe('BE0987654321');
});

// ─────────────────────────────────────────────────────────────────────────────
// Test 6 — Un admin peut anonymiser un autre utilisateur
// ─────────────────────────────────────────────────────────────────────────────

it('can be performed by admin on another user account', function () {
    Notification::fake();

    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $target = User::factory()->create(['email' => 'target@example.com']);

    app(AnonymizeUserAction::class)->execute($target, $admin);

    expect(User::find($target->id))->toBeNull();
    expect(User::withTrashed()->find($target->id)->email)->toBe("anonymized-{$target->id}@anonymized.local");
});
