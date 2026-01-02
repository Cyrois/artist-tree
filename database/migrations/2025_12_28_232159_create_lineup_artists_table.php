<?php

use App\Enums\ArtistTier;
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
            
            // Tier assignment
            $table->enum('tier', ArtistTier::values())->default(ArtistTier::Undercard->value);
            
            $table->timestamps();
            
            // Prevent same artist in same lineup multiple times
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

