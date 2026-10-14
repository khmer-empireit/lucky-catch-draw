<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The same file is now allowed to be re-uploaded into the same campaign
     * (e.g. re-importing a corrected or re-downloaded copy). Row-level
     * duplicate detection against existing participants still prevents any
     * actual double-import; this constraint only ever blocked the upload
     * itself, which is no longer desired.
     */
    public function up(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            // The campaign_id foreign key relies on an index with campaign_id
            // as its leftmost column to exist; the unique index being dropped
            // below was the only one providing that, so a plain index must
            // replace it first or MySQL refuses the drop (error 1553).
            $table->index('campaign_id');
            $table->dropUnique(['campaign_id', 'file_sha256']);
        });
    }

    public function down(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            $table->unique(['campaign_id', 'file_sha256']);
            $table->dropIndex(['campaign_id']);
        });
    }
};
