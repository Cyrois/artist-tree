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
        Schema::table('lineup_artists', function (Blueprint $table) {
            $table->uuid('stack_id')->nullable()->index()->after('tier');
            $table->boolean('is_stack_primary')->default(false)->after('stack_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lineup_artists', function (Blueprint $table) {
            $table->dropColumn(['stack_id', 'is_stack_primary']);
        });
    }
};
