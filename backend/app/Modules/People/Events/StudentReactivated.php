<?php

namespace App\Modules\People\Events;

use App\Modules\People\Models\Student;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched from Student::reactivate() -- afterCommit(), per Sprint
 * 4.2's own disclosed debt item on ApplicationSubmitted, not repeating
 * the same gap a second time. No subscribers yet.
 */
class StudentReactivated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Student $student) {}
}
