<?php

use App\Core\Models\Organization;
use App\Core\Models\OrganizationModule;
use Illuminate\Database\QueryException;

function makeOrganization(): Organization
{
    return Organization::create([
        'legal_name' => 'AlphaSchool Test Co.',
        'display_name' => 'AlphaSchool',
    ]);
}

it('reports a module as licensed when enabled with no expiration', function () {
    $organization = makeOrganization();
    OrganizationModule::create(['organization_id' => $organization->id, 'module_code' => 'finance', 'enabled' => true]);

    expect($organization->hasLicensed('finance'))->toBeTrue();
});

it('reports a module as unlicensed when disabled', function () {
    $organization = makeOrganization();
    OrganizationModule::create(['organization_id' => $organization->id, 'module_code' => 'finance', 'enabled' => false]);

    expect($organization->hasLicensed('finance'))->toBeFalse();
});

it('reports a module as unlicensed once its licensed_until date has passed', function () {
    $organization = makeOrganization();
    OrganizationModule::create([
        'organization_id' => $organization->id, 'module_code' => 'finance',
        'enabled' => true, 'licensed_until' => now()->subDay()->toDateString(),
    ]);

    expect($organization->hasLicensed('finance'))->toBeFalse();
});

it('reports a module as licensed while licensed_until is still in the future', function () {
    $organization = makeOrganization();
    OrganizationModule::create([
        'organization_id' => $organization->id, 'module_code' => 'finance',
        'enabled' => true, 'licensed_until' => now()->addYear()->toDateString(),
    ]);

    expect($organization->hasLicensed('finance'))->toBeTrue();
});

it('reports an unmentioned module as unlicensed', function () {
    $organization = makeOrganization();

    expect($organization->hasLicensed('finance'))->toBeFalse();
});

it('rejects a module_code outside the fixed Domain module list', function () {
    $organization = makeOrganization();

    OrganizationModule::create(['organization_id' => $organization->id, 'module_code' => 'not-a-real-module', 'enabled' => true]);
})->throws(InvalidArgumentException::class);

it('enforces one row per organization per module_code', function () {
    $organization = makeOrganization();
    OrganizationModule::create(['organization_id' => $organization->id, 'module_code' => 'finance', 'enabled' => true]);

    OrganizationModule::create(['organization_id' => $organization->id, 'module_code' => 'finance', 'enabled' => false]);
})->throws(QueryException::class);
