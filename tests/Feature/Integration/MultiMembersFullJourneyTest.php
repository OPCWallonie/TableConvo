<?php

use App\Actions\Company\ApproveCompanyJoinRequestAction;
use App\Actions\Company\AssignCompanyAdminAction;
use App\Actions\Company\AutoJoinCompanyByEmailDomainAction;
use App\Actions\Company\RejectCompanyJoinRequestAction;
use App\Actions\Company\RequestCompanyJoinAction;
use App\Models\Company;
use App\Models\User;
use App\Notifications\Company\CompanyAdminAssignedNotification;
use App\Notifications\Company\CompanyAdminRevokedNotification;
use App\Notifications\Company\CompanyAutoJoinedNotification;
use App\Notifications\Company\CompanyJoinApprovedNotification;
use App\Notifications\Company\CompanyJoinRejectedNotification;
use App\Notifications\Company\CompanyJoinRequestedNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin',         'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'company_admin', 'guard_name' => 'web']);
    Notification::fake();
});

// ─────────────────────────────────────────────────────────────────────────────
// Parcours complet multi-membres Phase 9.6
// A (fondateur) → B (auto-join) → C (join request approuvée) →
// D (join request rejetée) → réassignation super admin → succession B→C
// ─────────────────────────────────────────────────────────────────────────────

it('completes the full multi-member lifecycle from registration to succession', function () {

    // ── Étape 1 : A fonde la société ──────────────────────────────────────
    // Simule le résultat de RegisteredUserController Cas 1
    $company = Company::factory()->create([
        'vat_number'   => 'BE0123456789',
        'email_domain' => 'acme-sa.be',
    ]);
    $userA = User::factory()->for($company)->create([
        'email'      => 'arnaud@acme-sa.be',
        'created_at' => now()->subDays(3),
    ]);
    $userA->assignRole('company_admin');

    expect($userA->fresh()->hasRole('company_admin'))->toBeTrue();
    expect($company->email_domain)->toBe('acme-sa.be');

    // ── Étape 2 : B (marie@acme-sa.be) — auto-rattachement par domaine ────
    $userB = User::factory()->create([
        'email'      => 'marie@acme-sa.be',
        'company_id' => null,
        'created_at' => now()->subDays(2),
    ]);

    app(AutoJoinCompanyByEmailDomainAction::class)->execute($userB, $company);

    expect($userB->fresh()->company_id)->toBe($company->id);
    expect($userB->fresh()->hasRole('company_admin'))->toBeFalse();
    Notification::assertSentTo($userA, CompanyAutoJoinedNotification::class);

    // ── Étape 3 : C (freelance@gmail.com) — join request pending ─────────
    $userC = User::factory()->create([
        'email'      => 'freelance@gmail.com',
        'company_id' => null,
        'created_at' => now()->subDays(1),
    ]);

    $joinRequestC = app(RequestCompanyJoinAction::class)->execute($userC, $company);

    expect($userC->fresh()->company_id)->toBeNull();
    Notification::assertSentTo($userA, CompanyJoinRequestedNotification::class);

    // ── Étape 4 : A approuve C ────────────────────────────────────────────
    app(ApproveCompanyJoinRequestAction::class)->execute($userA, $joinRequestC);

    expect($userC->fresh()->company_id)->toBe($company->id);
    Notification::assertSentTo($userC, CompanyJoinApprovedNotification::class);

    // ── Étape 5 : D (sous-trait@autre-boite.com) — join request pending ──
    $userD = User::factory()->create([
        'email'      => 'sous-trait@autre-boite.com',
        'company_id' => null,
    ]);

    $joinRequestD = app(RequestCompanyJoinAction::class)->execute($userD, $company);

    expect($userD->fresh()->company_id)->toBeNull();
    // A toujours notifié (seul admin)
    Notification::assertSentTo($userA, CompanyJoinRequestedNotification::class);

    // ── Étape 6 : A rejette D avec raison ────────────────────────────────
    app(RejectCompanyJoinRequestAction::class)->execute(
        $userA,
        $joinRequestD,
        'Sous-traitant non éligible selon politique entreprise',
    );

    expect($userD->fresh()->company_id)->toBeNull();
    Notification::assertSentTo($userD, CompanyJoinRejectedNotification::class);

    // ── Étape 7 : super admin réassigne company_admin à B ─────────────────
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('admin');

    app(AssignCompanyAdminAction::class)->execute($superAdmin, $company, $userB);

    expect($userB->fresh()->hasRole('company_admin'))->toBeTrue();
    expect($userA->fresh()->hasRole('company_admin'))->toBeFalse();
    Notification::assertSentTo($userB, CompanyAdminAssignedNotification::class);
    Notification::assertSentTo($userA, CompanyAdminRevokedNotification::class);

    // ── Étape 8a : A soft-deleted — A n'est plus admin, pas de succession ─
    // Rafraîchir $userA pour que l'observer voie le bon statut de rôle
    $userA->refresh();
    $userA->delete();

    // B est toujours company_admin (aucune succession déclenchée)
    expect($userB->fresh()->hasRole('company_admin'))->toBeTrue();

    // ── Étape 8b : B soft-deleted — succession vers C (plus ancien restant) ─
    // A < B < C en date de création → C est le seul membre actif restant
    $userB->delete();

    // C doit être le nouveau company_admin (succession automatique)
    expect($userC->fresh()->hasRole('company_admin'))->toBeTrue();
    // D non rattaché à la company → pas éligible à la succession
    expect($userD->fresh()->hasRole('company_admin'))->toBeFalse();

    Notification::assertSentTo($userC, CompanyAdminAssignedNotification::class);
    Notification::assertSentTo($userB, CompanyAdminRevokedNotification::class);
});
