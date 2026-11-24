<?php

namespace App\Http\Controllers;

use App\Models\User;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Register', [
            'submitUrl' => route('register.store', absolute: false),
            'loginUrl' => route('login', absolute: false),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (is_string($email = $request->input('email'))) {
            $request->merge(['email' => Str::lower($email)]);
        }

        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['bail', 'required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['bail', 'required', 'string', 'confirmed', Password::min(8), function (string $attribute, string $value, Closure $fail): void {
                if (strlen($value) > 72 || str_contains($value, "\0")) {
                    $fail('The password must be at most 72 bytes and must not contain null characters.');
                }
            }],
        ]);

        $attributes['password'] = Hash::make($attributes['password']);

        try {
            $user = User::create($attributes);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => __('validation.unique', ['attribute' => 'email'])]);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget('url.intended');

        return to_route('generations.create');
    }
}
