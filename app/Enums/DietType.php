<?php

namespace App\Enums;

enum DietType: string
{
    case Keto = 'keto';
    case Paleo = 'paleo';
    case IntermittentFasting = 'intermittent-fasting';
    case Vegan = 'vegan';
    case Vegetarian = 'vegetarian';

    public function label(): string
    {
        return match ($this) {
            self::Keto => 'Keto',
            self::Paleo => 'Paleo',
            self::IntermittentFasting => 'Intermittent fasting',
            self::Vegan => 'Vegan',
            self::Vegetarian => 'Vegetarian',
        };
    }
}
