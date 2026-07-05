<?php

namespace App\Modules\Media\Support;

use App\Modules\Media\Contracts\HasBranchScopedMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * Implements the path scheme from docs/DOMAIN_BLUEPRINT.md §12 and
 * IMPLEMENTATION_PLAYBOOK.md's Sprint 1.2.1 spec, literally:
 * {tier}/{branch_id}/{model-type}/{model_id}/{collection}/{media_id}-{filename}
 * -- colocating one entity's files (cheap bulk export/erasure), keeping
 * categories visually separated, and spreading writes across many
 * prefixes to avoid storage-backend hot-partitioning from sequential
 * keys.
 *
 * "Tier" is the media's own disk name (public/private/temporary), kept as
 * a literal path segment per the frozen spec even though each tier also
 * has its own disk root -- this keeps the physical layout identical
 * regardless of whether a given deployment ever consolidates tiers onto
 * one shared bucket/disk, which the path scheme should not assume against.
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
            $media->disk,
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
