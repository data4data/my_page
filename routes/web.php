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
Route::get('/login', [PortfolioController::class, 'app'])->name('login')->middleware('guest');
Route::get('/hi-developer', [PortfolioController::class, 'app']);

// The real admin editor intentionally lives at an unlinked, non-obvious path
// (no button/link anywhere on the public page points at it) rather than
// /admin — reachable only by whoever already knows the URL. Each top-level
// admin section has its own real, bookmarkable/refreshable URL — vue-router
// (resources/js/router.js) reads the path to decide which one is active.
Route::get('/control-room-ao', [PortfolioController::class, 'app'])->middleware(['auth', 'role:admin']);
Route::get('/control-room-ao/mijn-agenda', [PortfolioController::class, 'app'])->middleware(['auth', 'role:admin']);
Route::get('/control-room-ao/insights', [PortfolioController::class, 'app'])->middleware(['auth', 'role:admin']);
Route::get('/control-room-ao/edit-content', [PortfolioController::class, 'app'])->middleware(['auth', 'role:admin']);

Route::get('/portfolio', [PortfolioController::class, 'show']);
Route::post('/hi-developer', [DeveloperInquiryController::class, 'store'])->middleware('throttle:10,1');

Route::post('/login', [AuthController::class, 'store'])->name('login.attempt')->middleware(['guest', 'throttle:6,1']);
Route::post('/logout', [AuthController::class, 'destroy'])->name('logout')->middleware('auth');

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/control-room-ao/portfolio', [PortfolioController::class, 'edit']);
    Route::put('/control-room-ao/portfolio', [PortfolioController::class, 'update']);
    Route::post('/control-room-ao/portfolio/seed-defaults', [PortfolioController::class, 'seedDefaults']);
    Route::get('/control-room-ao/inquiries', [DeveloperInquiryController::class, 'index']);

    // Planning Calendar (Agenda) — all scoped to the authenticated admin.
    Route::get('/control-room-ao/categories', [CategoryController::class, 'index']);
    Route::post('/control-room-ao/categories', [CategoryController::class, 'store']);
    Route::put('/control-room-ao/categories/{category}', [CategoryController::class, 'update']);
    Route::delete('/control-room-ao/categories/{category}', [CategoryController::class, 'destroy']);

    Route::get('/control-room-ao/tasks', [TaskController::class, 'index']);
    Route::post('/control-room-ao/tasks', [TaskController::class, 'store']);
    Route::put('/control-room-ao/tasks/{task}', [TaskController::class, 'update']);
    Route::delete('/control-room-ao/tasks/{task}', [TaskController::class, 'destroy']);

    Route::post('/control-room-ao/tasks/{task}/timer/start', [TimeLogController::class, 'start']);
    Route::post('/control-room-ao/tasks/{task}/timer/stop', [TimeLogController::class, 'stop']);

    Route::get('/control-room-ao/reports', [ReportController::class, 'show']);

    Route::get('/control-room-ao/reflections', [ReflectionController::class, 'show']);
    Route::put('/control-room-ao/reflections', [ReflectionController::class, 'upsert']);
});
