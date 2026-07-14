<?php

namespace App\Modules\Administration\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Deliberately not LogsActivity-audited (docs/adr/0018 Decision 4: "a
 * personal preference carries none of the organizational-policy weight
 * a Branch fee-rate override does") -- plain timestamps only. Never
 * routed through ApprovalEngine; never version-checked. If this ever
 * needs richer audit later, that is a real, separately-justified
 * decision, not an oversight being corrected.
 */
class ConfigurationUserPreference extends Model
{
    protected $table = 'configuration_user_preferences';

    protected $fillable = ['user_id', 'configuration_key', 'value'];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }
}
