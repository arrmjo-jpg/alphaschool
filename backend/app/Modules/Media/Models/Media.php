<?php

namespace App\Modules\Media\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\MediaCollections\Models\Media as BaseMedia;

/**
 * Extends Spatie's base Media model per docs/DOMAIN_BLUEPRINT.md §12:
 *
 * - SoftDeletes: recoverability via a scheduled purge window, never
 *   Spatie's default hard delete.
 * - LogsActivity: reuses the existing Activitylog audit infrastructure
 *   rather than building a parallel one.
 * - `sensitivity`: a `standard`/`high` classification within the
 *   `private` disk (Addendum B3) -- NOT a fourth disk tier. High-
 *   sensitivity collections (medical, court documents, identity
 *   documents) get mandatory view/download audit logging and a
 *   dedicated Policy class once real consumers exist; the disk/serving
 *   mechanism is identical either way.
 */
class Media extends BaseMedia
{
    use LogsActivity;
    use SoftDeletes;

    public const SENSITIVITY_STANDARD = 'standard';

    public const SENSITIVITY_HIGH = 'high';

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        // BaseMedia declares its own $casts as an old-style property
        // (uuid, manipulations, custom_properties, etc.), not the
        // casts() method -- overriding casts() here would silently
        // discard all of those instead of adding to them. mergeCasts()
        // is Eloquent's own tool for exactly this "extend a parent
        // model's casts without clobbering them" situation.
        $this->mergeCasts(['sensitivity' => 'string']);
    }

    public function isHighSensitivity(): bool
    {
        return $this->sensitivity === self::SENSITIVITY_HIGH;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['collection_name', 'disk', 'sensitivity', 'file_name'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
