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
        Schema::create('approval_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('step_number');

            // Eligibility is by role OR by a specific user (at least one
            // is required, validated by the engine at request() time --
            // not enforced at the schema level since "at least one of two
            // nullable columns" isn't expressible as a simple constraint
            // portable across MySQL/SQLite).
            $table->string('required_role')->nullable();
            $table->foreignId('required_user_id')->nullable()->constrained('users');

            $table->string('status')->default('pending'); // pending|approved|rejected
            $table->foreignId('decided_by_id')->nullable()->constrained('users');
            $table->timestamp('decided_at')->nullable();
            $table->text('comments')->nullable();

            $table->timestamps();

            $table->unique(['approval_request_id', 'step_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_steps');
    }
};
