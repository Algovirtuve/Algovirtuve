<?php

namespace App\Http\Controllers\Administration;

use App\Enums\RecipeStatus;
use App\Http\Controllers\Controller;
use App\Models\Request as AdminRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class admin_controller extends Controller
{
    public function viewAdminPage(Request $request): Response
    {
        $requestsCount = AdminRequest::select()->count();
        $rejectedRequestsCount = AdminRequest::select()
            ->whereHas('recipe', fn ($query) => $query->where('status', RecipeStatus::Declined->value))
            ->count();
        $waitingRequestsCount = AdminRequest::select()
            ->whereHas('recipe', fn ($query) => $query->where('status', RecipeStatus::Draft->value))
            ->count();
        $approvedRequestsCount = AdminRequest::select()
            ->whereHas('recipe', fn ($query) => $query->where('status', RecipeStatus::Accepted->value))
            ->count();

        $usersCount = User::select()->count();

        return Inertia::render('Administration/admin_page', [
            'requests_count' => $requestsCount,
            'rejected_requests_count' => $rejectedRequestsCount,
            'waiting_requests_count' => $waitingRequestsCount,
            'approved_requests_count' => $approvedRequestsCount,
            'users_count' => $usersCount,
        ]);
    }
}
