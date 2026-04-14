<?php

namespace App\Enums;

enum RecipeStatus: string
{
    case Draft = 'draft';
    case Accepted = 'accepted';
    case Declined = 'declined';
}
