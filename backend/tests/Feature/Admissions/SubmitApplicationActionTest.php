<?php

use App\Core\ValueObjects\IdentityDocumentReference;
use App\Core\ValueObjects\PersonName;
use App\Modules\Academic\Exceptions\ClosedAcademicYearException;
use App\Modules\Academic\Models\AcademicYear;
use App\Modules\Academic\Models\BranchGradeLevel;
use App\Modules\Academic\Models\GradeLevel;
use App\Modules\Admissions\Actions\SubmitApplicationAction;
use App\Modules\Admissions\Exceptions\GradeLevelNotOfferedAtBranchException;
use App\Modules\Admissions\Exceptions\RootOfTrustDocumentRequiredException;
use App\Modules\Admissions\Exceptions\StepUpVerificationFailedException;
use App\Modules\Admissions\Models\Applicant;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\StepUpAuthenticationService;
use App\Modules\Identity\Support\IdentityOtpSettings;
use App\Modules\People\Models\Contact;
use App\Modules\People\Models\Guardian;
use App\Modules\People\Models\Person;
use App\Modules\People\Models\PersonIdentityDocument;

// Administration Platform Phase 1 retrofit: identity.otp.* must be
// registered before StepUpAuthenticationService::challenge() can
// resolve its two Configuration values -- same setup
// tests/Feature/Identity/StepUpAuthenticationServiceTest.php already
// requires.
beforeEach(fn () => registerConfigurationSchemas([IdentityOtpSettings::class]));

function guardianContact(User $user): Contact
{
    return Contact::create([
        'person_id' => $user->person_id,
        'type' => Contact::TYPE_PHONE,
        'value' => '+962700000001',
        'verified_at' => now(),
    ]);
}

function stepUpCodeFor(User $user, Contact $contact): array
{
    $service = app(StepUpAuthenticationService::class);
    $challengeId = $service->challenge($user, $contact);
    $code = cache()->get('step_up_challenge:'.$challengeId)['code'];

    return [$challengeId, $code];
}

function openAcademicYearWithGradeLevelAtBranch(): array
{
    $branch = Branch::factory()->create();
    $academicYear = AcademicYear::factory()->active()->create();
    $gradeLevel = GradeLevel::factory()->create();
    BranchGradeLevel::factory()->create(['branch_id' => $branch->id, 'grade_level_id' => $gradeLevel->id]);

    return [$branch, $academicYear, $gradeLevel];
}

/**
 * The Playbook's own named requirement: first-time and returning-
 * guardian paths must be two explicit, separate tests, since conflating
 * them was exactly the risk named in the original Admissions session.
 */
it('submits an application for a brand-new guardian, triggering the full root-of-trust document check', function () {
    [$branch, $academicYear, $gradeLevel] = openAcademicYearWithGradeLevelAtBranch();

    $guardianUser = User::factory()->create();
    $contact = guardianContact($guardianUser);
    [$challengeId, $code] = stepUpCodeFor($guardianUser, $contact);

    expect(Guardian::where('person_id', $guardianUser->person_id)->exists())->toBeFalse();

    $applicant = app(SubmitApplicationAction::class)->execute(
        guardianUser: $guardianUser,
        guardianContact: $contact,
        stepUpChallengeId: $challengeId,
        stepUpCode: $code,
        applicantName: new PersonName(firstNameEn: 'Layla', familyNameEn: 'Hassan', firstNameAr: 'ليلى', familyNameAr: 'حسن'),
        applicantDob: '2018-01-01',
        applicantNationality: 'JO',
        applicantGender: Person::GENDER_FEMALE,
        branchId: $branch->id,
        academicYearPublicId: $academicYear->public_id,
        gradeLevelId: $gradeLevel->id,
        guardianIdentityDocuments: [new IdentityDocumentReference('passport', 'JO', 'P1234567')],
    );

    expect($applicant->status)->toBe(Applicant::STATUS_SUBMITTED);
    expect(Guardian::where('person_id', $guardianUser->person_id)->exists())->toBeTrue();
});

