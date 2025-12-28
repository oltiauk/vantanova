<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_tracks', function (Blueprint $table) {
            // Add is_hidden field to track when user removes a track (permanent storage)
            $table->boolean('is_hidden')->default(false)->after('expires_at');
            
            // Add index for filtering hidden tracks
            $table->index('is_hidden');
        });
        
        // Make expires_at nullable using raw SQL (safer than ->change() which requires doctrine/dbal)
        DB::statement('ALTER TABLE saved_tracks MODIFY expires_at TIMESTAMP NULL');
    }

    public function down(): void
    {
        Schema::table('saved_tracks', function (Blueprint $table) {
            $table->dropIndex(['is_hidden']);
            $table->dropColumn('is_hidden');
        });
        
        // Revert expires_at to NOT NULL using raw SQL
        DB::statement('ALTER TABLE saved_tracks MODIFY expires_at TIMESTAMP NOT NULL');
    }
};
