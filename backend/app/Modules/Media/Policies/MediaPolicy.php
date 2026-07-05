<?php

namespace App\Modules\Media\Policies;

use App\Models\User;
use App\Modules\Media\Models\Media;

/**
 * Authorization for viewing a private-tier Media item
 * (docs/DOMAIN_BLUEPRINT.md §12/§9).
 *
 * SPRINT 1.3 PLACEHOLDER: no real domain (Person/Employee/Student/
 * Guardian, Roles/Permissions) exists yet -- People and Identity are
 * Phase 2. This sprint's Definition of Done is proving the
 * authentication boundary works (an unauthenticated request is refused,
 * an authenticated one is served) and that the Policy wiring is in
 * place, not fine-grained authorization. `view()` currently allows any
 * authenticated user to view any media.
 *
 * This MUST be replaced once real consumers exist: a high-sensitivity
 * collection (Addendum B3) needs its own dedicated Policy logic (e.g.
 * only a student's own guardians and specific staff roles may view a
 * medical report), not this blanket allow-all-authenticated rule.
 */
class MediaPolicy
{
    public function view(User $user, Media $media): bool
    {
        return true;
    }
}
