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
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->string('name_en')->unique();
            $table->string('name_ar');

            $table->date('start_date');
            $table->date('end_date');

            // Three states, not a boolean -- rows are created ahead of
            // time (next year exists before it starts), and the
            // Blueprint's own "own lifecycle" wording (docs/business-
            // domains/academic.md) implies more than open/closed alone.
            // 'closed' gates writes to anything scoped to this year
            // (Sprint 4.1 spec, ClosedAcademicYearGuard).
            $table->string('status')->default('upcoming');

            $table->timestamps();

            // Reference/structural entity -- never hard-deleted, same
            // category as Branch. No softDeletes(): 'closed' already
            // expresses "no longer writable" more precisely than a
            // generic trashed flag would.
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_years');
    }
};
