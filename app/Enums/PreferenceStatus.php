<?php

namespace App\Enums;

enum PreferenceStatus: string
{
    case Awaiting = 'awaiting';
    case Liked = 'liked';
    case Disliked = 'disliked';

    public function label(): string
    {
        return match ($this) {
            self::Awaiting => 'Awaiting',
            self::Liked => 'Liked',
            self::Disliked => 'Disliked',
        };
    }
}
