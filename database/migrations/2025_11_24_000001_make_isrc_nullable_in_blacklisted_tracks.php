<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // First, drop the foreign key constraint
        Schema::table('blacklisted_tracks', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('blacklisted_tracks', function (Blueprint $table) {
            // Drop the unique constraint
            $table->dropUnique(['user_id', 'isrc']);

            // Make isrc nullable
            $table->string('isrc')->nullable()->change();

            // Recreate the foreign key constraint
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Add indexes for lookup (not unique because isrc can be null)
            $table->index(['user_id', 'isrc'], 'blacklisted_tracks_user_isrc_idx');
            $table->index(['user_id', 'spotify_id'], 'blacklisted_tracks_user_spotify_idx');
        });
    }

    public function down(): void
    {
        Schema::table('blacklisted_tracks', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['user_id']);
        });

        Schema::table('blacklisted_tracks', function (Blueprint $table) {
            // Drop the new indexes
            $table->dropIndex('blacklisted_tracks_user_isrc_idx');
            $table->dropIndex('blacklisted_tracks_user_spotify_idx');

            // Make isrc not nullable again
            $table->string('isrc')->nullable(false)->change();

            // Restore the original unique constraint
            $table->unique(['user_id', 'isrc']);

            // Recreate foreign key
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
