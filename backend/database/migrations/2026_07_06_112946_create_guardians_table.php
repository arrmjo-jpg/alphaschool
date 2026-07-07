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
        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            // The permanent identity anchor (docs/DOMAIN_BLUEPRINT.md §3)
            // -- one Guardian per Person, ever. Its real substance
            // (which students, relationship_type, custody/pickup
            // authorization, notification/portal defaults) is
            // guardian_student's job (Sprint 2.5, ADR-0003) -- none of
            // that belongs here.
            $table->foreignId('person_id')->unique()->constrained('people')->restrictOnDelete();

            // Flat, unlike Employee/Student -- Guardian's lifecycle is
            // genuinely thinner (its substance lives in guardian_student,
            // not here), so it is not forced into a richer status set
            // purely for surface symmetry with Employee/Student.
            $table->string('lifecycle_status')->default('active');

            $table->timestamps();

            // True soft-delete, not is_active -- same identity-context
            // category as Person/User/Employee/Student.
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guardians');
    }
};
