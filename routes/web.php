<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DeveloperInquiryController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\ReflectionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SecurityEventController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TimeLogController;
use App\Http\Controllers\TwoFactorController;
use Illuminate\Support\Facades\Route;

// Every path below renders the same Vue SPA shell (resources/views/app.blade.php);
// vue-router (resources/js/router.js) decides which page component to show.
Route::get('/', [PortfolioController::class, 'app']);
Route::get('/hi-developer', [PortfolioController::class, 'app']);

Route::get('/portfolio', [PortfolioController::class, 'show']);
Route::post('/hi-developer', [DeveloperInquiryController::class, 'store'])->middleware('throttle:10,1');

// The private workspace lives behind a per-install prefix (ADMIN_PATH) rather
// than /admin, and nothing on the public page links to it. Read from config,
// never written here, so no two installs share a URL.

// Login and logout sit behind the same prefix but without auth — you cannot
// be signed in yet when you ask for the login page. There is nothing at the
// standard /login for a scanner to find.
Route::prefix(config('admin.path'))->group(function () {
    Route::get('/login', [PortfolioController::class, 'app'])->name('login')->middleware('guest');
    Route::post('/login', [AuthController::class, 'store'])->name('login.attempt')->middleware(['guest', 'throttle:login']);
    // Second step. Throttled like the password step: six digits is cheap to
    // guess otherwise.
    Route::post('/two-factor-challenge', [AuthController::class, 'challenge'])->name('two-factor.challenge')->middleware(['guest', 'throttle:login']);
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout')->middleware('auth');
});

Route::prefix(config('admin.path'))->middleware(['auth', 'role:admin'])->group(function () {
    // SPA shell routes. Each section has a real, bookmarkable URL.
    Route::get('/', [PortfolioController::class, 'app']);
    Route::get('/mijn-agenda', [PortfolioController::class, 'app']);
    Route::get('/insights', [PortfolioController::class, 'app']);
    Route::get('/edit-content', [PortfolioController::class, 'app']);
    Route::get('/settings', [PortfolioController::class, 'app']);

    Route::get('/portfolio', [PortfolioController::class, 'edit']);
    Route::put('/portfolio', [PortfolioController::class, 'update']);
    Route::post('/portfolio/seed-defaults', [PortfolioController::class, 'seedDefaults']);

    // Saved versions of the public page.
    Route::get('/portfolio/revisions', [PortfolioController::class, 'revisions']);
    Route::post('/portfolio/revisions/{revision}/restore', [PortfolioController::class, 'restore']);
    Route::get('/inquiries', [DeveloperInquiryController::class, 'index']);

    // Sign-in attempts against this install, successful or not.
    Route::get('/security-events', [SecurityEventController::class, 'index']);

    // Two-factor enrolment. Turning it off asks for the password again,
    // rather than riding on whatever session is open.
    Route::get('/two-factor', [TwoFactorController::class, 'show']);
    Route::post('/two-factor', [TwoFactorController::class, 'store']);
    Route::post('/two-factor/confirm', [TwoFactorController::class, 'confirm']);
    Route::post('/two-factor/recovery-codes', [TwoFactorController::class, 'recoveryCodes']);
    Route::delete('/two-factor', [TwoFactorController::class, 'destroy']);

    // Planning Calendar (Agenda) — all scoped to the authenticated admin.
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{category}', [CategoryController::class, 'update']);
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

    Route::get('/tasks', [TaskController::class, 'index']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::put('/tasks/{task}', [TaskController::class, 'update']);
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);

    Route::post('/tasks/{task}/timer/start', [TimeLogController::class, 'start']);
    Route::post('/tasks/{task}/timer/stop', [TimeLogController::class, 'stop']);

    Route::get('/reports', [ReportController::class, 'show']);

    Route::get('/reflections', [ReflectionController::class, 'show']);
    Route::put('/reflections', [ReflectionController::class, 'upsert']);
});
