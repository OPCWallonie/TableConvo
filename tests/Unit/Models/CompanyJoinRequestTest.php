<?php

use App\Enums\CompanyJoinRequestStatus;
use App\Models\Company;
use App\Models\CompanyJoinRequest;
use App\Models\User;

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

test('la factory crée une demande en status pending par défaut', function () {
    $request = CompanyJoinRequest::factory()->create();

    expect($request->status)->toBe(CompanyJoinRequestStatus::Pending);
    expect($request->requested_at)->not->toBeNull();
    expect($request->resolved_at)->toBeNull();
});

test('le scope pending filtre correctement', function () {
    CompanyJoinRequest::factory()->create(['status' => CompanyJoinRequestStatus::Pending]);
    CompanyJoinRequest::factory()->approved()->create();
    CompanyJoinRequest::factory()->rejected()->create();

    $pending = CompanyJoinRequest::pending()->get();

    expect($pending)->toHaveCount(1);
    expect($pending->first()->status)->toBe(CompanyJoinRequestStatus::Pending);
});

test('le scope resolved filtre approved, rejected et cancelled', function () {
    CompanyJoinRequest::factory()->create();
    CompanyJoinRequest::factory()->approved()->create();
    CompanyJoinRequest::factory()->rejected()->create();
    CompanyJoinRequest::factory()->cancelled()->create();

    expect(CompanyJoinRequest::resolved()->count())->toBe(3);
});

test('les relations company, user et resolvedBy fonctionnent', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => null]);
    $resolver = User::factory()->create(['company_id' => null]);

    $request = CompanyJoinRequest::factory()->create([
        'company_id'          => $company->id,
        'user_id'             => $user->id,
        'resolved_by_user_id' => $resolver->id,
    ]);

    expect($request->company->id)->toBe($company->id);
    expect($request->user->id)->toBe($user->id);
    expect($request->resolvedBy->id)->toBe($resolver->id);
});

test('le soft delete fonctionne', function () {
    $request = CompanyJoinRequest::factory()->create();
    $id = $request->id;

    $request->delete();

    expect(CompanyJoinRequest::find($id))->toBeNull();
    expect(CompanyJoinRequest::withTrashed()->find($id))->not->toBeNull();
});

test('le hook creating bloque un second pending pour le même couple user/company', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => null]);

    CompanyJoinRequest::factory()->create([
        'company_id' => $company->id,
        'user_id'    => $user->id,
        'status'     => CompanyJoinRequestStatus::Pending,
    ]);

    expect(fn () => CompanyJoinRequest::factory()->create([
        'company_id' => $company->id,
        'user_id'    => $user->id,
        'status'     => CompanyJoinRequestStatus::Pending,
    ]))->toThrow(\RuntimeException::class);
});

test('le hook creating autorise un second pending si le premier est approuvé', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => null]);

    CompanyJoinRequest::factory()->approved()->create([
        'company_id' => $company->id,
        'user_id'    => $user->id,
    ]);

    $second = CompanyJoinRequest::factory()->create([
        'company_id' => $company->id,
        'user_id'    => $user->id,
        'status'     => CompanyJoinRequestStatus::Pending,
    ]);

    expect($second->exists)->toBeTrue();
});
