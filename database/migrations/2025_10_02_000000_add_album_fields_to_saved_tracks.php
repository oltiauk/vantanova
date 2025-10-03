<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_tracks', function (Blueprint $table) {
            $table->integer('track_count')->nullable()->after('preview_url');
            $table->boolean('is_single_track')->default(true)->after('track_count');
            $table->string('album_id')->nullable()->after('is_single_track');
        });
    }

    public function down(): void
    {
        Schema::table('saved_tracks', function (Blueprint $table) {
            $table->dropColumn(['track_count', 'is_single_track', 'album_id']);
        });
    }
};
