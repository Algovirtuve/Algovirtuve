<?php

namespace App\Enums;

enum RecipeStatus: string
{
    case Draft = 'draft';
    case Accepted = 'accepted';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Accepted => 'Accepted',
            self::Declined => 'Declined',
        };
    }
}
