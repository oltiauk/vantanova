<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blacklisted_tracks', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id'); // Match users table increments('id')
            $table->string('isrc')->index(); // International Standard Recording Code
            $table->string('track_name');
            $table->string('artist_name');
            $table->string('spotify_id')->nullable();
            $table->timestamps();
            
            $table->unique(['user_id', 'isrc']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blacklisted_tracks');
    }
};