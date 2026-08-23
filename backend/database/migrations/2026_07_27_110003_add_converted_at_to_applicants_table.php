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
        Schema::table('applicants', function (Blueprint $table) {
            // 'status' itself needs no schema change -- it's already a
            // plain string, now accepting 'converted' as one more value
            // (Sprint 4.2's own migration comment already anticipated
            // this exact addition). converted_at is a distinct, explicit
            // audit timestamp rather than overloading updated_at, which
            // could be touched by unrelated future field changes.
            $table->dateTime('converted_at')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropColumn('converted_at');
        });
    }
};
