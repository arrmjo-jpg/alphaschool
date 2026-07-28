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
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            // Per-year, not a permanent catalog like GradeLevel (Phase 5
            // Architecture Pass, explicit decision) -- "Grade 5 Section A"
            // means a specific roster for one Academic Year; next year's
            // Grade 5 Section A is a different row entirely, never a
            // reused one. restrictOnDelete() on all three, matching
            // Enrollment's own convention -- none of Branch/AcademicYear/
            // GradeLevel may silently disappear out from under a Section
            // that references them.
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('grade_level_id')->constrained('grade_levels')->restrictOnDelete();

            // A single locale-agnostic label (explicit decision, Phase 5
            // Architecture Pass) -- expected values are like "A"/"B"/"1",
            // not content needing translation. No name_en/name_ar unless
            // a real deployment need for localized section names surfaces
            // later.
            $table->string('name');

            // Informational only this sprint (Phase 5 Architecture Pass,
            // explicit decision) -- read for reporting/dashboard use and
            // as a soft signal Admissions/registration may check, but no
            // constraint or exception enforces it here. Hard enforcement
            // is a real business-policy question (is an over-capacity
            // exception ever legitimate?) not yet decided.
            $table->unsignedInteger('capacity')->nullable();

            // Reference/structural entity within its own year -- same
            // "deactivate, never delete" convention as GradeLevel/Branch.
            // No three-state lifecycle like AcademicYear: a Section is
            // already year-scoped by construction, so once its own
            // Academic Year closes, ClosedAcademicYearGuard already blocks
            // new scoped writes against it.
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['branch_id', 'academic_year_id', 'grade_level_id', 'name']);
            $table->index(['branch_id', 'academic_year_id', 'grade_level_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};
