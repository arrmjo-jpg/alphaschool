<?php

namespace Tests\Fixtures;

use App\Modules\Media\Contracts\HasBranchScopedMedia;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Test-only fixture exercising the Media Architecture skeleton (disk
 * tiers, path generator, branch scoping) in isolation, since no real
 * business model attaches media yet -- the first real consumer (Student
 * photos) arrives with People in Phase 2. Table is created ad hoc in the
 * test itself, not via a real migration.
 */
class MediaFixture extends Model implements HasBranchScopedMedia, HasMedia
{
    use InteractsWithMedia;

    protected $table = 'media_fixtures';

    protected $fillable = ['branch_id'];

    public function mediaPathBranchId(): ?int
    {
        return $this->branch_id;
    }
}
