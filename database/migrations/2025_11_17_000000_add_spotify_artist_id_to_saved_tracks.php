<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_tracks', function (Blueprint $table) {
            if (!Schema::hasColumn('saved_tracks', 'spotify_artist_id')) {
                $table->string('spotify_artist_id')->nullable()->after('spotify_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('saved_tracks', function (Blueprint $table) {
            if (Schema::hasColumn('saved_tracks', 'spotify_artist_id')) {
                $table->dropColumn('spotify_artist_id');
            }
        });
    }
};

