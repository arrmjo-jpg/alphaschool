<?php

namespace App\Modules\People\Events;

use App\Modules\People\Models\Enrollment;
use App\Modules\People\Models\Student;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Named in IMPLEMENTATION_PLAYBOOK.md's own Sprint 4.3 scope. Dispatched
 * afterCommit() from ConvertApplicantToStudentAction -- closes the
 * disclosed gap Sprint 4.2's own ApplicationSubmitted event left open
 * ("whoever adds the first listener... must use afterCommit()"). No
 * real subscribers yet (Library/Transportation/Notifications don't
 * exist), but the payload shape exists now so those modules only add a
 * listener later.
 */
class StudentEnrolled
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Student $student,
        public readonly Enrollment $enrollment,
    ) {}
}
