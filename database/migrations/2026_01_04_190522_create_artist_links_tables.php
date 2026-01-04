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
        Schema::create('artist_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_id')->constrained()->cascadeOnDelete();
            $table->string('platform')->index(); // 'spotify', 'instagram', etc.
            $table->string('url', 2048);
            $table->integer('vote_score')->default(0); // Cached sum of votes
            $table->timestamps();
        });

        Schema::create('artist_link_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_link_id')->constrained('artist_links')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('vote'); // 1 for upvote, -1 for downvote
            $table->timestamps();

            $table->unique(['artist_link_id', 'user_id']); // One vote per user per link
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('artist_link_votes');
        Schema::dropIfExists('artist_links');
    }
};
