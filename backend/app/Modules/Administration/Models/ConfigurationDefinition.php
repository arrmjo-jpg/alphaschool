<?php

namespace App\Modules\Administration\Models;

use App\Core\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The Configuration Registry's schema-declaration row -- one per key
 * declared by a module's DeclaresSettingsSchema manifest
 * (docs/adr/0018-configuration-platform-resolution-and-metadata.md).
 * Written only by App\Modules\Administration\Services\ConfigurationRegistry
 * at deploy-time sync, never by an ad hoc create() elsewhere.
 *
 * Full-diff audit (LogsActivity, not the cheap who/when tier) is
 * deliberate -- ADR-0018 Decision 2 makes Configuration audit
 * unconditional and rejected an optional toggle. Contrast with
 * App\Modules\Administration\Models\ProviderCredential (Phase 2),
 * which will use the cheap tier per ADR-0019 Decision 5 -- the two
 * models are audited differently on purpose, not by oversight.
 */
class ConfigurationDefinition extends Model
{
    use HasFactory;
    use HasPublicId;
    use LogsActivity;

    protected $fillable = [
        'key', 'type', 'translatable_category', 'default_value', 'required',
        'eligible_altitudes', 'versioned', 'owning_module', 'capability',
        'data_classification', 'approval_required', 'approval_permission',
        'required_permission_to_view', 'required_permission_to_edit',
        'restart_required', 'cache_ttl_seconds', 'requires', 'validation_rules',
        'migration_strategy', 'deprecation_status', 'heuristic_acknowledgment',
    ];

    protected function casts(): array
    {
        return [
            'default_value' => 'array',
            'required' => 'boolean',
            'eligible_altitudes' => 'array',
            'versioned' => 'boolean',
            'approval_required' => 'boolean',
            'restart_required' => 'boolean',
            'requires' => 'array',
            'validation_rules' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
