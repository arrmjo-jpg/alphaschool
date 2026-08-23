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
        Schema::create('grade_levels', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->string('code')->unique();
            $table->string('name_en');
            $table->string('name_ar');

            // Nursery -> KG -> Grade 1..12 ordering -- not inferable
            // from `code` alone across different naming schemes a
            // deployment might use.
            $table->unsignedInteger('sequence_order')->unique();

            // Reference entity -- deactivated, never hard-deleted,
            // matching branches/schools convention.
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('sequence_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grade_levels');
    }
};
