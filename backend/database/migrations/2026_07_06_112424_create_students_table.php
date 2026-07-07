<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            // The permanent identity anchor (docs/DOMAIN_BLUEPRINT.md §3,
            // ADR-0004) -- one Student per Person, ever. A withdrawn
            // student's later re-admission opens a new Enrollment
            // (Phase 4); it never creates a second Student row for the
            // same Person.
            $table->foreignId('person_id')->unique()->constrained('people')->restrictOnDelete();

            // Coarse only -- deliberately not academic_year_id/grade/
            // branch, which ADR-0004 assigns to Enrollment (Phase 4),
            // not Student. No branch_id -- Student is never directly
            // branch-scoped (Addendum B6). No current_enrollment_id --
            // it would reference a table (enrollments) that doesn't
            // exist yet; added alongside Enrollment itself in Phase 4.
            // No student_number -- ADR-0004 lists it as part of
            // Student's eventual shape, but numbering is explicitly out
            // of this sprint's scope (Playbook Scope-OUT) pending the
            // still-open numbering-scheme decision; it arrives via its
            // own additive migration once that decision is confirmed.
            $table->string('lifecycle_status')->default('active');

            $table->timestamps();

            // True soft-delete, not is_active -- Student is an
            // identity-context aggregate (same category as Person/User/
            // Employee), not a reference/structural entity like
            // Branch/Role.
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
