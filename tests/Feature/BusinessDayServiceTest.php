<?php

use App\Services\BusinessDay\BusinessDayService;
use Carbon\Carbon;

beforeEach(function () {
    $this->service = new BusinessDayService();
});

it('identifies weekend as non business day', function () {
    $saturday = Carbon::parse('2026-05-09'); // Samedi
    $sunday = Carbon::parse('2026-05-10'); // Dimanche

    expect($this->service->isBusinessDay($saturday))->toBeFalse();
    expect($this->service->isBusinessDay($sunday))->toBeFalse();
});

it('identifies monday as business day', function () {
    $monday = Carbon::parse('2026-05-11'); // Lundi normal
    expect($this->service->isBusinessDay($monday))->toBeTrue();
});

it('identifies belgian fixed holiday as non business day', function () {
    $fetteNationale = Carbon::parse('2026-07-21');
    $noel = Carbon::parse('2026-12-25');
    $toussaint = Carbon::parse('2026-11-01');

    expect($this->service->isBusinessDay($fetteNationale))->toBeFalse();
    expect($this->service->isBusinessDay($noel))->toBeFalse();
    expect($this->service->isBusinessDay($toussaint))->toBeFalse();
});

it('identifies easter monday as non business day', function () {
    $easterMonday2026 = Carbon::parse('2026-04-05'); // Lundi de Pâques 2026 (Pâques = 04-04)
    expect($this->service->isBusinessDay($easterMonday2026))->toBeFalse();
});

it('subtracts business days correctly', function () {
    // 3 jours ouvrables avant un vendredi = mardi (en semaine normale)
    $friday = Carbon::parse('2026-05-08'); // Vendredi
    $result = $this->service->subtractBusinessDays($friday, 3);

    expect($result->toDateString())->toBe('2026-05-05'); // Mardi
});

it('skips weekends when subtracting business days', function () {
    // 3 jours ouvrables avant un lundi = mercredi de la semaine précédente
    $monday = Carbon::parse('2026-05-11'); // Lundi
    $result = $this->service->subtractBusinessDays($monday, 3);

    expect($result->toDateString())->toBe('2026-05-06'); // Mercredi
});
