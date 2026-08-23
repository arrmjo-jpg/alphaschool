<?php

namespace App\Modules\Admissions\Models;

use App\Core\Concerns\HasPublicId;
use Database\Factories\AdmissionAssessmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A testing/assessment record scoped to one Applicant (Sprint 4.2
 * Technical Specification) -- Interview scheduling is explicitly out of
 * this sprint's scope (admissions.md's own separate Interviews
 * submodule; the approved Playbook stub names only this one entity and
 * a 'tested' status, not an 'interviewed' one).
 */
class AdmissionAssessment extends Model
{
    use HasFactory;
    use HasPublicId;

    protected $fillable = [
        'applicant_id', 'scheduled_at', 'completed_at', 'score', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
            'score' => 'decimal:2',
        ];
    }

    protected static function newFactory(): AdmissionAssessmentFactory
    {
        return AdmissionAssessmentFactory::new();
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }
}
