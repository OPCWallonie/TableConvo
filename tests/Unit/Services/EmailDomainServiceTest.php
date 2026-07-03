<?php

use App\Models\Company;
use App\Services\EmailDomain\EmailDomainService;
use Tests\TestCase;

uses(TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->service = new EmailDomainService();
});

test('extract retourne le domaine pour un email valide', function () {
    expect($this->service->extract('arnaud@acme-sa.be'))->toBe('acme-sa.be');
    expect($this->service->extract('marie@subdomain.example.com'))->toBe('subdomain.example.com');
});

test('extract retourne null pour un format invalide', function () {
    expect($this->service->extract('pas-un-email'))->toBeNull();
    expect($this->service->extract('@nodomain'))->toBeNull();
    expect($this->service->extract('user@'))->toBeNull();
    expect($this->service->extract(''))->toBeNull();
});

test('isGenericProvider retourne true pour gmail', function () {
    expect($this->service->isGenericProvider('gmail.com'))->toBeTrue();
});

test('isGenericProvider retourne true pour hotmail et outlook', function () {
    expect($this->service->isGenericProvider('hotmail.com'))->toBeTrue();
    expect($this->service->isGenericProvider('outlook.com'))->toBeTrue();
});

test('isGenericProvider retourne true pour les FAI belges', function () {
    expect($this->service->isGenericProvider('skynet.be'))->toBeTrue();
    expect($this->service->isGenericProvider('telenet.be'))->toBeTrue();
    expect($this->service->isGenericProvider('voo.be'))->toBeTrue();
    expect($this->service->isGenericProvider('proximus.be'))->toBeTrue();
});

test('isGenericProvider retourne false pour un domaine pro', function () {
    expect($this->service->isGenericProvider('acme-sa.be'))->toBeFalse();
    expect($this->service->isGenericProvider('monentreprise.com'))->toBeFalse();
});

test('isAcceptableCompanyDomain combine extract et isGenericProvider', function () {
    expect($this->service->isAcceptableCompanyDomain('arnaud@acme-sa.be'))->toBeTrue();
    expect($this->service->isAcceptableCompanyDomain('arnaud@gmail.com'))->toBeFalse();
    expect($this->service->isAcceptableCompanyDomain('pas-un-email'))->toBeFalse();
});

test('findCompanyByDomain retourne la company si match', function () {
    $company = Company::factory()->create(['email_domain' => 'acme-sa.be']);

    $found = $this->service->findCompanyByDomain('acme-sa.be');

    expect($found)->not->toBeNull();
    expect($found->id)->toBe($company->id);
});

test('findCompanyByDomain retourne null si pas de match', function () {
    expect($this->service->findCompanyByDomain('unknown.be'))->toBeNull();
});

test('findCompanyByDomain est insensible à la casse', function () {
    $company = Company::factory()->create(['email_domain' => 'acme-sa.be']);

    expect($this->service->findCompanyByDomain('ACME-SA.BE'))->not->toBeNull();
    expect($this->service->findCompanyByDomain('Acme-Sa.Be'))->not->toBeNull();
});
