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
        Schema::create('organization_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();

            // Domain modules only (Blueprint §1) -- Foundation modules
            // (Core, Identity, People, Media, Notifications, Settings)
            // are the base product and are never gated, so they never
            // get a row here. Validated at the application layer against
            // a fixed, developer-maintained list, not a DB enum -- the
            // set only grows when a new Domain module's own first
            // sprint ships, never by an admin at runtime.
            $table->string('module_code');

            $table->boolean('enabled')->default(true);

            // Null means perpetual / no expiration.
            $table->date('licensed_until')->nullable();

            $table->timestamps();

            $table->unique(['organization_id', 'module_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_modules');
    }
};
