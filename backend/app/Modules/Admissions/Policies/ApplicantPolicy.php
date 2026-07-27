<?php

namespace App\Modules\Admissions\Policies;

use App\Modules\Admissions\Models\Applicant;
use App\Modules\Identity\Models\User;

/**
 * Directly from admissions.md's own already-stated Permissions, not
 * invented: "Admissions Manager -- full," "Admissions Officer -- operate
 * the funnel, no policy changes," "Interviewer -- read applicant info,
 * submit interview scores only." Three permissions, not one -- mirrors
 * AcademicYearPolicy's own precedent (Sprint 4.1) of seeding real
 * vocabulary without pre-assigning it to a role shell, since Admissions'
 * own personas aren't formally decided anywhere either.
 */
class ApplicantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('admissions.manage-applications', 'sanctum')
            || $user->hasPermissionTo('admissions.submit-assessment-score', 'sanctum');
    }

    public function update(User $user, Applicant $applicant): bool
    {
        return $user->hasPermissionTo('admissions.manage-applications', 'sanctum');
    }

    public function decide(User $user, Applicant $applicant): bool
    {
        return $user->hasPermissionTo('admissions.manage-applications', 'sanctum');
    }

    public function managePolicy(User $user): bool
    {
        return $user->hasPermissionTo('admissions.manage-policy', 'sanctum');
    }

    /**
     * Scores only, per admissions.md's own "submit interview scores
     * only" -- an Interviewer's grant is deliberately narrower than
     * Admissions Officer's general update ability.
     */
    public function recordAssessmentResult(User $user, Applicant $applicant): bool
    {
        return $user->hasPermissionTo('admissions.manage-applications', 'sanctum')
            || $user->hasPermissionTo('admissions.submit-assessment-score', 'sanctum');
    }
}
