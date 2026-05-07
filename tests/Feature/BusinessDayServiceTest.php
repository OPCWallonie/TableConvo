<?php

use App\Services\BusinessDay\BusinessDayService;
use Carbon\Carbon;

beforeEach(function () {
    $this->service = new BusinessDayService();
});

// --- isBusinessDay ---

it('isBusinessDay returns false for Saturday', function () {
    expect($this->service->isBusinessDay(Carbon::parse('2026-05-09')))->toBeFalse();
});

it('isBusinessDay returns false for Sunday', function () {
    expect($this->service->isBusinessDay(Carbon::parse('2026-05-10')))->toBeFalse();
});

it('isBusinessDay returns false for January 1st 2026', function () {
    expect($this->service->isBusinessDay(Carbon::parse('2026-01-01')))->toBeFalse();
});

it('isBusinessDay returns false for May 1st 2026', function () {
    expect($this->service->isBusinessDay(Carbon::parse('2026-05-01')))->toBeFalse();
});

it('isBusinessDay returns false for July 21st 2026', function () {
    expect($this->service->isBusinessDay(Carbon::parse('2026-07-21')))->toBeFalse();
});

it('isBusinessDay returns false for November 11th 2026', function () {
    expect($this->service->isBusinessDay(Carbon::parse('2026-11-11')))->toBeFalse();
});

it('isBusinessDay returns false for December 25th 2026', function () {
    expect($this->service->isBusinessDay(Carbon::parse('2026-12-25')))->toBeFalse();
});

it('isBusinessDay returns false for Easter Monday 2026 (April 6th)', function () {
    // Pâques 2026 = dimanche 5 avril — Lundi de Pâques = 6 avril
    expect($this->service->isBusinessDay(Carbon::parse('2026-04-06')))->toBeFalse();
});

it('isBusinessDay returns false for Ascension 2026 (May 14th)', function () {
    // Pâques + 39 jours = 14 mai
    expect($this->service->isBusinessDay(Carbon::parse('2026-05-14')))->toBeFalse();
});

it('isBusinessDay returns false for Pentecost Monday 2026 (May 25th)', function () {
    // Pâques + 50 jours = 25 mai
    expect($this->service->isBusinessDay(Carbon::parse('2026-05-25')))->toBeFalse();
});

it('isBusinessDay returns true for a regular Wednesday', function () {
    // Mercredi 6 mai 2026 — pas de férié
    expect($this->service->isBusinessDay(Carbon::parse('2026-05-06')))->toBeTrue();
});

// --- subBusinessDays ---

it('subBusinessDays skips weekends correctly', function () {
    // Lundi 11 mai - 3 jours ouvrables = mercredi 6 mai (saute sam/dim 9-10)
    $result = $this->service->subBusinessDays(Carbon::parse('2026-05-11'), 3);
    expect($result->toDateString())->toBe('2026-05-06');
});

it('subBusinessDays skips holidays', function () {
    // Mercredi 6 mai - 3 jours ouvrables = jeudi 30 avril (saute 1er mai + WE)
    // 6 mai → mardi 5 (1), lundi 4 (2), dim 3 skip, sam 2 skip, ven 1 (férié) skip → jeu 30 avr (3)
    $result = $this->service->subBusinessDays(Carbon::parse('2026-05-06'), 3);
    expect($result->toDateString())->toBe('2026-04-30');
});

it('subBusinessDays skips both weekend AND adjacent holiday', function () {
    // Lundi 4 mai - 3 jours ouvrables : dim 3 skip, sam 2 skip, 1er mai (férié) skip → jeu 30 avr (1) → mer 29 avr (2) → mar 28 avr (3)
    // NB : le brief indique "2026-04-29" mais c'est une erreur : le 1er mai est aussi un jour férié (vendredi)
    $result = $this->service->subBusinessDays(Carbon::parse('2026-05-04'), 3);
    expect($result->toDateString())->toBe('2026-04-28');
});

// --- addBusinessDays ---

it('addBusinessDays skips holidays', function () {
    // Jeudi 30 avril + 3 jours ouvrables : 1er mai (férié) skip, sam 2 skip, dim 3 skip → lun 4 (1) → mar 5 (2) → mer 6 (3)
    $result = $this->service->addBusinessDays(Carbon::parse('2026-04-30'), 3);
    expect($result->toDateString())->toBe('2026-05-06');
});

// --- businessDaysBetween ---

it('businessDaysBetween counts correctly with exclusive end', function () {
    // Lun 4 mai → jeu 8 mai : lun(1) mar(2) mer(3) jeu(4) — ven 8 exclu
    $result = $this->service->businessDaysBetween(
        Carbon::parse('2026-05-04'),
        Carbon::parse('2026-05-08')
    );
    expect($result)->toBe(4);
});
