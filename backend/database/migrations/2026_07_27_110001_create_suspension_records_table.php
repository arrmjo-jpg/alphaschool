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
        Schema::create('suspension_records', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            // cascadeOnDelete() -- a suspension record has no independent
            // identity outside its Enrollment, the same reasoning already
            // applied to AdmissionAssessment/Applicant (Sprint 4.2).
            $table->foreignId('enrollment_id')->constrained('enrollments')->cascadeOnDelete();

            $table->foreignId('reason_code_id')->constrained('reason_codes')->restrictOnDelete();

            $table->dateTime('suspended_at');
            $table->dateTime('reinstated_at')->nullable();

            $table->timestamps();

            $table->index('enrollment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suspension_records');
    }
};
