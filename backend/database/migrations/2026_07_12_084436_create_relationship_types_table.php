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
        Schema::create('relationship_types', function (Blueprint $table) {
            $table->id();

            // Immutable once set (enforced at the application layer,
            // same convention as Branch's code) -- the only value
            // business code may ever reference. Never parsed from a
            // translated display name.
            $table->string('code')->unique();

            // Spatie Translatable JSON -- system vocabulary (Addendum
            // B5), not a personal/transliterated name. Exists so the
            // Arabic paternal/maternal kinship distinctions (عم vs
            // خال, جد لأب vs جد لأم) are genuinely separate rows,
            // never one enum case with two labels (docs/DOMAIN_
            // BLUEPRINT.md §11).
            $table->json('name');

            // Restricts which join this vocabulary is valid for --
            // guardian_student's relationship roles (Father, Mother,
            // Legal Guardian) and person_relationships' kinship terms
            // (Sibling, Uncle, Grandfather) are different vocabularies
            // sharing one translatable lookup table, the same "scope"
            // pattern already applied to Tags (Addendum D2).
            $table->string('scope');

            // Deactivation, not SoftDeletes: a relationship type is a
            // reference/structural entity (same category as Branch/
            // Role) -- historical guardian_student/person_relationships
            // rows must remain valid even after a type is retired from
            // new use, so it is deactivated, never deleted.
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('relationship_types');
    }
};
