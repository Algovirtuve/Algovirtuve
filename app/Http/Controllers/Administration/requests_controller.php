<?php

namespace App\Http\Controllers\Administration;

use App\Enums\RecipeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveRequestRequest;
use App\Http\Requests\DeclineRequestRequest;
use App\Models\Request as AdminRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class requests_controller extends Controller
{
    public function viewRequests(Request $request): Response
    {
        $requests = AdminRequest::select()
            ->with(['user', 'recipe'])
            ->orderByDesc('date')
            ->get()
            ->map(fn (AdminRequest $requestModel): array => [
                'id' => $requestModel->id,
                'date' => $requestModel->date?->toDateString(),
                'user' => [
                    'id' => $requestModel->user?->id,
                    'username' => $requestModel->user?->username,
                    'email' => $requestModel->user?->email,
                ],
                'recipe' => [
                    'id' => $requestModel->recipe?->id,
                    'title' => $requestModel->recipe?->title,
                    'status' => $requestModel->recipe?->status->value,
                    'status_label' => $requestModel->recipe?->status->label(),
                ],
                'administrator_id' => $requestModel->administrator_id,
            ])
            ->values();

        return Inertia::render('Administration/requests_page', [
            'requests' => $requests,
        ]);
    }

    public function viewRequest(Request $httpRequest, AdminRequest $request): Response
    {
        $request = AdminRequest::select()
            ->with(['user', 'recipe.owner'])
            ->whereKey($request->getKey())
            ->firstOrFail();

        return Inertia::render('Administration/request_page', [
            'request' => [
                'id' => $request->id,
                'date' => $request->date?->toDateString(),
                'administrator_id' => $request->administrator_id,
                'user' => [
                    'id' => $request->user?->id,
                    'username' => $request->user?->username,
                    'email' => $request->user?->email,
                ],
                'recipe' => $request->recipe === null ? null : [
                    'id' => $request->recipe->id,
                    'title' => $request->recipe->title,
                    'instructions' => $request->recipe->instructions,
                    'status' => $request->recipe->status->value,
                    'status_label' => $request->recipe->status->label(),
                    'owner' => [
                        'id' => $request->recipe->owner?->id,
                        'username' => $request->recipe->owner?->username,
                        'email' => $request->recipe->owner?->email,
                    ],
                ],
            ],
        ]);
    }

    public function declineRequest(DeclineRequestRequest $httpRequest, AdminRequest $request): RedirectResponse
    {
        $request = AdminRequest::select()
            ->with('recipe')
            ->whereKey($request->getKey())
            ->firstOrFail();

        /** @var User $administratorUser */
        $administratorUser = User::select()->whereKey($httpRequest->user()?->getKey())->firstOrFail();
        $administrator = $administratorUser->administrator;

        $request->update([
            'administrator_id' => $administrator?->getKey(),
            'date' => now()->toDateString(),
        ]);

        if ($request->recipe !== null) {
            $request->recipe->update([
                'status' => RecipeStatus::Declined,
            ]);
        }

        return redirect(route('admin.requests.show', $request, absolute: false))
            ->with('toast', [
                'type' => 'success',
                'message' => 'Request declined successfully.',
            ]);
    }

    public function approveRequest(ApproveRequestRequest $httpRequest, AdminRequest $request): RedirectResponse
    {
        $request = AdminRequest::select()
            ->with('recipe')
            ->whereKey($request->getKey())
            ->firstOrFail();

        /** @var User $administratorUser */
        $administratorUser = User::select()->whereKey($httpRequest->user()?->getKey())->firstOrFail();
        $administrator = $administratorUser->administrator;

        $request->update([
            'administrator_id' => $administrator?->getKey(),
            'date' => now()->toDateString(),
        ]);

        if ($request->recipe !== null) {
            $request->recipe->update([
                'status' => RecipeStatus::Accepted,
            ]);
        }

        return redirect(route('admin.requests.show', $request, absolute: false))
            ->with('toast', [
                'type' => 'success',
                'message' => 'Request approved successfully.',
            ]);
    }
}
