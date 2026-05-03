<?php

namespace App\Enums;

enum ToolType: string
{
    case ELECTRONIC_DEVICE = 'electronic_device';
    case BAKING = 'baking';
    case MIXING = 'mixing';
    case CUTTING = 'cutting';
    case COOKING = 'cooking';

    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }
}
