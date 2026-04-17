<?php

namespace App\Enums;

enum RecipeStatus: string
{
    case Draft = 'waiting_for_review';
    case Accepted = 'accepted';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Waiting for review',
            self::Accepted => 'Accepted',
            self::Declined => 'Declined',
        };
    }
}
