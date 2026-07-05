<?php

namespace Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A fixture that deliberately does NOT implement
 * App\Modules\Media\Contracts\HasBranchScopedMedia, proving
 * AlphaSchoolPathGenerator correctly omits the branch segment entirely
 * for global entities rather than writing an empty placeholder folder.
 * Shares the same underlying table as MediaFixture -- both are simple
 * polymorphic "model" sides of the media relation, distinguished only by
 * whether they opt into branch scoping.
 */
class GlobalMediaFixture extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'media_fixtures';
}
