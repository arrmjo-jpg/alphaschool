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
        Schema::create('applicants', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            // The applying child's own identity -- restrictOnDelete(),
            // never cascade: a Person must never silently disappear
            // because an Applicant row was removed. Deliberately NOT
            // unique alone: unlike Student (one per Person, ever), a
            // child may legitimately re-apply in a later academic year
            // after an earlier rejection/withdrawal -- a new Applicant
            // row per cycle, mirroring Enrollment's own per-period
            // re-appending rather than Student's single-row rule. The
            // composite unique below prevents two simultaneous
            // applications for the same child in the same cycle.
            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();

            $table->foreignId('submitted_by_guardian_id')->constrained('guardians')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();

            // Academic's own tables -- resolved and validated exclusively
            // through AcademicCatalogService (Sprint 4.1), never queried
            // directly by this module.
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('applied_for_grade_level_id')->constrained('grade_levels')->restrictOnDelete();

            // A distinct identifier space from Student Number, per the
            // already-frozen decision (IMPLEMENTATION_PLAYBOOK.md Sprint
            // 4.2) -- generated via NumberGeneratorService, never
            // client-supplied.
            $table->string('application_number')->unique();

            // Sprint 4.2's own enum, deliberately narrower than frozen
            // law's full lifecycle (submitted -> ... ->
            // converted/withdrawn/expired): 'converted' is Sprint 4.3's
            // own transition, 'expired' needs a scheduled-job policy not
            // designed anywhere yet. Widening this column ahead of code
            // that uses the wider values would be building for a
            // hypothetical, not completing an existing decision.
            $table->string('status')->default('submitted');

            $table->foreignId('rejection_reason_code_id')->nullable()->constrained('reason_codes')->nullOnDelete();

            $table->timestamps();

            // Context aggregate (frozen People-substrate table), same
            // category as Student/Employee -- softDeletes(), not
            // is_active, matching Student's own precedent.
            $table->softDeletes();

            $table->index('status');
            $table->index(['branch_id', 'academic_year_id', 'status']);
            $table->unique(['person_id', 'academic_year_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applicants');
    }
};
