<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TestingSeeder extends Seeder
{
    /**
     * Seed the testing database with minimal required data.
     */
    public function run(): void
    {
        $this->call([
            CountrySeeder::class,
            // Only seed countries for testing - no sample users, artists, or lineups
        ]);
    }
}