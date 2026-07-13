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
        Schema::create('duplicate_flags', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            // Naming mirrors App\Core\Services\DuplicateDetectionService's
            // own probe/candidate vocabulary (score(DuplicateSignals
            // $probe, DuplicateSignals $candidate)) rather than a
            // position-based primary/duplicate label that would
            // presuppose a resolution outcome before any review happens.
            $table->foreignId('source_person_id')->constrained('people')->restrictOnDelete();
            $table->foreignId('candidate_person_id')->constrained('people')->restrictOnDelete();

            // Mirrors DuplicateDetectionService::TIER_* / score() output
            // directly -- this table persists that service's result, it
            // never recomputes or reinterprets it.
            $table->unsignedTinyInteger('score');
            $table->string('tier');

            $table->string('status')->default('pending');
            $table->foreignId('resolved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Prevents re-flagging the exact same ordered pair twice, not
            // a claim that the reverse-direction scan (candidate as
            // source) is the same fact -- that's a distinct scan event,
            // deliberately out of scope for this sprint to deduplicate.
            $table->unique(['source_person_id', 'candidate_person_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('duplicate_flags');
    }
};
