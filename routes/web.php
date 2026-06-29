<?php

use App\Http\Controllers\PortfolioController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PortfolioController::class, 'app']);
Route::get('/admin', [PortfolioController::class, 'app']);
Route::get('/portfolio', [PortfolioController::class, 'show']);
Route::get('/admin/portfolio', [PortfolioController::class, 'edit']);
Route::put('/admin/portfolio', [PortfolioController::class, 'update']);
Route::post('/admin/portfolio/seed-defaults', [PortfolioController::class, 'seedDefaults']);
