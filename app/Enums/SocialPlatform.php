<?php

namespace App\Enums;

enum SocialPlatform: string
{
    case Official = 'official';
    case Facebook = 'facebook';
    case Twitter = 'twitter';
    case Instagram = 'instagram';
    case YouTube = 'youtube';
    case Spotify = 'spotify';
    case AppleMusic = 'apple_music';
    case SoundCloud = 'soundcloud';
    case Bandcamp = 'bandcamp';
    case TikTok = 'tiktok';
    case Discogs = 'discogs';
    case Wikidata = 'wikidata';
    case AllMusic = 'allmusic';
    case Deezer = 'deezer';
    case Tidal = 'tidal';
    case LastFm = 'last_fm';
    case Wikipedia = 'wikipedia';
}
