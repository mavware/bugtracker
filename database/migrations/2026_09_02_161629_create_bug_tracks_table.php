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
        Schema::create('bug_tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('surveillance_session_id')->constrained()->cascadeOnDelete();
            $table->string('client_track_id', 64);
            $table->unsignedBigInteger('start_offset_ms');
            $table->unsignedBigInteger('end_offset_ms');
            $table->unsignedInteger('point_count');
            $table->json('points');
            $table->string('entry_edge', 12)->nullable();
            $table->string('exit_edge', 12)->nullable();
            $table->string('start_crop_path')->nullable();
            $table->string('end_crop_path')->nullable();
            $table->timestamps();
            $table->unique(['surveillance_session_id', 'client_track_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bug_tracks');
    }
};
