<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('artist_aliases', function (Blueprint $table) {
            $table->index(['artist_id', 'name'], 'artist_aliases_artist_id_name_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('artist_aliases', function (Blueprint $table) {
            $table->dropIndex('artist_aliases_artist_id_name_index');
        });
    }
};
