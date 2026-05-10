<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    RateLimiter::clear('login|127.0.0.1');
    RateLimiter::clear('register|127.0.0.1');
    RateLimiter::clear('checkout|127.0.0.1');
    RateLimiter::clear('account-deletion|127.0.0.1');
});

it('blocks login after 5 failed attempts', function () {
    // 5 allowed attempts
    foreach (range(1, 5) as $i) {
        $this->post('/login', ['email' => 'bad@example.com', 'password' => 'wrong']);
    }

    // 6th attempt is rate-limited
    $response = $this->post('/login', ['email' => 'bad@example.com', 'password' => 'wrong']);

    $response->assertStatus(429);
});

it('checkout route is rate-limited after 10 requests per minute', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);

    // 10 allowed attempts
    foreach (range(1, 10) as $i) {
        $this->actingAs($user)->post(route('panier.checkout'), []);
    }

    // 11th attempt is rate-limited
    $response = $this->actingAs($user)->post(route('panier.checkout'), []);

    $response->assertStatus(429);
});

it('account deletion route is rate-limited after 3 requests per minute', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);

    // 3 allowed attempts
    foreach (range(1, 3) as $i) {
        $this->actingAs($user)->delete(route('espace.compte.destroy'));
    }

    // 4th attempt is rate-limited
    $response = $this->actingAs($user)->delete(route('espace.compte.destroy'));

    $response->assertStatus(429);
});

it('login rate limiter is named and configured', function () {
    expect(RateLimiter::limiter('login'))->not->toBeNull();
});

it('checkout rate limiter is named and configured', function () {
    expect(RateLimiter::limiter('checkout'))->not->toBeNull();
});
