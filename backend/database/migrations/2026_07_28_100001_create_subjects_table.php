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
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            // The global subject catalog (Subject Offering + Timetables
            // Architecture Pass) -- a permanent catalog like GradeLevel,
            // not per-year like Section/Term. Deliberately minimal:
            // code/name_en/name_ar/is_active only. BUS-0018's full
            // richness (versioning, equivalency, prerequisites,
            // department) is NOT built here -- nothing consumes it yet,
            // and building it now would be exactly the "prediction, not
            // promotion" mistake this project has consistently avoided
            // (Addendum B1). Extend when a real consumer needs it.
            $table->string('code')->unique();
            $table->string('name_en');
            $table->string('name_ar');

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
