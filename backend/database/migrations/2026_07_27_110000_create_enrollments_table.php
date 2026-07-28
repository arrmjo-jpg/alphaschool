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
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            // Independent top-level identity, not a Student child, per
            // frozen docs/DOMAIN_BLUEPRINT.md -- Attendance/Grades/Fees/
            // Behavior/Report Cards/Learning Participation all reference
            // this record, never Student directly, for exactly this
            // reason. restrictOnDelete(): a Student must never silently
            // disappear because an Enrollment was removed.
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();

            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('grade_level_id')->constrained('grade_levels')->restrictOnDelete();

            // Sprint 4.3's own full value set, built complete now per
            // explicit architectural decision -- Sprint 4.4 adds the
            // ACTIONS that reach promoted/repeated/transferred/withdrawn/
            // graduated, not new values. Suspension is deliberately not
            // a status value here -- it's a sub-status tracked in
            // suspension_records, on the SAME Enrollment, never a new one.
            $table->string('status')->default('active');

            // The chain Sprint 4.4's promotion/repetition/transfer
            // workflows will populate -- self-referential, nullable,
            // built now so the aggregate is structurally complete on
            // first creation, per explicit instruction.
            $table->foreignId('previous_enrollment_id')->nullable()->constrained('enrollments')->nullOnDelete();
            $table->foreignId('next_enrollment_id')->nullable()->constrained('enrollments')->nullOnDelete();

            $table->timestamps();

            $table->index('student_id');
            $table->index(['branch_id', 'academic_year_id', 'status']);
            $table->unique(['student_id', 'academic_year_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
