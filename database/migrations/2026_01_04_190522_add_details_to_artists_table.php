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
        Schema::table('artists', function (Blueprint $table) {
            $table->string('youtube_channel_id')->nullable()->after('spotify_id');
            $table->string('musicbrainz_id')->nullable()->unique()->after('youtube_channel_id');
            $table->foreignId('country_id')->nullable()->after('name')->constrained('countries')->nullOnDelete();

            $table->dropColumn('genres');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('artists', function (Blueprint $table) {
            $table->jsonb('genres')->nullable();
            $table->dropForeign(['country_id']);
            $table->dropColumn(['musicbrainz_id', 'youtube_channel_id', 'country_id']);
        });
    }
};
