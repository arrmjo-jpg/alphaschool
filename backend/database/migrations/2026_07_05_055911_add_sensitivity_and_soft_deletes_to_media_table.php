<?php

use App\Modules\Media\Models\Media;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A separate migration, not an edit of the vendor-published
     * create_media_table migration -- standard Laravel convention: never
     * modify a package's own published migration, add your own on top.
     */
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            // docs/DOMAIN_BLUEPRINT.md Addendum B3 -- a classification
            // within the `private` disk, not a fourth disk tier. Default
            // 'standard'; high-sensitivity collections (medical, court
            // documents, identity documents) opt in explicitly.
            $table->string('sensitivity')->default(Media::SENSITIVITY_STANDARD)->after('collection_name');

            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn('sensitivity');
            $table->dropSoftDeletes();
        });
    }
};
