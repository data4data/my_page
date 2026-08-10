<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DeveloperInquiryController;
use App\Http\Controllers\PortfolioController;
use Illuminate\Support\Facades\Route;

// Every path below renders the same Vue SPA shell (resources/views/app.blade.php);
// vue-router (resources/js/router.js) decides which page component to show.
Route::get('/', [PortfolioController::class, 'app']);
Route::get('/login', [PortfolioController::class, 'app'])->name('login')->middleware('guest');
Route::get('/hi-developer', [PortfolioController::class, 'app']);

// The real admin editor intentionally lives at an unlinked, non-obvious path
// (no button/link anywhere on the public page points at it) rather than
// /admin — reachable only by whoever already knows the URL.
Route::get('/control-room-ao', [PortfolioController::class, 'app'])->middleware(['auth', 'role:admin']);

Route::get('/portfolio', [PortfolioController::class, 'show']);
Route::post('/hi-developer', [DeveloperInquiryController::class, 'store'])->middleware('throttle:10,1');

Route::post('/login', [AuthController::class, 'store'])->name('login.attempt')->middleware(['guest', 'throttle:6,1']);
Route::post('/logout', [AuthController::class, 'destroy'])->name('logout')->middleware('auth');

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/control-room-ao/portfolio', [PortfolioController::class, 'edit']);
    Route::put('/control-room-ao/portfolio', [PortfolioController::class, 'update']);
    Route::post('/control-room-ao/portfolio/seed-defaults', [PortfolioController::class, 'seedDefaults']);
    Route::get('/control-room-ao/inquiries', [DeveloperInquiryController::class, 'index']);
});
