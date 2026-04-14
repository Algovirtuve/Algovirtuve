<?php

namespace App\Enums;

enum PreferenceStatus: string
{
    case Awating = 'awaiting';
    case Liked = 'liked';
    case Disliked = 'disliked';
}
