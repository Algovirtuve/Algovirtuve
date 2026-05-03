<?php

namespace App\Enums;

enum IngredientCategory: string
{
    case VEGETABLE = 'vegetable';
    case MUSHROOM = 'mushroom';
    case WHEAT_PRODUCT = 'wheat_product';
    case FLOUR_PRODUCT = 'flour_product';
    case MEAT = 'meat';
    case DAIRY = 'dairy';
    case SEASONING = 'seasoning';
    case NUT = 'nut';
    case SEED = 'seed';
    case BERRY = 'berry';
    case FRUIT = 'fruit';
    case FISH_PRODUCT = 'fish_product';

    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }
}
