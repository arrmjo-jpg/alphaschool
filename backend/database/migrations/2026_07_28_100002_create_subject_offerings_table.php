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
        Schema::create('subject_offerings', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            // Subject Offering + Timetables Architecture Pass. No
            // teacher_id -- Teacher Assignment is its own effective-dated
            // aggregate (TeacherAssignment), mirroring
            // Section/HomeroomAssignment's own precedent, never a mutable
            // FK on the offering itself.
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->foreignId('section_id')->constrained('sections')->restrictOnDelete();
            $table->foreignId('term_id')->constrained('terms')->restrictOnDelete();

            // Stored directly, not derived via term.academic_year_id
            // (BUS-0032's own required amendment) -- nearly all real
            // queries/reports filter by year directly, matching
            // Enrollment/Section's own existing sibling-scope-column
            // precedent. Enforced to agree with both Section's and
            // Term's own academic_year_id by the three-way Consistency
            // Invariant in SubjectOffering::booted(), not by a DB
            // constraint (the compared columns span three tables, which
            // an FK/CHECK constraint cannot express -- same reasoning as
            // SectionAssignment's own invariant).
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();

            $table->timestamps();

            // A Subject is offered at most once per (Section, Term) --
            // no duplicate offerings of the same subject to the same
            // section in the same term.
            $table->unique(['subject_id', 'section_id', 'term_id']);
            $table->index('academic_year_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subject_offerings');
    }
};
