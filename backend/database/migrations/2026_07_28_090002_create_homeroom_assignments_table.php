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
        Schema::create('homeroom_assignments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            // BUS-0019 (Academic Assignment Model) -- Homeroom Teacher
            // assignment is an effective-dated Assignment aggregate, not
            // a mutable section.homeroom_teacher_id field (explicitly
            // rejected there). restrictOnDelete() on both, matching
            // section_assignments' own convention.
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('section_id')->constrained('sections')->restrictOnDelete();

            // HasTemporalAssignment's required columns.
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->string('status')->default('active');
            $table->foreignId('reason_code_id')->nullable()->constrained('reason_codes')->restrictOnDelete();
            $table->foreignId('ended_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // guardAgainstOverlap() queries by section_id (this model's
            // own temporalScopeAttributes()) -- "two teachers cannot both
            // be the active homeroom teacher of the same section at
            // once," the trait's own worked example.
            $table->index(['section_id', 'effective_from']);
            $table->index('status');
            $table->index('effective_until');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('homeroom_assignments');
    }
};
