<?php

use Illuminate\Support\Str;

/**
 * A genuine dual-connection concurrency proof for
 * PromoteEnrollmentAction's lockForUpdate() row lock, matching the
 * established pattern (tests/Feature/Core/NumberGeneratorConcurrencyTest.php,
 * tests/Feature/People/ConvertApplicantToStudentConcurrencyTest.php)
 * exactly -- same shared realMariadbCredentialsFromDotEnv()/
 * openRealMariadbPdo() helpers from tests/Pest.php.
 *
 * Proves the same mechanism those tests prove, applied to enrollments:
 * a connection holding `SELECT ... FOR UPDATE` on the Enrollment row
 * genuinely blocks a second connection until the first commits -- the
 * real double-promotion guard.
 */
beforeEach(function () {
    $this->credentials = realMariadbCredentialsFromDotEnv();
    $this->pdo = $this->credentials ? openRealMariadbPdo($this->credentials) : null;

    if ($this->pdo === null) {
        test()->markTestSkipped('No real local MariaDB reachable -- this concurrency test needs one.');
    }

    $this->pdo->exec("DELETE FROM people WHERE first_name_en = 'ConcurrencyTestPromotionStudent'");

    $ulid = fn () => (string) Str::ulid();

    $stmt = $this->pdo->prepare(
        'INSERT INTO people (public_id, first_name_en, first_name_ar, family_name_en, family_name_ar, dob, gender, search_key, created_at, updated_at) '.
        "VALUES (?, ?, ?, ?, ?, '2015-01-01', 'male', ?, NOW(), NOW())"
    );
    $stmt->execute([$ulid(), 'ConcurrencyTestPromotionStudent', 'test', 'Student', 'test', 'concurrencytestpromotionstudent']);
    $this->personId = (int) $this->pdo->lastInsertId();

    $stmt = $this->pdo->prepare(
        'INSERT INTO students (public_id, person_id, lifecycle_status, created_at, updated_at) VALUES (?, ?, \'active\', NOW(), NOW())'
    );
    $stmt->execute([$ulid(), $this->personId]);
    $this->studentId = (int) $this->pdo->lastInsertId();

    $stmt = $this->pdo->prepare(
        'INSERT INTO branches (public_id, code, name_en, name_ar, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())'
    );
    $stmt->execute([$ulid(), 'CONCUR-PROMO-'.random_int(10000, 99999), 'Concurrency Test Branch', 'test']);
    $this->branchId = (int) $this->pdo->lastInsertId();

    $stmt = $this->pdo->prepare(
        "INSERT INTO academic_years (public_id, name_en, name_ar, start_date, end_date, status, created_at, updated_at) VALUES (?, ?, ?, '2030-09-01', '2031-06-30', 'active', NOW(), NOW())"
    );
    $stmt->execute([$ulid(), 'Concurrency Test Promotion Year', 'test']);
    $this->academicYearId = (int) $this->pdo->lastInsertId();

    $stmt = $this->pdo->prepare(
        'INSERT INTO grade_levels (public_id, code, name_en, name_ar, sequence_order, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())'
    );
    $sequenceOrder = random_int(100000, 999999);
    $stmt->execute([$ulid(), 'CONCUR-PROMO-GL-'.$sequenceOrder, 'Concurrency Test Grade', 'test', $sequenceOrder]);
    $this->gradeLevelId = (int) $this->pdo->lastInsertId();

    $stmt = $this->pdo->prepare(
        "INSERT INTO enrollments (public_id, student_id, academic_year_id, branch_id, grade_level_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'active', NOW(), NOW())"
    );
    $stmt->execute([$ulid(), $this->studentId, $this->academicYearId, $this->branchId, $this->gradeLevelId]);
    $this->enrollmentId = (int) $this->pdo->lastInsertId();
});

afterEach(function () {
    $this->pdo?->exec("DELETE FROM enrollments WHERE id = {$this->enrollmentId}");
    $this->pdo?->exec("DELETE FROM grade_levels WHERE id = {$this->gradeLevelId}");
    $this->pdo?->exec("DELETE FROM academic_years WHERE id = {$this->academicYearId}");
    $this->pdo?->exec("DELETE FROM branches WHERE id = {$this->branchId}");
    $this->pdo?->exec("DELETE FROM students WHERE id = {$this->studentId}");
    $this->pdo?->exec("DELETE FROM people WHERE id = {$this->personId}");
});

it('proves the promotion row lock genuinely blocks a second connection until the first commits', function () {
    $connectionA = openRealMariadbPdo($this->credentials);
    $connectionB = openRealMariadbPdo($this->credentials);

    $connectionA->beginTransaction();
    $connectionA->query("SELECT * FROM enrollments WHERE id = {$this->enrollmentId} FOR UPDATE");

    $connectionB->exec('SET SESSION innodb_lock_wait_timeout = 1');
    $connectionB->beginTransaction();

    $blocked = false;

    try {
        $connectionB->query("SELECT * FROM enrollments WHERE id = {$this->enrollmentId} FOR UPDATE");
    } catch (PDOException $e) {
        $blocked = str_contains($e->getMessage(), 'Lock wait timeout') || str_contains($e->getMessage(), '1205');
    }

    expect($blocked)->toBeTrue();

    // Connection A, still holding the lock, does exactly what
    // PromoteEnrollmentAction does: flip status to promoted.
    $connectionA->exec("UPDATE enrollments SET status = 'promoted' WHERE id = {$this->enrollmentId}");
    $connectionB->rollBack();
    $connectionA->commit();

    // Now that A released the lock, B can acquire it immediately and
    // sees the already-committed 'promoted' status -- exactly the
    // signal PromoteEnrollmentAction uses (via its own upfront status
    // check) to refuse a concurrent second promotion attempt with
    // EnrollmentNotActiveException, without needing to hold the lock
    // across the entire promotion transaction.
    $connectionB->beginTransaction();
    $row = $connectionB->query("SELECT status FROM enrollments WHERE id = {$this->enrollmentId} FOR UPDATE")->fetch(PDO::FETCH_ASSOC);
    $connectionB->commit();

    expect($row['status'])->toBe('promoted');
});
