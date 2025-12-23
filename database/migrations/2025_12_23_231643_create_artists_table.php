<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('artists', function (Blueprint $table) {
            $table->id();
            $table->string('spotify_id')->unique()->nullable();
            $table->string('name');
            $table->jsonb('genres')->nullable();
            $table->string('image_url', 2048)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });

        // Add GIN index for jsonb genres column (PostgreSQL only)
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX artists_genres_gin ON artists USING GIN (genres jsonb_path_ops)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('artists');
    }
};
