<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A room used to be a free-text label repeated on every session and
     * intervention. It becomes a row of its own, owned by the account that
     * recorded it and, when the camera was at a customer's property, by that
     * customer too. The existing labels are carried over: one room per distinct
     * owner, property and name, and every session and intervention is pointed at
     * its room before the label columns go.
     */
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // A room is part of the property, so it goes with the customer record.
            // The nights recorded in it are kept: their room_id is nulled below.
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->timestamps();

            $table->index(['user_id', 'customer_id', 'name']);
        });

        Schema::table('surveillance_sessions', function (Blueprint $table) {
            $table->foreignId('room_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
        });

        Schema::table('interventions', function (Blueprint $table) {
            $table->foreignId('room_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
        });

        $this->carryLabelsOver('surveillance_sessions');
        $this->carryLabelsOver('interventions');

        Schema::table('surveillance_sessions', function (Blueprint $table) {
            $table->dropColumn('room');
        });

        Schema::table('interventions', function (Blueprint $table) {
            $table->dropColumn('room');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('surveillance_sessions', function (Blueprint $table) {
            $table->string('room', 80)->nullable()->after('name');
        });

        Schema::table('interventions', function (Blueprint $table) {
            $table->string('room', 80)->nullable()->after('customer_id');
        });

        foreach (['surveillance_sessions', 'interventions'] as $tableName) {
            foreach (DB::table('rooms')->get() as $room) {
                DB::table($tableName)->where('room_id', $room->id)->update(['room' => $room->name]);
            }
        }

        Schema::table('surveillance_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('room_id');
        });

        Schema::table('interventions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('room_id');
        });

        Schema::dropIfExists('rooms');
    }

    /**
     * Give every distinct label on the table a room row, reusing one the other
     * table already created, and point the rows at it.
     */
    private function carryLabelsOver(string $tableName): void
    {
        $labels = DB::table($tableName)
            ->whereNotNull('room')
            ->where('room', '!=', '')
            ->select('user_id', 'customer_id', 'room')
            ->distinct()
            ->get();

        $now = now();

        foreach ($labels as $label) {
            // A plain where() on a null customer would match nothing, so the
            // property is matched with whereNull for the account's own rooms.
            $forProperty = fn (QueryBuilder $query): QueryBuilder => $label->customer_id === null
                ? $query->whereNull('customer_id')
                : $query->where('customer_id', $label->customer_id);

            $roomId = $forProperty(DB::table('rooms')->where('user_id', $label->user_id))
                ->where('name', $label->room)
                ->value('id');

            if ($roomId === null) {
                $roomId = DB::table('rooms')->insertGetId([
                    'user_id' => $label->user_id,
                    'customer_id' => $label->customer_id,
                    'name' => $label->room,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $forProperty(DB::table($tableName)->where('user_id', $label->user_id))
                ->where('room', $label->room)
                ->update(['room_id' => $roomId]);
        }
    }
};
