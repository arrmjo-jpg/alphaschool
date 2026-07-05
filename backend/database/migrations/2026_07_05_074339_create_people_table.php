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
        Schema::create('people', function (Blueprint $table) {
            $table->id();

            // docs/DOMAIN_BLUEPRINT.md Addendum D4: every domain aggregate
            // gets a ULID public_id for external API/route representation,
            // never the raw internal id -- Media is the only named exception.
            $table->ulid('public_id')->unique();

            // Bilingual name parts, plain flat columns -- names are
            // transliterations, not translations, so Spatie Translatable
            // does not apply here (agreed pre-implementation).
            $table->string('first_name_en');
            $table->string('first_name_ar');
            $table->string('second_name_en')->nullable();
            $table->string('second_name_ar')->nullable();
            $table->string('third_name_en')->nullable();
            $table->string('third_name_ar')->nullable();
            $table->string('family_name_en');
            $table->string('family_name_ar');

            $table->date('dob');
            $table->string('gender');

            // Structural only (no hardcoded country whitelist), same
            // lesson already applied to Money's currency and ReasonCode.
            $table->string('nationality')->nullable();

            // Indexed candidate-narrowing key for the Duplicate-Detection
            // Pattern (App\Core\Services\DuplicateDetectionService) --
            // never unique, since it's meant to surface near-duplicates,
            // not enforce them away.
            $table->string('search_key')->index();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('people');
    }
};
