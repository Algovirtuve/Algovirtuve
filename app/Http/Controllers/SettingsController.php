<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class SettingsController extends Controller
{
    public function createAppearance()
    {
        return Inertia::render('settings/appearance');
    }
}
