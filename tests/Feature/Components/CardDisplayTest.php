<?php

use App\Enums\CardStatus;
use App\Models\Card;
use App\Models\CardType;
use App\Settings\ThemeSettings;

function makeTestCard(array $attrs = []): Card
{
    $type = new CardType(['name' => 'Carte Test', 'sessions_count' => 10]);

    $card = new Card(array_merge([
        'sessions_total'     => 10,
        'sessions_remaining' => 7,
        'price_paid'         => 250.00,
        'purchased_at'       => now(),
        'expires_at'         => now()->addMonths(6),
        'status'             => CardStatus::Active,
    ], $attrs));
    $card->setRelation('cardType', $type);

    return $card;
}

it('renders stamp design when ThemeSettings::card_design = stamp', function () {
    $settings = app(ThemeSettings::class);
    $settings->card_design = 'stamp';
    $settings->save();

    $card = makeTestCard();
    $html = view('components.card-display', ['card' => $card])->render();

    expect($html)->toContain('TableConvo');
});

it('renders wallet design when ThemeSettings::card_design = wallet', function () {
    $settings = app(ThemeSettings::class);
    $settings->card_design = 'wallet';
    $settings->save();

    $card = makeTestCard();
    $html = view('components.card-display', ['card' => $card])->render();

    expect($html)->toContain('tc-wallet');
    expect($html)->toContain('radial-gradient');
});

it('renders editorial design when card_design = editorial', function () {
    $settings = app(ThemeSettings::class);
    $settings->card_design = 'editorial';
    $settings->save();

    $card = makeTestCard();
    $html = view('components.card-display', ['card' => $card])->render();

    expect($html)->toContain('box-shadow');
});

it('renders swiss design when card_design = swiss', function () {
    $settings = app(ThemeSettings::class);
    $settings->card_design = 'swiss';
    $settings->save();

    $card = makeTestCard();
    $html = view('components.card-display', ['card' => $card])->render();

    expect($html)->toContain('JetBrains Mono');
});

it('design override prop forces a specific design regardless of settings', function () {
    $settings = app(ThemeSettings::class);
    $settings->card_design = 'stamp';
    $settings->save();

    $card = makeTestCard();
    $html = view('components.card-display', ['card' => $card, 'design' => 'wallet'])->render();

    expect($html)->toContain('tc-wallet');
    expect($html)->toContain('radial-gradient');
});

it('renders the correct number of remaining sessions', function () {
    $settings = app(ThemeSettings::class);
    $settings->card_design = 'stamp';
    $settings->save();

    $card = makeTestCard(['sessions_total' => 10, 'sessions_remaining' => 3]);
    $html = view('components.card-display', ['card' => $card])->render();

    expect($html)->toContain('3');
});

it('shows expired state correctly when card is expired', function () {
    $settings = app(ThemeSettings::class);
    $settings->card_design = 'stamp';
    $settings->save();

    $card = makeTestCard([
        'sessions_remaining' => 0,
        'expires_at'         => now()->subDays(5),
        'status'             => CardStatus::Expired,
    ]);
    $html = view('components.card-display', ['card' => $card])->render();

    expect($html)->toContain('Expir');
});

it('wallet design renders the correct used vs remaining bars count', function () {
    $settings = app(ThemeSettings::class);
    $settings->card_design = 'wallet';
    $settings->save();

    // 10 sessions, 7 remaining → 3 used
    $card = makeTestCard(['sessions_total' => 10, 'sessions_remaining' => 7]);
    $html = view('components.card-display', ['card' => $card])->render();

    expect(substr_count($html, 'var(--color-accent)'))->toBe(3);
    expect(substr_count($html, 'rgba(255,255,255,.22)'))->toBe(7);
});

it("stamp design renders 'Expire bientôt' badge when expiring within warning threshold", function () {
    $settings = app(ThemeSettings::class);
    $settings->card_design = 'stamp';
    $settings->save();

    // 15 days is within the default 30-day max warning threshold
    $card = makeTestCard(['expires_at' => now()->addDays(15)]);
    $html = view('components.card-display', ['card' => $card])->render();

    expect($html)->toContain('Expire bientôt');
});

it("stamp design renders 'Expirée' badge in red when card is expired", function () {
    $settings = app(ThemeSettings::class);
    $settings->card_design = 'stamp';
    $settings->save();

    $card = makeTestCard([
        'expires_at' => now()->subDays(1),
        'status'     => CardStatus::Expired,
    ]);
    $html = view('components.card-display', ['card' => $card])->render();

    expect($html)->toContain('Expirée');
    expect($html)->toContain('#dc2626');
});

it('editorial design renders the progress bar with correct percentage', function () {
    $settings = app(ThemeSettings::class);
    $settings->card_design = 'editorial';
    $settings->save();

    // 7 / 10 = 70%
    $card = makeTestCard(['sessions_total' => 10, 'sessions_remaining' => 7]);
    $html = view('components.card-display', ['card' => $card])->render();

    expect($html)->toContain('width:70%');
});

it('swiss design renders monospace expiration date in ISO format', function () {
    $settings = app(ThemeSettings::class);
    $settings->card_design = 'swiss';
    $settings->save();

    $expiry = now()->addMonths(6);
    $card = makeTestCard(['expires_at' => $expiry]);
    $html = view('components.card-display', ['card' => $card])->render();

    expect($html)->toContain('exp ' . $expiry->format('Y-m-d'));
    expect($html)->toContain('JetBrains Mono');
});

it('expired cards have reduced opacity visual degradation across all designs', function (string $design) {
    $settings = app(ThemeSettings::class);
    $settings->card_design = $design;
    $settings->save();

    $expiredCard = makeTestCard([
        'expires_at' => now()->subDays(1),
        'status'     => CardStatus::Expired,
    ]);

    $html = view('components.card-display', ['card' => $expiredCard])->render();
    expect($html)->toContain('opacity:.55');
})->with(['wallet', 'stamp', 'editorial', 'swiss']);

it('editorial design does not render any per-session visual element', function () {
    $settings = app(ThemeSettings::class);
    $settings->card_design = 'editorial';
    $settings->save();

    $card = makeTestCard(['sessions_total' => 10, 'sessions_remaining' => 7]);
    $html = view('components.card-display', ['card' => $card])->render();

    // No CSS grid of individual session cells (used by stamp)
    expect($html)->not->toContain('display:grid');
    // No per-session flex bars (used by wallet and swiss)
    expect(substr_count($html, 'flex:1'))->toBe(0);
});
