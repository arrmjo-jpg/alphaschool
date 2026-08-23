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
        Schema::table('students', function (Blueprint $table) {
            // Nullable -- the FK must be addable before any Enrollment
            // exists at migration time. In practice a Student always has
            // this populated immediately by ConvertApplicantToStudentAction.
            // Derived-vs-stored trade-off deliberately resolved in favor
            // of storing it here: Sprint 4.4's promotion/transfer/repeat
            // workflows all need to update "which Enrollment is current"
            // as a single, fast write, not a derived query recomputed on
            // every read.
            $table->foreignId('current_enrollment_id')->nullable()->after('lifecycle_status')
                ->constrained('enrollments')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_enrollment_id');
        });
    }
};
