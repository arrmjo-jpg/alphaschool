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
        Schema::create('teacher_assignments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            // Subject-level Teacher Assignment (Subject Offering +
            // Timetables Architecture Pass, explicit user requirement:
            // reuse the existing Assignment pattern, do NOT merge with
            // Homeroom). Mirrors homeroom_assignments' own shape exactly,
            // scoped to subject_offering_id instead of section_id.
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('subject_offering_id')->constrained('subject_offerings')->restrictOnDelete();

            // HasTemporalAssignment's required columns.
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->string('status')->default('active');
            $table->foreignId('reason_code_id')->nullable()->constrained('reason_codes')->restrictOnDelete();
            $table->foreignId('ended_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // guardAgainstOverlap() queries by subject_offering_id (this
            // model's own temporalScopeAttributes()) -- single-cardinality
            // assumption: one active teacher per SubjectOffering at a
            // time (Subject Offering + Timetables Architecture Pass,
            // explicitly flagged as an assumption for v1, not a
            // permanent constraint on co-teaching).
            $table->index(['subject_offering_id', 'effective_from']);
            $table->index('status');
            $table->index('effective_until');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_assignments');
    }
};
