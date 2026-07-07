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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            // The permanent identity anchor (docs/DOMAIN_BLUEPRINT.md §3)
            // -- one Employee per Person, ever. A rehire opens a new
            // Employment period (ADR-0005, Phase 6); it never creates a
            // second Employee row for the same Person.
            $table->foreignId('person_id')->unique()->constrained('people')->restrictOnDelete();

            // Coarse only -- deliberately not hire_date/leave_date/
            // position/salary, which ADR-0005 assigns to Employment
            // (Phase 6), not Employee. No branch_id -- Employee is never
            // directly branch-scoped (Addendum B6); branch membership
            // arrives with employee_branches (Sprint 2.4, Step 4).
            $table->string('lifecycle_status')->default('active');

            $table->timestamps();

            // True soft-delete, not is_active -- Employee is an
            // identity-context aggregate (same category as Person/User),
            // not a reference/structural entity like Branch/Role.
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