it('submits a second application for a returning guardian, reusing their existing Guardian record without re-verification', function () {
    [$branch, $academicYear, $gradeLevel] = openAcademicYearWithGradeLevelAtBranch();

    $guardianUser = User::factory()->create();
    $existingGuardian = Guardian::factory()->create(['person_id' => $guardianUser->person_id]);
    $contact = guardianContact($guardianUser);
    [$challengeId, $code] = stepUpCodeFor($guardianUser, $contact);

    // No identity documents supplied -- must not be required for a
    // returning guardian.
    $applicant = app(SubmitApplicationAction::class)->execute(
        guardianUser: $guardianUser,
        guardianContact: $contact,
        stepUpChallengeId: $challengeId,
        stepUpCode: $code,
        applicantName: new PersonName(firstNameEn: 'Omar', familyNameEn: 'Hassan', firstNameAr: 'عمر', familyNameAr: 'حسن'),
        applicantDob: '2019-03-01',
        applicantNationality: 'JO',
        applicantGender: Person::GENDER_MALE,
        branchId: $branch->id,
        academicYearPublicId: $academicYear->public_id,
        gradeLevelId: $gradeLevel->id,
    );

    expect($applicant->submitted_by_guardian_id)->toBe($existingGuardian->id);
    expect(Guardian::count())->toBe(1);
});

it('rejects a brand-new guardian submission with no identity document supplied', function () {
    [$branch, $academicYear, $gradeLevel] = openAcademicYearWithGradeLevelAtBranch();

    $guardianUser = User::factory()->create();
    $contact = guardianContact($guardianUser);
    [$challengeId, $code] = stepUpCodeFor($guardianUser, $contact);

    expect(fn () => app(SubmitApplicationAction::class)->execute(
        guardianUser: $guardianUser,
        guardianContact: $contact,
        stepUpChallengeId: $challengeId,
        stepUpCode: $code,
        applicantName: new PersonName(firstNameEn: 'Sara', familyNameEn: 'Ali', firstNameAr: 'سارة', familyNameAr: 'علي'),
        applicantDob: '2018-06-01',
        applicantNationality: 'JO',
        applicantGender: Person::GENDER_FEMALE,
        branchId: $branch->id,
        academicYearPublicId: $academicYear->public_id,
        gradeLevelId: $gradeLevel->id,
    ))->toThrow(RootOfTrustDocumentRequiredException::class);
});

it('rejects submission without a valid step-up code', function () {
    [$branch, $academicYear, $gradeLevel] = openAcademicYearWithGradeLevelAtBranch();

    $guardianUser = User::factory()->create();
    Guardian::factory()->create(['person_id' => $guardianUser->person_id]);
    $contact = guardianContact($guardianUser);
    [$challengeId] = stepUpCodeFor($guardianUser, $contact);

    expect(fn () => app(SubmitApplicationAction::class)->execute(
        guardianUser: $guardianUser,
        guardianContact: $contact,
        stepUpChallengeId: $challengeId,
        stepUpCode: 'wrong-code',
        applicantName: new PersonName(firstNameEn: 'Zaid', familyNameEn: 'Ali', firstNameAr: 'زيد', familyNameAr: 'علي'),
        applicantDob: '2018-06-01',
        applicantNationality: 'JO',
        applicantGender: Person::GENDER_MALE,
        branchId: $branch->id,
        academicYearPublicId: $academicYear->public_id,
        gradeLevelId: $gradeLevel->id,
    ))->toThrow(StepUpVerificationFailedException::class);
});

