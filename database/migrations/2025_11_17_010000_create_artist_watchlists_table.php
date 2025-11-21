<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artist_watchlists', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->string('artist_id');
            $table->string('artist_name');
            $table->string('artist_image_url')->nullable();
            $table->unsignedBigInteger('followers')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'artist_id']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('artist_watchlist_searches', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->json('results')->nullable();
            $table->timestamp('last_executed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('artist_count')->default(0);
            $table->unsignedInteger('track_count')->default(0);
            $table->timestamps();

            $table->unique('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artist_watchlist_searches');
        Schema::dropIfExists('artist_watchlists');
    }
};

