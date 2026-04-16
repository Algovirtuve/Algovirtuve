<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNewPasswordRequest;
use App\Http\Requests\StorePasswordResetLinkRequest;
use App\Http\Requests\StoreSessionRequest;
use App\Http\Requests\StoreUserRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function createSession(Request $request): Response
    {
        return Inertia::render('auth/login', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function storeSession(StoreSessionRequest $request): RedirectResponse
    {
        $credentials = $request->validated();

        if (! Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ], $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroySession(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect(route('login', absolute: false));
    }

    public function createUser(): Response
    {
        return Inertia::render('auth/register');
    }

    public function storeUser(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function createPasswordResetLink(Request $request): Response
    {
        return Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function storePasswordResetLink(StorePasswordResetLinkRequest $request): RedirectResponse
    {
        $credentials = $request->validated();

        $status = Password::sendResetLink($credentials);

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', __($status));
        }

        throw ValidationException::withMessages([
            'email' => __($status),
        ]);
    }

    public function createNewPassword(Request $request, string $token): Response
    {
        return Inertia::render('auth/reset-password', [
            'email' => (string) $request->string('email'),
            'token' => $token,
        ]);
    }

    public function storeNewPassword(StoreNewPasswordRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $status = Password::reset($validated, function (User $user, string $password): void {
            if (Hash::check($password, $user->password)) {
                throw ValidationException::withMessages([
                    'password' => __('The new password must be different from your current password.'),
                ]);
            }

            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));
        });

        if ($status === Password::PASSWORD_RESET) {
            return redirect(route('login', absolute: false))->with('status', __($status));
        }

        throw ValidationException::withMessages([
            'email' => __($status),
        ]);
    }
}
