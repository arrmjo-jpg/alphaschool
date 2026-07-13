<?php

namespace App\Modules\IdentityMaintenance\Events;

use App\Modules\IdentityMaintenance\Models\MergeRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MergeDryRunFailed
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  string[]  $conflicts
     */
    public function __construct(public readonly MergeRequest $mergeRequest, public readonly array $conflicts) {}
}
