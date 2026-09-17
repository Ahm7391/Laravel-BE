<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AnalyticsDashboardController;
use App\Http\Controllers\Api\SeedDataController;
use App\Http\Controllers\Api\ForecastServiceController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:api')->get('/user', function (Request $request) {
//     return $request->user();
// });

Route::get('/health', function () {
  return response()->json(['status' => 'ok']);
});

# THIS IS ONLY FOR DATA SEEDING
Route::post('/dummy-bookings', [SeedDataController::class, 'store'])->name('storage.dummy-bookings');

# ROUTING FOR ANALYTICS DASHBOARD FEATURES DEMO
Route::post('/analytics-result/update', [AnalyticsDashboardController::class, 'updateStatusFromPipeline'])->name('api.analytics.update');
Route::post('/analytics-demo', [AnalyticsDashboardController::class, 'sendBack'])->name('api.analytics.demo');
Route::post('/dummy-records', [AnalyticsDashboardController::class, 'recordsProcess'])->name('api.dummy.records');

# ROUTING FOR FORECAST SERVICE FEATURE
Route::post('/forecast-demo', [ForecastServiceController::class, 'sendBack'])->name('api.forecast.demo');
Route::post('/prediction-result', [ForecastServiceController::class, 'predictionResult'])->name('api.prediction-result');