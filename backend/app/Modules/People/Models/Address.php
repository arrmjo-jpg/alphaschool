<?php

namespace App\Modules\People\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A Person's address (docs/DOMAIN_BLUEPRINT.md §5) -- a child entity,
 * never columns on Person, since a person can have multiple address
 * types (home/pickup/billing).
 */
class Address extends Model
{
    use SoftDeletes;

    public const TYPE_HOME = 'home';

    public const TYPE_PICKUP = 'pickup';

    public const TYPE_BILLING = 'billing';

    protected $fillable = ['person_id', 'type', 'line1', 'line2', 'city', 'country', 'latitude', 'longitude'];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
