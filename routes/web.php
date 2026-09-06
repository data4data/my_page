<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DeveloperInquiryController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\ReflectionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TimeLogController;
use Illuminate\Support\Facades\Route;

// Every path below renders the same Vue SPA shell (resources/views/app.blade.php);
// vue-router (resources/js/router.js) decides which page component to show.
Route::get('/', [PortfolioController::class, 'app']);
Route::get('/hi-developer', [PortfolioController::class, 'app']);

Route::get('/portfolio', [PortfolioController::class, 'show']);
Route::post('/hi-developer', [DeveloperInquiryController::class, 'store'])->middleware('throttle:10,1');

// The private workspace — admin editor and planning calendar — lives behind a
// per-install prefix (ADMIN_PATH in .env, see config/admin.php) rather than
// /admin, and nothing on the public page links to it. The prefix is read from
// config, not written here, so no install ships with the same guessable URL.
// The Blade shell passes the same value to the frontend as a <meta> tag, which
// is what resources/js/shared/admin-path.js reads.

// The way in and the way out sit behind the same prefix, but without the auth
// middleware — you cannot be signed in yet when you ask for the login page.
// At the standard /login, every commodity scanner probing for a login form
// found a real one; here there is nothing at /login to find. The path reveals
// nothing on its own, since anyone who can reach this URL already knows the
// prefix — which is why the shell is willing to emit it here.
Route::prefix(config('admin.path'))->group(function () {
    Route::get('/login', [PortfolioController::class, 'app'])->name('login')->middleware('guest');
    Route::post('/login', [AuthController::class, 'store'])->name('login.attempt')->middleware(['guest', 'throttle:login']);
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout')->middleware('auth');
});

Route::prefix(config('admin.path'))->middleware(['auth', 'role:admin'])->group(function () {
    // SPA shell routes. Each top-level admin section has its own real,
    // bookmarkable/refreshable URL — vue-router reads the path to decide
    // which one is active.
    Route::get('/', [PortfolioController::class, 'app']);
    Route::get('/mijn-agenda', [PortfolioController::class, 'app']);
    Route::get('/insights', [PortfolioController::class, 'app']);
    Route::get('/edit-content', [PortfolioController::class, 'app']);

    Route::get('/portfolio', [PortfolioController::class, 'edit']);
    Route::put('/portfolio', [PortfolioController::class, 'update']);
    Route::post('/portfolio/seed-defaults', [PortfolioController::class, 'seedDefaults']);

    // Saved versions of the public page, listed on the Reset content tab.
    Route::get('/portfolio/revisions', [PortfolioController::class, 'revisions']);
    Route::post('/portfolio/revisions/{revision}/restore', [PortfolioController::class, 'restore']);
    Route::get('/inquiries', [DeveloperInquiryController::class, 'index']);

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
