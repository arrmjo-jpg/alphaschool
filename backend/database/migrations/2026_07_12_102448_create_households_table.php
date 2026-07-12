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
        Schema::create('households', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            // An administrative grouping (docs/DOMAIN_BLUEPRINT.md §11) --
            // NOT a family model, and NEVER derived from
            // person_relationships. name_en/name_ar are plain, separate
            // columns (an admin-entered instance label), not Spatie
            // Translatable -- this is instance data, not shared system
            // vocabulary (Addendum B5), the same distinction already
            // applied to Branch's name_en/name_ar.
            $table->string('name_en');
            $table->string('name_ar');

            // Deactivation, not SoftDeletes: a future consumer (mailing,
            // transportation grouping, or any other module) may come to
            // reference a household, and a "deleted" row must never
            // leave a dangling reference -- the same reference/
            // structural-entity category as Branch/Role.
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('households');
    }
};
