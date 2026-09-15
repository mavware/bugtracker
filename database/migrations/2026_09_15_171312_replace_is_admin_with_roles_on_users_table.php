<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // An account holds any number of roles; a JSON list keeps them on
            // the row, since a role is an enum case rather than a record.
            $table->json('roles')->nullable()->after('email_verified_at');
        });

        DB::table('users')->where('is_admin', false)->update(['roles' => json_encode([UserRole::Homeowner->value])]);
        DB::table('users')->where('is_admin', true)->update(['roles' => json_encode([UserRole::Admin->value])]);

        Schema::table('users', function (Blueprint $table) {
            $table->json('roles')->nullable(false)->change();
            $table->dropColumn('is_admin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('email_verified_at');
        });

        DB::table('users')->whereJsonContains('roles', UserRole::Admin->value)->update(['is_admin' => true]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('roles');
        });
    }
};
