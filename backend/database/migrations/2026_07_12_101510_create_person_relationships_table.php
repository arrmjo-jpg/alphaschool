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
        Schema::create('person_relationships', function (Blueprint $table) {
            $table->id();

            // A generic, directed edge at the Person level
            // (docs/DOMAIN_BLUEPRINT.md §11, ADR-0003) -- deliberately
            // NOT parent_id/child_id or any hierarchy-implying naming.
            // All business meaning (sibling, spouse, former spouse,
            // paternal/maternal uncle, guardian, or any future kind)
            // comes entirely from relationship_type_id; this table
            // never encodes a fixed direction like "elder" or
            // "ancestor" -- that would be a table-structure assumption
            // the relationship graph must stay free of.
            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();
            $table->foreignId('related_person_id')->constrained('people')->restrictOnDelete();
            $table->foreignId('relationship_type_id')->constrained('relationship_types')->restrictOnDelete();

            $table->timestamps();

            // Prevents the exact same fact being recorded twice, not a
            // hierarchy constraint -- a person may still hold many
            // different relationship rows to the same related person
            // (e.g. both "guardian" and "relative" in an edge case).
            // related_person_id already has its own single-column index
            // via constrained() above, needed for the inverse-direction
            // query (find everything where I am the related person).
            $table->unique(['person_id', 'related_person_id', 'relationship_type_id'], 'person_relationships_unique_fact');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('person_relationships');
    }
};