it('rejects submission against a closed Academic Year, reusing Sprint 4.1\'s guard', function () {
    $branch = Branch::factory()->create();
    $academicYear = AcademicYear::factory()->closed()->create();
    $gradeLevel = GradeLevel::factory()->create();
    BranchGradeLevel::factory()->create(['branch_id' => $branch->id, 'grade_level_id' => $gradeLevel->id]);

    $guardianUser = User::factory()->create();
    Guardian::factory()->create(['person_id' => $guardianUser->person_id]);
    $contact = guardianContact($guardianUser);
    [$challengeId, $code] = stepUpCodeFor($guardianUser, $contact);

    expect(fn () => app(SubmitApplicationAction::class)->execute(
        guardianUser: $guardianUser,
        guardianContact: $contact,
        stepUpChallengeId: $challengeId,
        stepUpCode: $code,
        applicantName: new PersonName(firstNameEn: 'Nour', familyNameEn: 'Saleh', firstNameAr: 'نور', familyNameAr: 'صالح'),
        applicantDob: '2018-06-01',
        applicantNationality: 'JO',
        applicantGender: Person::GENDER_FEMALE,
        branchId: $branch->id,
        academicYearPublicId: $academicYear->public_id,
        gradeLevelId: $gradeLevel->id,
    ))->toThrow(ClosedAcademicYearException::class);
});

it('rejects submission for a Grade Level not offered at the chosen Branch', function () {
    $branch = Branch::factory()->create();
    $academicYear = AcademicYear::factory()->active()->create();
    $gradeLevel = GradeLevel::factory()->create();
    // Deliberately no BranchGradeLevel row created.

    $guardianUser = User::factory()->create();
    Guardian::factory()->create(['person_id' => $guardianUser->person_id]);
    $contact = guardianContact($guardianUser);
    [$challengeId, $code] = stepUpCodeFor($guardianUser, $contact);

    expect(fn () => app(SubmitApplicationAction::class)->execute(
        guardianUser: $guardianUser,
        guardianContact: $contact,
        stepUpChallengeId: $challengeId,
        stepUpCode: $code,
        applicantName: new PersonName(firstNameEn: 'Rami', familyNameEn: 'Saleh', firstNameAr: 'رامي', familyNameAr: 'صالح'),
        applicantDob: '2018-06-01',
        applicantNationality: 'JO',
        applicantGender: Person::GENDER_MALE,
        branchId: $branch->id,
        academicYearPublicId: $academicYear->public_id,
        gradeLevelId: $gradeLevel->id,
    ))->toThrow(GradeLevelNotOfferedAtBranchException::class);
});

/**
 * Independent Review regression test -- resolveGuardian() used to run
 * outside the transaction, so a brand-new guardian's Guardian and
 * PersonIdentityDocument rows would stay committed even when a later
 * check (here, Grade Level not offered) rejected the submission.
 */
it('rolls back a brand-new guardian\'s Guardian and PersonIdentityDocument rows when a later check fails', function () {
    $branch = Branch::factory()->create();
    $academicYear = AcademicYear::factory()->active()->create();
    $gradeLevel = GradeLevel::factory()->create();
    // Deliberately no BranchGradeLevel row -- fails after guardian
    // resolution, inside the same transaction that now wraps it.

    $guardianUser = User::factory()->create();
    $contact = guardianContact($guardianUser);
    [$challengeId, $code] = stepUpCodeFor($guardianUser, $contact);

    expect(fn () => app(SubmitApplicationAction::class)->execute(
        guardianUser: $guardianUser,
        guardianContact: $contact,
        stepUpChallengeId: $challengeId,
        stepUpCode: $code,
        applicantName: new PersonName(firstNameEn: 'Huda', familyNameEn: 'Nasser', firstNameAr: 'هدى', familyNameAr: 'ناصر'),
        applicantDob: '2018-06-01',
        applicantNationality: 'JO',
        applicantGender: Person::GENDER_FEMALE,
        branchId: $branch->id,
        academicYearPublicId: $academicYear->public_id,
        gradeLevelId: $gradeLevel->id,
        guardianIdentityDocuments: [new IdentityDocumentReference('passport', 'JO', 'P9999999')],
    ))->toThrow(GradeLevelNotOfferedAtBranchException::class);

    expect(Guardian::where('person_id', $guardianUser->person_id)->exists())->toBeFalse();
    expect(PersonIdentityDocument::where('person_id', $guardianUser->person_id)->exists())->toBeFalse();
});
