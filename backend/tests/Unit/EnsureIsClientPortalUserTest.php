<?php

use App\Http\Middleware\EnsureIsClientPortalUser;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

test('blocks a staff user (no client_id) with a 403', function () {
    $user = new User(['client_id' => null]);
    $request = Request::create('/api/v1/portal/dashboard');
    $request->setUserResolver(fn () => $user);

    (new EnsureIsClientPortalUser)->handle($request, fn ($req) => 'next-called');
})->throws(HttpException::class);

test('allows a client-portal user through', function () {
    $user = new User(['client_id' => 'some-uuid']);
    $request = Request::create('/api/v1/portal/dashboard');
    $request->setUserResolver(fn () => $user);

    $result = (new EnsureIsClientPortalUser)->handle($request, fn ($req) => 'next-called');

    expect($result)->toBe('next-called');
});
