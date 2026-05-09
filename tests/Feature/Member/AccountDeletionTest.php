<?php

use App\Models\User;
use App\Notifications\AccountAnonymizedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Test 1 — Suppression réussie avec le bon mot de passe
// ─────────────────────────────────────────────────────────────────────────────

it('authenticated user can delete their own account with correct password', function () {
    Notification::fake();

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->delete(route('espace.compte.destroy'), [
            'password' => 'password',
        ]);

    $response->assertRedirect('/');
    $this->assertGuest();
    expect(User::find($user->id))->toBeNull();
});

// ─────────────────────────────────────────────────────────────────────────────
// Test 2 — Mot de passe incorrect → rejet, compte intact
// ─────────────────────────────────────────────────────────────────────────────

it('account deletion fails with wrong password', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->from(route('espace.profil'))
        ->delete(route('espace.compte.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response->assertRedirect(route('espace.profil'));
    $response->assertSessionHasErrorsIn('accountDeletion', ['password']);
    $this->assertAuthenticatedAs($user);
    expect(User::find($user->id))->not->toBeNull();
});

// ─────────────────────────────────────────────────────────────────────────────
// Test 3 — Après suppression : user soft-deleté + notification envoyée
// ─────────────────────────────────────────────────────────────────────────────

it('user is anonymized soft-deleted and notified after account deletion', function () {
    Notification::fake();

    $user = User::factory()->create([
        'first_name' => 'Denis',
        'email'      => 'denis@example.com',
    ]);

    $this->actingAs($user)
        ->delete(route('espace.compte.destroy'), ['password' => 'password']);

    $soft = User::withTrashed()->find($user->id);
    expect($soft)->not->toBeNull();
    expect($soft->deleted_at)->not->toBeNull();
    expect($soft->email)->toBe("anonymized-{$user->id}@anonymized.local");

    Notification::assertSentOnDemand(
        AccountAnonymizedNotification::class,
        fn ($n, $channels, $notifiable) =>
            $notifiable->routes['mail'] === 'denis@example.com'
    );
});
