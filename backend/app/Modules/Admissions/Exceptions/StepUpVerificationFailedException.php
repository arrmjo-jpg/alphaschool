<?php

namespace App\Modules\Admissions\Exceptions;

use RuntimeException;

/**
 * "Submit application" is a sensitive action requiring step-up
 * authentication (IMPLEMENTATION_PLAYBOOK.md Sprint 4.2 stub) --
 * StepUpAuthentication::verify() returns bool, this exception is what
 * SubmitApplicationAction raises when that bool is false, so the
 * rejection is a real, catchable domain failure rather than a silent
 * no-op.
 */
class StepUpVerificationFailedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Step-up verification failed or the challenge has expired -- application submission was not accepted.');
    }
}
