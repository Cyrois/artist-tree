<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Use different seeders based on environment
        if (app()->environment('testing')) {
            $this->call([
                TestingSeeder::class,
            ]);
        } else {
            $this->call([
                CountrySeeder::class,
                UserSeeder::class,
                LineupSeeder::class,
            ]);
        }
    }
}
