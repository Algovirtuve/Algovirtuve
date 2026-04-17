<?php

namespace App\Http\Controllers\Personalization;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class settings_controller extends Controller
{
    public function createAppearance()
    {
        return Inertia::render('Personalization/settings_page');
    }
}
