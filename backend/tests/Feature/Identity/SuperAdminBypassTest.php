<?php

use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Gate;

it('lets a super admin pass an ability defined after the bypass logic already existed, with zero configuration', function () {
    // Simulates "a newly created branch" -- an ability nobody granted
    // the super admin any role/permission for, defined fresh right here,
    // proving Gate::before short-circuits before any policy/role check
    // runs at all, per docs/DOMAIN_BLUEPRINT.md §8.
    Gate::define('manage-a-resource-that-did-not-exist-when-the-bypass-was-written', fn (User $user) => false);

    $superAdmin = User::factory()->superAdmin()->create();
    $regularUser = User::factory()->create();

    expect(Gate::forUser($superAdmin)->allows('manage-a-resource-that-did-not-exist-when-the-bypass-was-written'))->toBeTrue()
        ->and(Gate::forUser($regularUser)->allows('manage-a-resource-that-did-not-exist-when-the-bypass-was-written'))->toBeFalse();
});

it('does not bypass anything for a non-super-admin user, even an explicit allow-all ability', function () {
    Gate::define('anyone-can-do-this', fn (User $user) => true);

    $regularUser = User::factory()->create();

    // Sanity check that the bypass is additive (super admin gets extra
    // access), not a global override that makes every check meaningless.
    expect(Gate::forUser($regularUser)->allows('anyone-can-do-this'))->toBeTrue();
});
