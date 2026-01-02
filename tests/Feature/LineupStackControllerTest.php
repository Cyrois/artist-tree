<?php

use App\Models\Artist;
use App\Models\Lineup;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
});

test('authenticated users can create a new stack', function () {

    $this->withoutMiddleware();

    $lineup = Lineup::factory()->create();

    $artist = Artist::factory()->create();

    $lineup->artists()->attach($artist->id);



    $this->actingAs($this->user)

        ->post(route('lineups.stacks.store', $lineup->id), [

            'artist_id' => $artist->id,

        ])

        ->assertRedirect();



    $this->assertDatabaseHas('lineup_artists', [

        'lineup_id' => $lineup->id,

        'artist_id' => $artist->id,

        'is_stack_primary' => true,

    ]);

    

    $pivot = DB::table('lineup_artists')

        ->where('lineup_id', $lineup->id)

        ->where('artist_id', $artist->id)

        ->first();

        

    expect($pivot->stack_id)->not->toBeNull();

});



test('authenticated users can add to existing stack', function () {

    $this->withoutMiddleware();

    $lineup = Lineup::factory()->create();

    $artist1 = Artist::factory()->create();

    $artist2 = Artist::factory()->create();

    $lineup->artists()->attach([$artist1->id, $artist2->id]);

    

    $stackId = (string) Str::uuid();

    DB::table('lineup_artists')

        ->where('lineup_id', $lineup->id)

        ->where('artist_id', $artist1->id)

        ->update(['stack_id' => $stackId, 'is_stack_primary' => true]);



    $this->actingAs($this->user)

        ->post(route('lineups.stacks.store', $lineup->id), [

            'artist_id' => $artist2->id,

            'stack_id' => $stackId,

        ])

        ->assertRedirect();



    $this->assertDatabaseHas('lineup_artists', [

        'lineup_id' => $lineup->id,

        'artist_id' => $artist2->id,

        'stack_id' => $stackId,

        'is_stack_primary' => false,

    ]);

});



test('authenticated users can promote artist in stack', function () {



    $this->withoutMiddleware();



    $lineup = Lineup::factory()->create();









    $artist1 = Artist::factory()->create();

    $artist2 = Artist::factory()->create();

    $lineup->artists()->attach([$artist1->id, $artist2->id]);

    

    $stackId = (string) Str::uuid();

    DB::table('lineup_artists')

        ->where('lineup_id', $lineup->id)

        ->where('artist_id', $artist1->id)

        ->update(['stack_id' => $stackId, 'is_stack_primary' => true]);

    DB::table('lineup_artists')

        ->where('lineup_id', $lineup->id)

        ->where('artist_id', $artist2->id)

        ->update(['stack_id' => $stackId, 'is_stack_primary' => false]);



        $this->actingAs($this->user)



            ->post(route('lineups.stacks.promote', ['lineup' => $lineup->id, 'stack_id' => $stackId]), [



                'artist_id' => $artist2->id,



            ])



    

        ->assertRedirect();



    $this->assertDatabaseHas('lineup_artists', [

        'lineup_id' => $lineup->id,

        'artist_id' => $artist1->id,

        'is_stack_primary' => false,

    ]);

    $this->assertDatabaseHas('lineup_artists', [

        'lineup_id' => $lineup->id,

        'artist_id' => $artist2->id,

        'is_stack_primary' => true,

    ]);

});



test('authenticated users can remove artist from stack', function () {



    $this->withoutMiddleware();



    $lineup = Lineup::factory()->create();









    $artist = Artist::factory()->create();

    $lineup->artists()->attach($artist->id);

    

    $stackId = (string) Str::uuid();

    DB::table('lineup_artists')

        ->where('lineup_id', $lineup->id)

        ->where('artist_id', $artist->id)

        ->update(['stack_id' => $stackId, 'is_stack_primary' => true]);



    $this->actingAs($this->user)

        ->post(route('lineups.stacks.remove-artist', ['lineup' => $lineup->id, 'artist' => $artist->id]))

        ->assertRedirect();



    $this->assertDatabaseHas('lineup_artists', [

        'lineup_id' => $lineup->id,

        'artist_id' => $artist->id,

        'stack_id' => null,

        'is_stack_primary' => false,

    ]);

});



test('authenticated users can dissolve stack', function () {

    $this->withoutMiddleware();

    $lineup = Lineup::factory()->create();

    $artist1 = Artist::factory()->create();

    $artist2 = Artist::factory()->create();

    $lineup->artists()->attach([$artist1->id, $artist2->id]);

    

    $stackId = (string) Str::uuid();

    DB::table('lineup_artists')

        ->where('lineup_id', $lineup->id)

        ->update(['stack_id' => $stackId]);



        $this->actingAs($this->user)



            ->post(route('lineups.stacks.dissolve', ['lineup' => $lineup->id, 'stack_id' => $stackId]))



            ->assertRedirect();



    



    $this->assertDatabaseHas('lineup_artists', [

        'lineup_id' => $lineup->id,

        'artist_id' => $artist1->id,

        'stack_id' => null,

    ]);

    $this->assertDatabaseHas('lineup_artists', [

        'lineup_id' => $lineup->id,

        'artist_id' => $artist2->id,

        'stack_id' => null,

    ]);

});
