<?php

namespace App\Modules\Admissions\Actions;

use App\Modules\Admissions\Models\AdmissionAssessment;
use App\Modules\Admissions\Models\Applicant;

/**
 * Records one AdmissionAssessment result and moves the Applicant into
 * `under_review` on its first assessment (if still `submitted`), then
 * to `tested` once this assessment is marked complete -- Sprint 4.2's
 * own approved status machine, nothing beyond it.
 */
class RecordAssessmentResultAction
{
    public function execute(Applicant $applicant, float $score, ?string $notes = null): AdmissionAssessment
    {
        $applicant->moveToReview();

        $assessment = $applicant->assessments()->create([
            'scheduled_at' => now(),
            'completed_at' => now(),
            'score' => $score,
            'notes' => $notes,
        ]);

        $applicant->markTested();

        return $assessment;
    }
}
