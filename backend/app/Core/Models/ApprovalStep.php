<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalStep extends Model
{
    protected $fillable = [
        'approval_request_id',
        'step_number',
        'required_role',
        'required_user_id',
        'status',
        'decided_by_id',
        'decided_at',
        'comments',
    ];

    protected function casts(): array
    {
        return [
            'step_number' => 'integer',
            'decided_at' => 'datetime',
        ];
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }
}
