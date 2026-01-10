<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('artist_metrics', function (Blueprint $table) {
            // Add YouTube analytics columns
            // Note: PostgreSQL doesn't support AFTER clause, so columns will be added at the end
            $table->timestamp('youtube_refreshed_at')->nullable();
            $table->unsignedBigInteger('youtube_avg_views')->nullable();
            $table->unsignedBigInteger('youtube_avg_likes')->nullable();
            $table->unsignedBigInteger('youtube_avg_comments')->nullable();
            $table->unsignedInteger('youtube_videos_analyzed')->nullable();
            $table->timestamp('youtube_analytics_refreshed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('artist_metrics', function (Blueprint $table) {
            // Drop YouTube analytics columns in reverse order
            $table->dropColumn([
                'youtube_analytics_refreshed_at',
                'youtube_videos_analyzed',
                'youtube_avg_comments',
                'youtube_avg_views',
                'youtube_avg_likes',
                'youtube_refreshed_at'
            ]);
        });
    }
};
