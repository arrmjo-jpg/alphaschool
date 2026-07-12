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
        Schema::create('household_members', function (Blueprint $table) {
            $table->id();

            // A plain membership pivot -- Person-level (a Household may
            // gather Guardians, Students, or any other Person, unlike
            // BillingGroup which is deliberately Student-scoped). No
            // temporal tracking (per this sprint's A3 decision) and no
            // FK to/from person_relationships in either direction --
            // Household membership must never be inferred from, or feed
            // back into, the relationship graph.
            $table->foreignId('household_id')->constrained('households')->restrictOnDelete();
            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();

            $table->timestamps();

            $table->unique(['household_id', 'person_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('household_members');
    }
};
