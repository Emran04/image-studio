<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Login', [
            'submitUrl' => route('login.store', absolute: false),
            'registerUrl' => route('register', absolute: false),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->authenticate($request, admin: false);
    }

    public function createAdmin(Request $request): Response|RedirectResponse
    {
        if ($request->user()?->can('view-admin')) {
            return to_route('admin.index');
        }

        return Inertia::render('Login', [
            'submitUrl' => route('admin.login.store', absolute: false),
            'admin' => true,
        ]);
    }

    public function storeAdmin(Request $request): RedirectResponse
    {
        if ($request->user()?->can('view-admin')) {
            return to_route('admin.index');
        }

        return $this->authenticate($request, admin: true);
    }

    private function authenticate(Request $request, bool $admin): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:1024'],
        ]);

        $lowercaseEmail = Str::lower($credentials['email']);
        $throttleKey = 'login:'.hash('sha256', $lowercaseEmail.'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => $seconds, 'minutes' => (int) ceil($seconds / 60)]),
            ]);
        }

        if ($admin) {
            $credentials['is_admin'] = true;
        }

        if (! Auth::attempt($credentials)
            && ($credentials['email'] === $lowercaseEmail || ! Auth::attempt([...$credentials, 'email' => $lowercaseEmail]))) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();
        $request->session()->forget('url.intended');

        return to_route($admin ? 'admin.index' : 'generations.create');
    }

    public function destroy(Request $request): RedirectResponse
    {
        return $this->logout($request, 'login');
    }

    public function destroyAdmin(Request $request): RedirectResponse
    {
        return $this->logout($request, 'admin.login');
    }

    private function logout(Request $request, string $loginRoute): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Inertia::clearHistory();

        return to_route($loginRoute);
    }
}
