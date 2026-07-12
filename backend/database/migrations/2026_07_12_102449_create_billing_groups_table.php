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
        Schema::create('billing_groups', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            // An administrative shell only (docs/DOMAIN_BLUEPRINT.md
            // §11) -- "which students bill together", never computed
            // from person_relationships or coupled to Household.
            // Finance is the intended future consumer (sibling
            // discounts), but has no presence in this sprint at all --
            // no discount rate, no invoice linkage, nothing beyond a
            // label and membership.
            $table->string('name_en');
            $table->string('name_ar');

            // Deactivation, not SoftDeletes -- Finance will eventually
            // reference this table, and a "deleted" row must never
            // leave a dangling reference once that happens.
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_groups');
    }
};
