<?php

namespace App\Enums;

enum DietaryType: string
{
    case Vegetarian = 'vegetarian';
    case NonVegetarian = 'non_vegetarian';
    case Eggetarian = 'eggetarian';

    public function label(): string
    {
        return match ($this) {
            self::Vegetarian => 'Veg',
            self::NonVegetarian => 'Non-Veg',
            self::Eggetarian => 'Egg',
        };
    }
}
