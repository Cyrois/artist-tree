<?php

namespace App\Enums;

enum ArtistTier: string
{
    case Headliner = 'headliner';
    case SubHeadliner = 'sub_headliner';
    case MidTier = 'mid_tier';
    case Undercard = 'undercard';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
