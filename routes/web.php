<?php

use App\Http\Controllers\ApiController;
use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

// API (JSON, same action names as the old api.php). These are web routes
// on purpose: they need the Laravel session (guest/host identity) and
// CSRF protection, unlike the old stateless api.php.
Route::match(['get', 'post'], '/api/{action}', [ApiController::class, 'dispatch'])
    ->where('action', '[a-z_]+');

// Inertia pages. Every route renders the same Party page with an
// initial view, so direct URLs work (host pages are reached by direct
// URL; the home page is guest-only and has no host link).
Route::get('/', [PageController::class, 'show'])->defaults('view', 'home');
Route::get('/who', [PageController::class, 'show'])->defaults('view', 'who');
Route::get('/reveal', [PageController::class, 'show'])->defaults('view', 'reveal');
Route::get('/gifts', [PageController::class, 'show'])->defaults('view', 'gifts');
Route::get('/wishlists', [PageController::class, 'show'])->defaults('view', 'wishlists');
Route::get('/vote', [PageController::class, 'show'])->defaults('view', 'vote');
Route::get('/guest-home', [PageController::class, 'show'])->defaults('view', 'guesthome');
Route::get('/host', fn () => redirect('/host/dashboard'));
Route::get('/host/dashboard', [PageController::class, 'show'])->defaults('view', 'hostdashboard');
Route::get('/host/setup', [PageController::class, 'show'])->defaults('view', 'hostsetup');
Route::get('/host/guests', [PageController::class, 'show'])->defaults('view', 'hostguests');
Route::get('/host/poll', [PageController::class, 'show'])->defaults('view', 'hostpoll');
Route::get('/superadmin', [PageController::class, 'show'])->defaults('view', 'superadmin');
