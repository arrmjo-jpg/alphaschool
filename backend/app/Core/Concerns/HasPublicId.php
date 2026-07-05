<?php

namespace App\Core\Concerns;

use Illuminate\Support\Str;

/**
 * Dual-ID strategy per docs/DOMAIN_BLUEPRINT.md Addendum D4: the internal
 * auto-increment integer stays the real primary key (cheap joins across
 * join-heavy chains like Enrollment/Employment), while `public_id` (a
 * ULID) is the only identifier that may appear in external API responses
 * or route-model binding. The raw internal `id` must never leak externally.
 *
 * Media is the one named exception (its own PK IS a ULID, see
 * App\Modules\Media\Models\Media) -- every other domain aggregate uses
 * this trait instead.
 *
 * Built now, ahead of a third real consumer, because D4 is an explicit,
 * already-frozen Blueprint decision anticipating exactly this reuse
 * (the same justification used for building HasTemporalAssignment before
 * Employment/Enrollment existed) -- not speculative Core growth.
 */
trait HasPublicId
{
    protected static function bootHasPublicId(): void
    {
        static::creating(function ($model): void {
            if (empty($model->public_id)) {
                $model->public_id = (string) Str::ulid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
