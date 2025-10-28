<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listened_tracks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('track_key');
            $table->string('track_name');
            $table->string('artist_name');
            $table->string('spotify_id')->nullable();
            $table->string('isrc')->nullable();
            $table->timestamp('first_listened_at')->nullable();
            $table->timestamp('last_listened_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'track_key']);
            $table->index(['user_id', 'last_listened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listened_tracks');
    }
};



