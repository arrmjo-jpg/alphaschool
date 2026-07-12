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
        Schema::create('guardian_student', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            // The safety-critical join between the existing Guardian and
            // Student aggregates (docs/DOMAIN_BLUEPRINT.md §11) -- not a
            // new identity, restrictOnDelete() so a historical
            // relationship can never silently lose what it refers to.
            $table->foreignId('guardian_id')->constrained('guardians')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();

            // relationship_types is reference data with its own three-
            // layer deletion policy (Sprint 2.5 Step 1) -- restrictOnDelete()
            // is this table's half of that database-level backstop.
            $table->foreignId('relationship_type_id')->constrained('relationship_types')->restrictOnDelete();

            $table->boolean('is_primary_contact')->default(false);
            $table->boolean('is_pickup_authorized')->default(false);
            $table->text('custody_restriction_notes')->nullable();

            // Schema only in this sprint -- the actual verification
            // workflow (real identity-document check, registrar-
            // confirmed) is Phase 4, alongside Admissions, where a root
            // of trust is first established (§11).
            $table->foreignId('verified_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();

            // HasTemporalAssignment's required columns (App\Core\Concerns\
            // HasTemporalAssignment) -- guardian_student is this trait's
            // first real production consumer.
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->string('status')->default('active');
            $table->foreignId('reason_code_id')->nullable()->constrained('reason_codes')->restrictOnDelete();
            $table->foreignId('ended_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // guardAgainstOverlap() (HasTemporalAssignment) queries by
            // exactly this triple on every save() -- a single-column FK
            // index per column is not equivalent to this composite for
            // that lookup. status/effective_until are indexed separately
            // since scopeAsOf()/scopeActive() filter on them directly.
            $table->index(['guardian_id', 'student_id', 'effective_from']);
            $table->index('status');
            $table->index('effective_until');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guardian_student');
    }
};
