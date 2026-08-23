<?php

namespace App\Modules\People\Events;

use App\Modules\People\Models\Student;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched from Student::withdraw() itself via DB::afterCommit()
 * (Sprint 4.4), matching StudentReactivated's existing discipline.
 */
class StudentWithdrawn
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Student $student) {}
}
