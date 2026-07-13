<?php

namespace App\Modules\IdentityMaintenance\Events;

use App\Modules\IdentityMaintenance\Models\MergeRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The event other modules will actually care about long-term (Sprint
 * 3.2's own plan: e.g. Notifications wanting to inform someone,
 * Reporting wanting to update denormalized data, once those modules
 * exist). Dispatched strictly after the execution transaction commits,
 * never mid-transaction (C7's own reasoning: events dispatched mid-write
 * aren't undone by a DB rollback).
 */
class MergeExecuted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly MergeRequest $mergeRequest) {}
}
