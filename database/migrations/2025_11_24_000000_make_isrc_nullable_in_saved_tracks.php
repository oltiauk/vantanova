<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // First, check and drop the foreign key constraint if it exists
        Schema::table('saved_tracks', function (Blueprint $table) {
            // Drop foreign key constraint first
            $table->dropForeign(['user_id']);
        });

        Schema::table('saved_tracks', function (Blueprint $table) {
            // Now drop the unique constraint
            $table->dropUnique(['user_id', 'isrc']);

            // Make isrc nullable
            $table->string('isrc')->nullable()->change();

            // Recreate the foreign key constraint
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Add indexes for lookup (not unique because isrc can be null)
            $table->index(['user_id', 'isrc'], 'saved_tracks_user_isrc_idx');
            $table->index(['user_id', 'spotify_id'], 'saved_tracks_user_spotify_idx');
        });
    }

    public function down(): void
    {
        Schema::table('saved_tracks', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['user_id']);
        });

        Schema::table('saved_tracks', function (Blueprint $table) {
            // Drop the new indexes
            $table->dropIndex('saved_tracks_user_isrc_idx');
            $table->dropIndex('saved_tracks_user_spotify_idx');

            // Make isrc not nullable again (will fail if there are null values)
            $table->string('isrc')->nullable(false)->change();

            // Restore the original unique constraint
            $table->unique(['user_id', 'isrc']);

            // Recreate foreign key
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
