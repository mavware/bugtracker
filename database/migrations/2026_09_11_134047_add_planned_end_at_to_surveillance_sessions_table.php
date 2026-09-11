<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A night can be told to end itself after so many hours. The capture device
     * keeps that deadline and acts on it; the server is told so the dashboard
     * can say when the night ends and notice one that was due to end but did
     * not. Null is a night that runs until End night is pressed.
     */
    public function up(): void
    {
        Schema::table('surveillance_sessions', function (Blueprint $table) {
            $table->timestamp('planned_end_at')->nullable()->after('last_heartbeat_at');
        });
    }

    public function down(): void
    {
        Schema::table('surveillance_sessions', function (Blueprint $table) {
            $table->dropColumn('planned_end_at');
        });
    }
};
