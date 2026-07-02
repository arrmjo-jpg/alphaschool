<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * The lookup table backing App\Core\ValueObjects\ReasonCode. This is the
 * DB-backed catalog of valid reasons per context; the value object itself
 * stays a pure, DB-free structural check (docs/DOMAIN_BLUEPRINT.md §6).
 *
 * Seeded per-context by whichever module owns that context (e.g. HR seeds
 * the 'employment' context's reasons) -- Core only owns the table shape,
 * never the actual catalog of reasons, since those are domain knowledge.
 */
class ReasonCode extends Model
{
    use HasTranslations;

    public array $translatable = ['label'];

    protected $fillable = [
        'context',
        'code',
        'label',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeForContext($query, string $context)
    {
        return $query->where('context', $context)->where('is_active', true);
    }
}
