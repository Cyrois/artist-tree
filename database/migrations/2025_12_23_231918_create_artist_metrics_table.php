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
        Schema::create('artist_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_id')->unique()->constrained()->cascadeOnDelete();

            // Spotify metrics
            $table->unsignedTinyInteger('spotify_popularity')->nullable(); // 0-100
            $table->unsignedBigInteger('spotify_followers')->nullable();

            // YouTube metrics (for future use)
            $table->unsignedBigInteger('youtube_subscribers')->nullable();

            // Future platforms - add columns via migration:
            $table->unsignedBigInteger('instagram_followers')->nullable();
            $table->unsignedBigInteger('tiktok_followers')->nullable();

            $table->timestamp('refreshed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('artist_metrics');
    }
};
