<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The row App\Core\Services\NumberGeneratorService locks and increments.
 * See that class for the concurrency-safety mechanics -- this model is
 * intentionally a thin Eloquent wrapper with no business logic of its own.
 */
class NumberSequence extends Model
{
    protected $fillable = [
        'code',
        'scope_type',
        'scope_id',
        'format_pattern',
        'padding_length',
        'reset_period',
        'period_key',
        'current_value',
        'is_gapless',
    ];

    protected function casts(): array
    {
        return [
            'current_value' => 'integer',
            'padding_length' => 'integer',
            'is_gapless' => 'boolean',
        ];
    }
}
