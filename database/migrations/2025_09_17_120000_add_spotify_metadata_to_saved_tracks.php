<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_tracks', function (Blueprint $table) {
            $table->string('label')->nullable()->after('spotify_id');
            $table->integer('popularity')->nullable()->after('label');
            $table->integer('followers')->nullable()->after('popularity');
            $table->string('release_date')->nullable()->after('followers');
            $table->string('preview_url')->nullable()->after('release_date');
        });
    }

    public function down(): void
    {
        Schema::table('saved_tracks', function (Blueprint $table) {
            $table->dropColumn(['label', 'popularity', 'followers', 'release_date', 'preview_url']);
        });
    }
};