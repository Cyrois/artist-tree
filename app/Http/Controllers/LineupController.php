<?php

namespace App\Http\Controllers;

use App\Models\Artist;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LineupController extends Controller
{
    public function index()
    {
        // Mock lineups
        $lineups = collect([
            [
                'id' => 1,
                'name' => 'Summer Fest 2025',
                'description' => 'Annual summer music festival featuring diverse genres',
                'created_at' => now()->subDays(14)->format('M j, Y'),
                'updated_at' => now()->subHours(2)->diffForHumans(),
                'total_artists' => 17,
                'artists' => $this->getMockArtists(17),
            ],
            [
                'id' => 2,
                'name' => 'Desert Dreams',
                'description' => 'Electronic and indie focused desert festival',
                'created_at' => now()->subDays(19)->format('M j, Y'),
                'updated_at' => now()->subDays(1)->diffForHumans(),
                'total_artists' => 7,
                'artists' => $this->getMockArtists(7),
            ],
            [
                'id' => 3,
                'name' => 'Warehouse Series',
                'description' => 'Underground electronic music showcase',
                'created_at' => now()->subDays(28)->format('M j, Y'),
                'updated_at' => now()->subDays(3)->diffForHumans(),
                'total_artists' => 5,
                'artists' => $this->getMockArtists(5),
            ]
        ]);

        return Inertia::render('Lineups/Index', [
            'lineups' => $lineups
        ]);
    }

    private function getMockArtists($count)
    {
        // Get random artists from DB
        $artists = Artist::inRandomOrder()->limit($count)->get();
        
        // If we don't have enough artists in DB, we'll just loop what we have
        if ($artists->isEmpty()) {
            return [];
        }

        $tiers = ['headliner', 'sub_headliner', 'mid_tier', 'undercard'];
        
        return $artists->map(function ($artist) use ($tiers) {
            return [
                'id' => $artist->id,
                'name' => $artist->name,
                'image_url' => $artist->image_url,
                'tier' => $tiers[array_rand($tiers)], // Randomly assign tier
            ];
        })->values();
    }
}
