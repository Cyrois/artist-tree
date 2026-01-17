<?php

namespace Tests\Unit;

use App\Models\Genre;
use Tests\TestCase;

class GenreSmartLogicTest extends TestCase
{
    /**
     * Test that different variations of the same genre map to the same record
     * and that the system "learns" new synonyms over time.
     */
    public function test_smart_genre_creation_and_matching()
    {
        // 1. Create initial genre "R&B"
        $g1 = Genre::findOrCreateSmart('R&B');
        $this->assertEquals('R&B', $g1->name);
        $this->assertEquals('rb', $g1->slug);
        $this->assertContains('rnb', $g1->synonyms); // Normalized version added by boot()

        // 2. Find by exact normalized synonym "rnb"
        $g2 = Genre::findOrCreateSmart('rnb');
        $this->assertEquals($g1->id, $g2->id);

        // 3. Find by raw variation "RnB"
        // Should match normalized "rnb" and add "RnB" to synonyms
        $g3 = Genre::findOrCreateSmart('RnB');
        $this->assertEquals($g1->id, $g3->id);

        $g1->refresh();
        $this->assertContains('RnB', $g1->synonyms);

        // 4. Find by another variation "R & B"
        $g4 = Genre::findOrCreateSmart('R & B');
        $this->assertEquals($g1->id, $g4->id);

        $g1->refresh();
        $this->assertContains('R & B', $g1->synonyms);

        // 5. Test another genre "Hip-Hop"
        $h1 = Genre::findOrCreateSmart('Hip-Hop');
        $this->assertEquals('Hip-Hop', $h1->name);
        $this->assertContains('hiphop', $h1->synonyms);

        // 6. Match "Hip Hop" to "Hip-Hop"
        $h2 = Genre::findOrCreateSmart('Hip Hop');
        $this->assertEquals($h1->id, $h2->id);

        $h1->refresh();
        $this->assertContains('Hip Hop', $h1->synonyms);
    }
}
