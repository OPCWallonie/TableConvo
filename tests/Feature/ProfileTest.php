<?php

use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/espace/profil');

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/espace/profil', [
            'first_name' => 'Test',
            'last_name'  => 'User',
            'email'      => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/espace/profil');

    $user->refresh();

    $this->assertSame('Test', $user->first_name);
    $this->assertSame('User', $user->last_name);
    $this->assertSame('test@example.com', $user->email);
    $this->assertNull($user->email_verified_at);
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/espace/profil', [
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'email'      => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/espace/profil');

    $this->assertNotNull($user->refresh()->email_verified_at);
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete('/espace/compte', [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    // Soft delete : le user existe encore en DB mais deleted_at est rempli
    $this->assertNotNull($user->fresh()->deleted_at);
});

test('user account is anonymized on deletion', function () {
    $user = User::factory()->create();
    $userId = $user->id;

    $this
        ->actingAs($user)
        ->delete('/espace/compte', ['password' => 'password']);

    $deleted = User::withTrashed()->find($userId);
    $this->assertSame('Compte', $deleted->first_name);
    $this->assertSame('supprimé', $deleted->last_name);
    $this->assertSame("deleted-{$userId}@deleted.local", $deleted->email);
    $this->assertNull($deleted->phone);
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/espace/profil')
        ->delete('/espace/compte', [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrorsIn('accountDeletion', 'password')
        ->assertRedirect('/espace/profil');

    $this->assertNotNull($user->fresh());
});
