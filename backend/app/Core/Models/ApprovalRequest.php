<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApprovalRequest extends Model
{
    protected $fillable = [
        'requestable_type',
        'requestable_id',
        'status',
        'requested_by_id',
        'reason',
        'disallow_requester_as_approver',
        'current_step_number',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'disallow_requester_as_approver' => 'boolean',
            'current_step_number' => 'integer',
            'decided_at' => 'datetime',
        ];
    }

    public function requestable(): MorphTo
    {
        return $this->morphTo();
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalStep::class)->orderBy('step_number');
    }

    public function currentStep(): ?ApprovalStep
    {
        return $this->steps()->where('step_number', $this->current_step_number)->first();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
