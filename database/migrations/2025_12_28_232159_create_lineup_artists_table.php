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
        Schema::create('lineup_artists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lineup_id')->constrained()->cascadeOnDelete();
            $table->foreignId('artist_id')->constrained()->cascadeOnDelete();
            $table->enum('tier', ['headliner', 'sub_headliner', 'mid_tier', 'undercard'])->default('undercard');
            $table->enum('suggested_tier', ['headliner', 'sub_headliner', 'mid_tier', 'undercard'])->default('undercard');
            $table->boolean('tier_override')->default(false);
            $table->timestamps();

            $table->unique(['lineup_id', 'artist_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lineup_artists');
    }
};
