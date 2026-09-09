<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AnalyticsDashboardController;
use App\Http\Controllers\Api\SeedDataController;

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

Route::post('/dummy-bookings', [SeedDataController::class, 'store'])->name('storage.dummy-bookings');
Route::post('/analytics-result/update', [AnalyticsDashboardController::class, 'updateStatusFromPipeline'])->name('api.analytics.update');
