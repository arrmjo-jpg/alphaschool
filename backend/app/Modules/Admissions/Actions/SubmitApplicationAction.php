<?php

namespace App\Modules\Admissions\Actions;

use App\Core\Services\NumberGeneratorService;
use App\Core\ValueObjects\IdentityDocumentReference;
use App\Core\ValueObjects\PersonName;
use App\Modules\Academic\Services\AcademicCatalogService;
use App\Modules\Admissions\Events\ApplicationSubmitted;
use App\Modules\Admissions\Exceptions\GradeLevelNotOfferedAtBranchException;
use App\Modules\Admissions\Models\Applicant;
use App\Modules\Admissions\Services\GuardianVerificationService;
use App\Modules\Identity\Models\User;
use App\Modules\People\Models\Contact;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates Sprint 4.2's own approved flow end to end: root-of-trust/
 * step-up (GuardianVerificationService) -> Academic Year open gate
 * (AcademicCatalogService, Sprint 4.1) -> Grade-Level-offered-at-Branch
 * check (AcademicCatalogService) -> Application Number
 * (NumberGeneratorService) -> Applicant creation -> ApplicationSubmitted.
 */
class SubmitApplicationAction
{
    public function __construct(
        private readonly GuardianVerificationService $guardianVerificationService,
        private readonly AcademicCatalogService $academicCatalogService,
        private readonly NumberGeneratorService $numberGeneratorService,
    ) {}

    /**
     * @param  IdentityDocumentReference[]  $guardianIdentityDocuments  Required only if this is the guardian's first application (root-of-trust).
     * @param  IdentityDocumentReference[]  $applicantIdentityDocuments
     */
    public function execute(
        User $guardianUser,
        Contact $guardianContact,
        string $stepUpChallengeId,
        string $stepUpCode,
        PersonName $applicantName,
        ?string $applicantDob,
        ?string $applicantNationality,
        string $applicantGender,
        int $branchId,
        string $academicYearPublicId,
        int $gradeLevelId,
        array $guardianIdentityDocuments = [],
        array $applicantIdentityDocuments = [],
    ): Applicant {
        // Step-up verification only reads/clears a cache entry (no DB
        // write) -- deliberately kept outside the transaction so a bad
        // OTP fails fast without ever opening one.
        $this->guardianVerificationService->requireStepUp($guardianUser, $guardianContact, $stepUpChallengeId, $stepUpCode);

        // Independent Review fix: everything from here on writes to the
        // database (Guardian/PersonIdentityDocument creation included)
        // and must succeed or fail as one unit -- previously
        // resolveGuardian() ran outside this transaction, so a guardian
        // and their identity documents could be permanently committed
        // even when the Academic Year/Grade Level check that follows
        // rejected the submission, leaving an orphaned Guardian with no
        // Applicant. The Academic Year/Grade Level checks are pure
        // reads, so including them inside the transaction is harmless.
        return DB::transaction(function () use (
            $guardianUser, $guardianIdentityDocuments, $branchId, $academicYearPublicId, $gradeLevelId,
            $applicantName, $applicantDob, $applicantNationality, $applicantGender, $applicantIdentityDocuments,
        ): Applicant {
            $guardian = $this->guardianVerificationService->resolveGuardian($guardianUser, $guardianIdentityDocuments);

            $academicYear = $this->academicCatalogService->findAcademicYear($academicYearPublicId);
            $this->academicCatalogService->assertAcademicYearIsOpen($academicYear->id);

            $offeredGradeLevels = $this->academicCatalogService->gradeLevelsForBranch($branchId);
            if (! $offeredGradeLevels->contains('id', $gradeLevelId)) {
                throw new GradeLevelNotOfferedAtBranchException($gradeLevelId, $branchId);
            }

            $person = $this->guardianVerificationService->resolveApplicantPerson(
                $applicantName, $applicantDob, $applicantNationality, $applicantGender, $applicantIdentityDocuments,
            );

            $applicationNumber = $this->numberGeneratorService->next('application');

            $applicant = Applicant::create([
                'person_id' => $person->id,
                'submitted_by_guardian_id' => $guardian->id,
                'branch_id' => $branchId,
                'academic_year_id' => $academicYear->id,
                'applied_for_grade_level_id' => $gradeLevelId,
                'application_number' => $applicationNumber,
                'status' => Applicant::STATUS_SUBMITTED,
            ]);

            ApplicationSubmitted::dispatch($applicant);

            return $applicant;
        });
    }
}
