<?php

namespace App\Modules\Academic\Exceptions;

use App\Modules\Academic\Models\Term;
use RuntimeException;

/**
 * Raised by AcademicPeriodGuard::assertTermIsOpen() -- Term's own
 * sibling to ClosedAcademicYearException, once Term became the guard's
 * second real consumer (BUS-0032). Carries the term's public_id/name so
 * a caller several layers removed from this guard can diagnose the
 * failure without a second query.
 */
class ClosedTermException extends RuntimeException
{
    public function __construct(Term $term)
    {
        parent::__construct(
            "Term '{$term->name_en}' ({$term->public_id}) is closed and cannot accept new or modified records.",
        );
    }
}
