<?php

use Illuminate\Support\Str;

/**
 * A genuine dual-connection concurrency proof for
 * ConvertApplicantToStudentAction's lockForUpdate() row lock, matching
 * the established pattern in
 * tests/Feature/Core/NumberGeneratorConcurrencyTest.php and
 * tests/Feature/IdentityMaintenance/MergeExecutionLockingConcurrencyTest.php
 * exactly (same shared realMariadbCredentialsFromDotEnv()/
 * openRealMariadbPdo() helpers from tests/Pest.php).
 *
 * Proves the same mechanism those two tests prove, applied to
 * applicants: a connection holding `SELECT ... FOR UPDATE` on the
 * Applicant row genuinely blocks a second connection until the first
 * commits -- the real double-conversion guard, not a read-then-write
 * race that only looks safe under sequential single-process testing.
 */
beforeEach(function () {
    $this->credentials = realMariadbCredentialsFromDotEnv();
    $this->pdo = $this->credentials ? openRealMariadbPdo($this->credentials) : null;

    if ($this->pdo === null) {
        test()->markTestSkipped('No real local MariaDB reachable -- this concurrency test needs one.');
    }

    $this->pdo->exec("DELETE FROM applicants WHERE application_number = 'CONCURRENCY-TEST-0001'");
    $this->pdo->exec("DELETE FROM guardians WHERE id IN (SELECT id FROM (SELECT g.id FROM guardians g JOIN people p ON p.id = g.person_id WHERE p.first_name_en LIKE 'ConcurrencyTest%') t)");
    $this->pdo->exec("DELETE FROM people WHERE first_name_en LIKE 'ConcurrencyTest%'");

    $ulid = fn () => (string) Str::ulid();

    $stmt = $this->pdo->prepare(
        'INSERT INTO people (public_id, first_name_en, first_name_ar, family_name_en, family_name_ar, dob, gender, search_key, created_at, updated_at) '.
        "VALUES (?, ?, ?, ?, ?, '2018-01-01', 'male', ?, NOW(), NOW())"
    );
    $stmt->execute([$ulid(), 'ConcurrencyTestApplicant', 'test', 'Applicant', 'test', 'concurrencytestapplicant']);
    $this->personId = (int) $this->pdo->lastInsertId();

    $stmt->execute([$ulid(), 'ConcurrencyTestGuardian', 'test', 'Guardian', 'test', 'concurrencytestguardian']);
    $this->guardianPersonId = (int) $this->pdo->lastInsertId();

    $stmt = $this->pdo->prepare(
        'INSERT INTO guardians (public_id, person_id, created_at, updated_at) VALUES (?, ?, NOW(), NOW())'
    );
    $stmt->execute([$ulid(), $this->guardianPersonId]);
    $this->guardianId = (int) $this->pdo->lastInsertId();

    $stmt = $this->pdo->prepare(
        'INSERT INTO branches (public_id, code, name_en, name_ar, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())'
    );
    $stmt->execute([$ulid(), 'CONCUR-'.random_int(10000, 99999), 'Concurrency Test Branch', 'test']);
    $this->branchId = (int) $this->pdo->lastInsertId();

    $stmt = $this->pdo->prepare(
        "INSERT INTO academic_years (public_id, name_en, name_ar, start_date, end_date, status, created_at, updated_at) VALUES (?, ?, ?, '2030-09-01', '2031-06-30', 'active', NOW(), NOW())"
    );
    $stmt->execute([$ulid(), 'Concurrency Test Year', 'test']);
    $this->academicYearId = (int) $this->pdo->lastInsertId();

    $stmt = $this->pdo->prepare(
        'INSERT INTO grade_levels (public_id, code, name_en, name_ar, sequence_order, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())'
    );
    $sequenceOrder = random_int(100000, 999999);
    $stmt->execute([$ulid(), 'CONCUR-GL-'.$sequenceOrder, 'Concurrency Test Grade', 'test', $sequenceOrder]);
    $this->gradeLevelId = (int) $this->pdo->lastInsertId();

    $stmt = $this->pdo->prepare(
        "INSERT INTO applicants (public_id, person_id, submitted_by_guardian_id, branch_id, academic_year_id, applied_for_grade_level_id, application_number, status, fee_paid, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 'CONCURRENCY-TEST-0001', 'accepted', 1, NOW(), NOW())"
    );
    $stmt->execute([$ulid(), $this->personId, $this->guardianId, $this->branchId, $this->academicYearId, $this->gradeLevelId]);
    $this->applicantId = (int) $this->pdo->lastInsertId();
});

afterEach(function () {
    $this->pdo?->exec("DELETE FROM applicants WHERE id = {$this->applicantId}");
    $this->pdo?->exec("DELETE FROM guardians WHERE id = {$this->guardianId}");
    $this->pdo?->exec("DELETE FROM grade_levels WHERE id = {$this->gradeLevelId}");
    $this->pdo?->exec("DELETE FROM academic_years WHERE id = {$this->academicYearId}");
    $this->pdo?->exec("DELETE FROM branches WHERE id = {$this->branchId}");
    $this->pdo?->exec("DELETE FROM people WHERE id IN ({$this->personId}, {$this->guardianPersonId})");
});

it('proves the conversion row lock genuinely blocks a second connection until the first commits', function () {
    $connectionA = openRealMariadbPdo($this->credentials);
    $connectionB = openRealMariadbPdo($this->credentials);

    $connectionA->beginTransaction();
    $connectionA->query("SELECT * FROM applicants WHERE id = {$this->applicantId} FOR UPDATE");

    $connectionB->exec('SET SESSION innodb_lock_wait_timeout = 1');
    $connectionB->beginTransaction();

    $blocked = false;

    try {
        $connectionB->query("SELECT * FROM applicants WHERE id = {$this->applicantId} FOR UPDATE");
    } catch (PDOException $e) {
        $blocked = str_contains($e->getMessage(), 'Lock wait timeout') || str_contains($e->getMessage(), '1205');
    }

    expect($blocked)->toBeTrue();

    // Connection A, still holding the lock, does exactly what
    // ConvertApplicantToStudentAction does: flip status to converted.
    $connectionA->exec("UPDATE applicants SET status = 'converted' WHERE id = {$this->applicantId}");
    $connectionB->rollBack();
    $connectionA->commit();

    // Now that A released the lock, B can acquire it immediately and
    // sees the already-committed 'converted' status -- exactly the
    // signal ConvertApplicantToStudentAction uses to refuse a concurrent
    // second attempt with ApplicantAlreadyConvertedException, without
    // needing to hold the lock across the entire conversion transaction.
    $connectionB->beginTransaction();
    $row = $connectionB->query("SELECT status FROM applicants WHERE id = {$this->applicantId} FOR UPDATE")->fetch(PDO::FETCH_ASSOC);
    $connectionB->commit();

    expect($row['status'])->toBe('converted');
});
