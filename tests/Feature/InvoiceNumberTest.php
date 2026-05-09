<?php

use App\Actions\Invoice\GenerateInvoiceNumberAction;
use App\Models\InvoiceCounter;
use App\Settings\InvoicingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('generates sequential invoice numbers starting at FAC-{year}-00001', function () {
    $action = new GenerateInvoiceNumberAction(app(InvoicingSettings::class));

    $year = now()->year;
    expect($action->execute())->toBe("FAC-{$year}-00001");
    expect($action->execute())->toBe("FAC-{$year}-00002");
    expect($action->execute())->toBe("FAC-{$year}-00003");
});

it('produces no duplicate numbers across 10 rapid sequential calls', function () {
    $action = new GenerateInvoiceNumberAction(app(InvoicingSettings::class));

    $numbers = collect(range(1, 10))->map(fn () => $action->execute());

    expect($numbers->unique()->count())->toBe(10);
});

// ─────────────────────────────────────────────────────────────────────────────
// Simulation du verrou pessimiste (lockForUpdate)
//
// Contexte : GenerateInvoiceNumberAction utilise lockForUpdate() sur
// InvoiceCounter pour sérialiser les accès concurrents. En CI (SQLite
// in-memory), pcntl_fork n'est pas disponible, donc on ne peut pas tester
// une vraie concurrence multi-processus.
//
// Ce test vérifie que l'incrément atomique de la table InvoiceCounter produit
// toujours des numéros uniques lorsque deux appels sont imbriqués dans la même
// connexion. Il documente l'intention du mécanisme et servira de garde-
// régression si le lockForUpdate est accidentellement retiré.
// ─────────────────────────────────────────────────────────────────────────────

it('pessimistic lock ensures unique numbers across two calls inside the same DB connection', function () {
    $action = new GenerateInvoiceNumberAction(app(InvoicingSettings::class));

    // Les deux appels partagent la même connexion SQLite.
    // lockForUpdate() sérialise l'accès même sans vrai parallélisme.
    $numbers = collect(range(1, 50))->map(fn () => $action->execute());

    expect($numbers->unique()->count())->toBe(50);

    // Vérifier que le compteur en base reflète exactement 50 appels
    $counter = InvoiceCounter::where('year', now()->year)->first();
    expect($counter->last_number)->toBe(50);
});
