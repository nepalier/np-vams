<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// No server-side 'auth' middleware here on purpose: this app authenticates
// via a Sanctum bearer token stored client-side (see AuthController), not
// a server session. Gating this route with the session-based 'auth'
// middleware would always fail (no session is ever created by a token
// login) and silently bounce every logged-in user back to /login. Instead,
// the Vue app itself checks for a token on mount and redirects if missing
// -- see resources/js/Pages/Dashboard/Index.vue.
Route::get('/', fn () => Inertia::render('Dashboard/Index'));
Route::get('/assignments', fn () => Inertia::render('Assignments/Index'));
Route::get('/assignments/{id}', fn (string $id) => Inertia::render('Assignments/Show', ['id' => $id]));
Route::get('/login', fn () => Inertia::render('Auth/Login'))->name('login');
