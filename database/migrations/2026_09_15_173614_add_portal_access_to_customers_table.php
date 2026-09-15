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
        Schema::table('customers', function (Blueprint $table) {
            // The account the property's owner signs in with to see and tidy their
            // own nights. Nulled rather than cascaded: closing the client's account
            // must not take the professional's customer record with it.
            $table->foreignId('client_user_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->string('portal_invite_token', 64)->nullable()->unique()->after('notes');
            $table->timestamp('portal_invited_at')->nullable()->after('portal_invite_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_user_id');
            $table->dropColumn(['portal_invite_token', 'portal_invited_at']);
        });
    }
};
