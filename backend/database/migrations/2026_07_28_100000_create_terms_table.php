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
        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            // Per-year, real Master Data (BUS-0032, "Term" Architecture
            // Pass) -- a Term is a child of exactly one AcademicYear, not
            // a permanent global catalog. restrictOnDelete() matches
            // Section's own convention: an AcademicYear may never
            // silently disappear out from under a Term that references
            // it.
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();

            // Genuine translatable content ("First Semester" / "الفصل
            // الدراسي الأول"), unlike Section.name -- BUS-0032's explicit
            // bilingual decision.
            $table->string('name_en');
            $table->string('name_ar');

            // Ordering within the year ("First Term," "Second Term," ...)
            // -- unique per academic_year_id, not globally, since every
            // year restarts its own sequence at 1.
            $table->unsignedInteger('sequence_order');

            $table->date('start_date');
            $table->date('end_date');

            // 3-state lifecycle mirroring AcademicYear exactly
            // (upcoming/active/closed) -- BUS-0032's explicit decision.
            $table->string('status')->default('upcoming');

            $table->timestamps();

            $table->unique(['academic_year_id', 'sequence_order']);
            $table->index('academic_year_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('terms');
    }
};
