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
        Schema::create('person_identity_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('document_type');
            $table->string('issuing_country');
            $table->string('number');
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();

            // A renewal (passport/national ID reissue) is a NEW row, never
            // an overwrite of the old one (docs/DOMAIN_BLUEPRINT.md §7 --
            // identity documents are never overwritten). is_current marks
            // which row is authoritative today.
            $table->boolean('is_current')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // Scoped to the whole (type, country, number) triple, not the
            // number alone -- avoids false collisions between different
            // document types/countries reusing the same number space.
            $table->unique(['document_type', 'issuing_country', 'number'], 'person_identity_documents_type_country_number_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('person_identity_documents');
    }
};
