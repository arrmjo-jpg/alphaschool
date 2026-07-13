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
        Schema::create('merge_reassignment_logs', function (Blueprint $table) {
            $table->id();

            // restrictOnDelete(), not cascadeOnDelete() -- the log must
            // never disappear even if its parent MergeRequest is later
            // soft-deleted; this is the permanent record Addendum C8
            // requires and rollback reads.
            $table->foreignId('merge_request_id')->constrained('merge_requests')->restrictOnDelete();

            // Polymorphic-shaped but not a real morph column pair --
            // no FK possible here (class/entity_id can point at any of
            // several tables), which is exactly why Identity
            // Maintenance, not each module, owns writing this log
            // (ADR-0009).
            $table->string('class');
            $table->string('field');
            $table->unsignedBigInteger('entity_id');

            $table->foreignId('old_person_id')->constrained('people')->restrictOnDelete();
            $table->foreignId('new_person_id')->constrained('people')->restrictOnDelete();

            // Append-only -- never updated after insert, only reversed
            // by rollback (which reads, never edits, these rows).
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merge_reassignment_logs');
    }
};
