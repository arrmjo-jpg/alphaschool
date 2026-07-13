<?php

namespace App\Modules\IdentityMaintenance\Events;

use App\Modules\IdentityMaintenance\Models\MergeRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MergeRejected
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly MergeRequest $mergeRequest) {}
}
