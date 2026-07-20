<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Session-based (cookie) login for the Inertia/Vue web portal -- the 'web'
 * guard from config/auth.php, separate from the Sanctum bearer-token login
 * in Api\V1\AuthController used by pure API clients. Same underlying
 * User model and password, two different credential mechanisms for two
 * different kinds of client.
 */
class LoginController
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = strtolower($request->input('email')).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, (int) config('npvams.login_throttle.max_attempts'))) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => ["Too many login attempts. Try again in {$seconds} seconds."],
            ]);
        }

        // Look up across all tenants (email is globally unique) before
        // attempting -- same reasoning as Api\V1\AuthController::login().
        $user = User::withoutTenantScope()->where('email', $request->input('email'))->first();

        if (! $user || ! $user->is_active || ! Auth::attempt(
            ['email' => $request->input('email'), 'password' => $request->input('password')],
            true,
        )) {
            RateLimiter::hit($throttleKey, (int) config('npvams.login_throttle.decay_minutes') * 60);

            throw ValidationException::withMessages(['email' => ['Invalid credentials.']]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();

        return redirect()->intended('/');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
