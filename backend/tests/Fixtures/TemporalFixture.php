<?php

namespace Tests\Fixtures;

use App\Core\Concerns\HasTemporalAssignment;
use Illuminate\Database\Eloquent\Model;

/**
 * Test-only fixture exercising App\Core\Concerns\HasTemporalAssignment in
 * isolation, since no real business model adopts it yet (the first real
 * consumers -- Enrollment, employee_branches, guardian_student -- arrive
 * in later phases per docs/IMPLEMENTATION_PLAYBOOK.md). Table is created
 * ad hoc in the test itself (see HasTemporalAssignmentTest), not via a
 * real migration, so it never touches the production schema.
 */
class TemporalFixture extends Model
{
    use HasTemporalAssignment;

    protected $table = 'temporal_fixtures';

    protected $fillable = [
        'scope_key',
        'effective_from',
        'effective_until',
        'status',
        'reason_code_id',
        'ended_by_id',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    public function temporalScopeAttributes(): array
    {
        return ['scope_key' => $this->scope_key];
    }

    public function temporalReasonContext(): string
    {
        return 'temporal_fixture';
    }
}
