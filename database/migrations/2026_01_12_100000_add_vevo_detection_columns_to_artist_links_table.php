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
        Schema::table('artist_links', function (Blueprint $table) {
            // Add review status column for future manual review functionality
            $table->string('review_status')->default('public_added')->after('vote_score');

            // Add column to track VEVO detection processing
            $table->timestamp('vevo_checked_at')->nullable()->after('review_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('artist_links', function (Blueprint $table) {
            $table->dropColumn(['review_status', 'vevo_checked_at']);
        });
    }
};
