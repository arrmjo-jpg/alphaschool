<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deliberately separate from configuration_values
 * (docs/adr/0018-configuration-platform-resolution-and-metadata.md
 * Decision 4): a personal preference carries none of the organizational
 * -policy weight a Branch override does, so it gets a simpler,
 * lower-ceremony mechanism -- no version column, no approval routing, no
 * full-diff audit trait on the model (see
 * App\Modules\Administration\Models\ConfigurationUserPreference).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuration_user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('configuration_key');
            $table->json('value');
            $table->timestamps();

            $table->unique(['user_id', 'configuration_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuration_user_preferences');
    }
};
