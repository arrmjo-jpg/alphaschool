<?php

namespace App\Modules\Administration\Models;

use App\Core\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The Provider Registry's schema-declaration row -- one per slot declared
 * by a module's DeclaresProviderSlots manifest
 * (docs/adr/0019-integration-platform-architecture.md Decision 1).
 * Written only by App\Modules\Administration\Services\ProviderRegistry
 * at deploy-time sync, never by an ad hoc create() elsewhere -- the
 * identical discipline already proven for ConfigurationDefinition.
 *
 * Full-diff audit is correct here (contrast
 * App\Modules\Administration\Models\ProviderCredential's cheap tier):
 * this row carries no secret, only declaration metadata, the same
 * reasoning ConfigurationDefinition's own docblock already states.
 */
class ProviderRegistration extends Model
{
    use HasFactory;
    use HasPublicId;
    use LogsActivity;

    protected $fillable = [
        'slot_key', 'capability_contract', 'provider_class', 'credential_fields',
        'owning_module', 'required_permission_to_edit', 'approval_required',
        'approval_permission', 'deprecation_status',
    ];

    protected function casts(): array
    {
        return [
            'credential_fields' => 'array',
            'approval_required' => 'boolean',
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
