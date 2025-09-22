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
        Schema::create('spotify_cache', function (Blueprint $table) {
            $table->id();
            $table->string('spotify_id')->unique(); // Spotify track, artist, or album ID
            $table->enum('type', ['track', 'artist', 'album']);
            $table->json('data'); // Cached Spotify API response
            $table->timestamp('expires_at'); // Cache expiration (e.g., 24 hours)
            $table->timestamps();

            $table->index(['spotify_id', 'type']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spotify_cache');
    }
};