<?php

namespace App\Enums;

enum Measurement: string
{
    case G = 'g';
    case KG = 'kg';
    case ML = 'ml';
    case L = 'l';
    case UNIT = 'unit';

    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }
}
