<?php

namespace App\Modules\Media\Support;

use App\Modules\Media\Contracts\HasBranchScopedMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * Implements the path scheme from docs/DOMAIN_BLUEPRINT.md §12:
 * {branch_id}/{model-type}/{model_id}/{collection}/{media_id}-{filename}
 * -- colocating one entity's files (cheap bulk export/erasure), keeping
 * categories visually separated, and spreading writes across many
 * prefixes to avoid storage-backend hot-partitioning from sequential
 * keys.
 *
 * "Tier" from the Blueprint's spec is realized as *which disk* stores the
 * file (public/private/temporary, config/filesystems.php) rather than an
 * additional literal path segment -- each tier already has its own root,
 * so repeating the tier name as a folder inside its own root would just
 * be redundant (e.g. storage/app/public/public/...).
 *
 * The branch segment is present only for models implementing
 * HasBranchScopedMedia and returning a non-null branch ID -- global
 * entities (e.g. a Guardian's media) get no branch folder at all, per
 * that contract's own docblock.
 */
class AlphaSchoolPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return $this->basePath($media).'/'.$media->getKey().'-';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->basePath($media).'/'.$media->getKey().'/conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->basePath($media).'/'.$media->getKey().'/responsive-images/';
    }

    protected function basePath(Media $media): string
    {
        $segments = array_filter([
            $this->branchSegment($media),
            class_basename($media->model_type),
            (string) $media->model_id,
            $media->collection_name,
        ], fn (string $segment) => $segment !== '');

        return implode('/', $segments);
    }

    protected function branchSegment(Media $media): string
    {
        $model = $media->model;

        if (! $model instanceof HasBranchScopedMedia) {
            return '';
        }

        $branchId = $model->mediaPathBranchId();

        return $branchId !== null ? (string) $branchId : '';
    }
}
