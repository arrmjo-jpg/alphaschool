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
        Schema::create('reason_codes', function (Blueprint $table) {
            $table->id();
            // Which HasTemporalAssignment-consuming context this reason
            // applies to, e.g. 'enrollment', 'employment',
            // 'homeroom_teacher_assignment'. Different assignment types
            // have different valid reasons -- a single global reason pool
            // would let a "retirement" reason be picked when closing a
            // Section Assignment, which makes no sense
            // (docs/DOMAIN_BLUEPRINT.md §6).
            $table->string('context');
            $table->string('code');
            $table->json('label'); // Spatie Translatable: {"en": "...", "ar": "..."}
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['context', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reason_codes');
    }
};
