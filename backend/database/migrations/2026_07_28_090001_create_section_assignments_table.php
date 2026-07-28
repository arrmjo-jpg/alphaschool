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
        Schema::create('section_assignments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            // Anchored on Enrollment, not Student directly (Phase 5
            // Architecture Pass) -- DOMAIN_BLUEPRINT.md §4 already calls
            // this "Enrollment's own child entity." restrictOnDelete() on
            // both, matching guardian_student's own safety-critical-join
            // convention -- neither Enrollment nor Section may silently
            // disappear out from under an assignment history row.
            $table->foreignId('enrollment_id')->constrained('enrollments')->restrictOnDelete();
            $table->foreignId('section_id')->constrained('sections')->restrictOnDelete();

            // HasTemporalAssignment's required columns (App\Core\Concerns\
            // HasTemporalAssignment) -- identical shape to guardian_student.
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->string('status')->default('active');
            $table->foreignId('reason_code_id')->nullable()->constrained('reason_codes')->restrictOnDelete();
            $table->foreignId('ended_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // guardAgainstOverlap() queries by enrollment_id (this
            // model's own temporalScopeAttributes()) on every save() --
            // matches guardian_student's own composite-index convention
            // for the same reason.
            $table->index(['enrollment_id', 'effective_from']);
            $table->index('status');
            $table->index('effective_until');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('section_assignments');
    }
};
