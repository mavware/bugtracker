<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A night watched without an account is kept in the browser under a uuid the
     * browser minted. When the user later saves it to their account, that uuid
     * comes along so the import can be retried, or sent in chunks, without ever
     * making a second session out of the same night.
     */
    public function up(): void
    {
        Schema::table('surveillance_sessions', function (Blueprint $table) {
            $table->uuid('imported_local_id')->nullable()->after('customer_id');
            $table->unique(['user_id', 'imported_local_id']);
        });
    }

    public function down(): void
    {
        Schema::table('surveillance_sessions', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'imported_local_id']);
            $table->dropColumn('imported_local_id');
        });
    }
};
