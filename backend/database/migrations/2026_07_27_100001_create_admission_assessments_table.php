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
        Schema::create('admission_assessments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            // cascadeOnDelete(), unlike every FK elsewhere in Sprint 4.1/
            // 4.2 -- an AdmissionAssessment has no independent identity
            // outside its Applicant (unlike Enrollment/Employment, which
            // are independent top-level identities per frozen law).
            $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();

            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('completed_at')->nullable();

            // Raw score only -- "passing" is a Configuration-threshold
            // comparison at read time (admissions.md's own "assessment
            // passing threshold"), never a stored derived boolean.
            $table->decimal('score', 5, 2)->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('applicant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admission_assessments');
    }
};
