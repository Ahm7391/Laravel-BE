<?php

use App\Http\Controllers\AnalyticsDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing');
})->name('home');

Route::get('/analytics-dashboard', [AnalyticsDashboardController::class, 'index'])->name('analytics.dashboard');
Route::post('/analytics-dashboard/request', [AnalyticsDashboardController::class, 'requestAnalytics'])->name('analytics.request');
