<?php

namespace App\Enums;

enum ArtistDeleteReason: string
{
    case NO_SONGS = 'no_songs';
    case SPOTIFY_404 = 'spotify_404';
    case DUPLICATE = 'duplicate';
    case MANUAL = 'manual';
}
