<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController
{
    /**
     * Authenticate and issue a Sanctum token.
     *
     * Tenant scoping is NOT applied here on purpose — at login time we do not
     * yet know which tenant the user belongs to; we look the user up by
     * email globally (email is unique platform-wide), then everything after
     * login is scoped via IdentifyTenant middleware using the resolved user.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $throttleKey = strtolower($request->input('email')).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, (int) config('npvams.login_throttle.max_attempts'))) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => ["Too many login attempts. Try again in {$seconds} seconds."],
            ]);
        }

        $user = User::withoutTenantScope()
            ->where('email', $request->input('email'))
            ->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            RateLimiter::hit($throttleKey, (int) config('npvams.login_throttle.decay_minutes') * 60);

            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['This account is inactive. Contact your organization administrator.'],
            ]);
        }

        if ($user->mfa_enabled) {
            $code = $request->input('mfa_code');

            if (! $code || ! $this->verifyMfaCode($user, $code)) {
                throw ValidationException::withMessages([
                    'mfa_code' => ['A valid MFA code is required.'],
                ]);
            }
        }

        RateLimiter::clear($throttleKey);

        $token = $user->createToken(
            name: 'api-token',
            expiresAt: now()->addMinutes((int) config('npvams.sanctum_token_expiration_minutes'))
        );

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        activity('auth')
            ->causedBy($user)
            ->withProperties(['ip' => $request->ip(), 'user_agent' => $request->userAgent()])
            ->log('user.login');

        return response()->json([
            'data' => [
                'token' => $token->plainTextToken,
                'user' => new UserResource($user),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        activity('auth')->causedBy($request->user())->log('user.logout');

        return response()->json(['data' => ['message' => 'Logged out.']]);
    }

    private function verifyMfaCode(User $user, string $code): bool
    {
        // TOTP verification hook — wire to a TOTP library (e.g. pragmarx/google2fa)
        // against $user->mfa_secret in the Phase 2 hardening pass. Deliberately
        // isolated here as a single choke point rather than inline in login().
        return app(\App\Support\Auth\TotpVerifier::class)->verify($user->mfa_secret, $code);
    }
}
