<?php

namespace Tests\Fixtures;

use App\Modules\Identity\Models\User;

/**
 * Test-only stand-in for a model that has Spatie's hasRole() convention,
 * without depending on Spatie Permission actually being wired onto User
 * yet (that's Identity's job, Phase 2) -- proves
 * App\Core\Services\ApprovalEngine's duck-typed role check integrates
 * correctly once a real HasRoles-using model exists, using a minimal fake
 * instead of waiting on a module that isn't built.
 */
class FakeRoleHolder extends User
{
    protected $table = 'users'; // same underlying table as User -- this is a behavioral stand-in, not a separate entity

    protected array $fakeRoles = [];

    public function withFakeRoles(array $roles): static
    {
        $this->fakeRoles = $roles;

        return $this;
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->fakeRoles, true);
    }
}
