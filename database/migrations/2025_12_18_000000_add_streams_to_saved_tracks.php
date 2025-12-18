<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_tracks', function (Blueprint $table) {
            if (!Schema::hasColumn('saved_tracks', 'streams')) {
                $table->bigInteger('streams')->nullable()->after('followers');
            }
        });
    }

    public function down(): void
    {
        Schema::table('saved_tracks', function (Blueprint $table) {
            if (Schema::hasColumn('saved_tracks', 'streams')) {
                $table->dropColumn('streams');
            }
        });
    }
};

