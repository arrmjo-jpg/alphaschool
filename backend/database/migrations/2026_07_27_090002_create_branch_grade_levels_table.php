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
        Schema::create('branch_grade_levels', function (Blueprint $table) {
            $table->id();

            // Mandatory, not the optional/nullable seam pattern
            // branches.parent_branch_id uses -- this FK is the join's
            // whole purpose. restrictOnDelete(), not cascade: deleting a
            // Branch or GradeLevel must never silently wipe out the
            // "this branch teaches this grade" historical fact.
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('grade_level_id')->constrained('grade_levels')->restrictOnDelete();

            // A Branch may stop offering a Grade without losing the
            // historical fact it once did -- deactivated, not deleted.
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['branch_id', 'grade_level_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branch_grade_levels');
    }
};
