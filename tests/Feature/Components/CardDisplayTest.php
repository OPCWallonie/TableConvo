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

    expect($html)->toContain('linear-gradient');
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

    expect($html)->toContain('linear-gradient');
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
