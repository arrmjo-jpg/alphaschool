<?php

namespace App\Modules\Admissions\Exceptions;

use RuntimeException;

/**
 * A first-time guardian (no existing Guardian row on their own Person)
 * must supply at least one identity document reference before a
 * Guardian context can be created -- the "first-child document check"
 * named in IMPLEMENTATION_PLAYBOOK.md's Sprint 4.2 stub. Document
 * AUTHENTICITY review is Not Yet Defined (no automated verification or
 * reviewer workflow is built here) -- this exception only enforces that
 * a document reference was structurally supplied.
 */
class RootOfTrustDocumentRequiredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('A first-time guardian must supply at least one identity document reference before submitting an application.');
    }
}
