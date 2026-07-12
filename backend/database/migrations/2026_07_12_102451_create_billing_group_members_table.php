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
        Schema::create('billing_group_members', function (Blueprint $table) {
            $table->id();

            // Student-scoped, deliberately: the business question this
            // exists for is "which students bill together" (the Family
            // design session's own framing), not "which people share a
            // residence" (that's Household). No FK/coupling to
            // households at all -- the two groupings stay entirely
            // independent so Finance can consume this later without
            // Household ever needing to change.
            $table->foreignId('billing_group_id')->constrained('billing_groups')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();

            $table->timestamps();

            $table->unique(['billing_group_id', 'student_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_group_members');
    }
};
