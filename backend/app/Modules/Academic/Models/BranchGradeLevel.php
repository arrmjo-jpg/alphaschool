<?php

namespace App\Modules\Academic\Models;

use App\Modules\Identity\Models\Branch;
use Database\Factories\BranchGradeLevelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The join fact "this Branch currently offers this Grade Level" (Sprint
 * 4.1 Technical Specification) -- no business logic of its own, no
 * public_id (an internal join row, never referenced externally by ID).
 */
class BranchGradeLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id', 'grade_level_id', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): BranchGradeLevelFactory
    {
        return BranchGradeLevelFactory::new();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }
}
