<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blacklisted_artists', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id'); // Match users table increments('id')
            $table->string('spotify_artist_id')->index(); // Primary artist Spotify ID
            $table->string('artist_name');
            $table->timestamps();
            
            $table->unique(['user_id', 'spotify_artist_id']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blacklisted_artists');
    }
};