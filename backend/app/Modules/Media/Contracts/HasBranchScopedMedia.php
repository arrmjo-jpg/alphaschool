<?php

namespace App\Modules\Media\Contracts;

/**
 * Implemented by any model whose media should be partitioned by branch in
 * physical storage (docs/DOMAIN_BLUEPRINT.md §12's path scheme:
 * {tier}/{branch_id}/{model-type}/{model_id}/{collection}/...).
 *
 * This lives in the Media Foundation module, not Core -- "branch" is a
 * legitimate cross-cutting organizational concept Foundation modules may
 * reference (it already appears throughout Identity/People), but Core
 * itself must stay domain-agnostic and never know what a "branch" is,
 * even indirectly through an interface name.
 *
 * A model with no branch scope (a global entity) simply does not
 * implement this interface -- App\Modules\Media\Support\
 * AlphaSchoolPathGenerator omits the branch segment entirely rather than
 * writing an empty/null placeholder folder.
 */
interface HasBranchScopedMedia
{
    /**
     * Returns the branch ID this model's media should be partitioned
     * under, or null if this specific instance has no branch (e.g. a
     * Guardian, which is never branch-scoped per
     * docs/DOMAIN_BLUEPRINT.md's Branch Ownership rules).
     */
    public function mediaPathBranchId(): ?int;
}
