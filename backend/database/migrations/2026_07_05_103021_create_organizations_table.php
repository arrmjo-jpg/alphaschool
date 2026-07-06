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
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            // docs/DOMAIN_BLUEPRINT.md Addendum A2: scoped narrowly as
            // vendor/licensing identity, NOT a business hierarchy layer
            // above School. Exactly one row expected per dedicated
            // instance (ADR-0006).
            $table->string('legal_name');
            $table->string('display_name');
            $table->string('support_contract_reference')->nullable();
            $table->date('support_expires_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
