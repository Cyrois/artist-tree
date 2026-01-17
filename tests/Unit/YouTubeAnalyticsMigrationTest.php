<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Feature: youtube-integration, Property 1: Database Schema Consistency
 * Validates: Requirements Database Schema
 */
it('ensures YouTube analytics columns exist with correct data types', function () {
    // Property 1: Database Schema Consistency
    // For any database migration, the YouTube analytics columns should exist
    // with the correct data types and nullable constraints

    // Verify the table exists
    expect(Schema::hasTable('artist_metrics'))->toBeTrue();

    // Define expected YouTube analytics columns
    $expectedColumns = [
        'youtube_refreshed_at',
        'youtube_avg_views',
        'youtube_avg_likes',
        'youtube_avg_comments',
        'youtube_videos_analyzed',
        'youtube_analytics_refreshed_at',
    ];

    // Verify each column exists
    foreach ($expectedColumns as $expectedColumn) {
        expect(Schema::hasColumn('artist_metrics', $expectedColumn))
            ->toBeTrue("Column {$expectedColumn} should exist");
    }

    // Verify youtube_subscribers column still exists (prerequisite)
    expect(Schema::hasColumn('artist_metrics', 'youtube_subscribers'))
        ->toBeTrue('youtube_subscribers column should exist as prerequisite');

    // Verify column types using Laravel's Schema facade
    // Check that all YouTube analytics columns exist and are properly configured
    $columns = Schema::getColumnListing('artist_metrics');

    // Verify all expected columns are present in the table
    foreach ($expectedColumns as $column) {
        expect(in_array($column, $columns))
            ->toBeTrue("Column {$column} should be present in artist_metrics table");
    }

    // Test that we can insert and retrieve data with these columns
    // This validates that the columns have the correct data types

    // First create an artist since there's a foreign key constraint
    $artistId = DB::table('artists')->insertGetId([
        'name' => 'Test Artist',
        'spotify_id' => 'test123',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('artist_metrics')->insert([
        'artist_id' => $artistId,
        'youtube_refreshed_at' => now(),
        'youtube_avg_views' => 1000000,
        'youtube_avg_likes' => 50000,
        'youtube_avg_comments' => 5000,
        'youtube_videos_analyzed' => 15,
        'youtube_analytics_refreshed_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Verify the data was inserted correctly
    $record = DB::table('artist_metrics')->where('artist_id', $artistId)->first();
    expect($record)->not->toBeNull('Record should be inserted successfully');
    expect($record->youtube_avg_views)->toBe(1000000);
    expect($record->youtube_avg_likes)->toBe(50000);
    expect($record->youtube_avg_comments)->toBe(5000);
    expect($record->youtube_videos_analyzed)->toBe(15);
    expect($record->youtube_refreshed_at)->not->toBeNull();
    expect($record->youtube_analytics_refreshed_at)->not->toBeNull();

    // Clean up test data
    DB::table('artist_metrics')->where('artist_id', $artistId)->delete();
    DB::table('artists')->where('id', $artistId)->delete();
});

/**
 * Property-based test: Database Schema Consistency across multiple runs
 * For any fresh migration, the schema should be consistent
 */
it('maintains schema consistency across multiple migration runs', function () {
    // This property test ensures that running migrations multiple times
    // produces the same consistent schema structure

    // Get initial column listing
    $initialColumns = Schema::getColumnListing('artist_metrics');

    // Rollback and re-run the migration
    $this->artisan('migrate:rollback', ['--step' => 1]);
    $this->artisan('migrate');

    // Get columns after re-migration
    $finalColumns = Schema::getColumnListing('artist_metrics');

    // Schema should contain the same columns (order may vary)
    expect(count($finalColumns))->toBe(count($initialColumns), 'Column count should be consistent');

    // Verify all initial columns still exist
    foreach ($initialColumns as $column) {
        expect(in_array($column, $finalColumns))
            ->toBeTrue("Column {$column} should exist after re-migration");
    }

    // Verify all YouTube analytics columns still exist
    $youtubeColumns = [
        'youtube_subscribers',
        'youtube_refreshed_at',
        'youtube_avg_views',
        'youtube_avg_likes',
        'youtube_avg_comments',
        'youtube_videos_analyzed',
        'youtube_analytics_refreshed_at',
    ];

    foreach ($youtubeColumns as $column) {
        expect(Schema::hasColumn('artist_metrics', $column))
            ->toBeTrue("Column {$column} should exist after re-migration");
    }
});
