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
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            // Deliberately shallow this sprint (Org -> School -> Branch
            // seam only): one School row, seeded under the one
            // Organization row, that every current Branch points to.
            // School-level Settings/Team/permission scoping is a real,
            // unanswered design question -- not built here.
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();

            $table->string('name_en');
            $table->string('name_ar');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
