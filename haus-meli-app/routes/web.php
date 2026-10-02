<?php

use App\Http\Controllers\E1001AgendaController;
use App\Http\Controllers\WeatherController;
use App\Http\Controllers\ElevationController;
use App\Http\Controllers\PlannedRouteShareController;
use App\Http\Controllers\SavedToursController;
use App\Http\Controllers\TripGpxController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('layouts.app');
});

Route::get('/trips/variants/{variant}/gpx', TripGpxController::class)
    ->name('trips.variant.gpx');

Route::post('/tours/planned/share', [PlannedRouteShareController::class, 'store'])
    ->name('tours.planned.share');
Route::get('/tours/planned/share', [PlannedRouteShareController::class, 'storeInfo'])
    ->name('tours.planned.share.info');
Route::get('/tours/share/{token}', [PlannedRouteShareController::class, 'show'])
    ->name('tours.share.show');
Route::get('/tours/share/{token}/gpx', [PlannedRouteShareController::class, 'gpx'])
    ->name('tours.share.gpx');

Route::get('/tours/saved', [SavedToursController::class, 'index'])
    ->name('tours.saved.index');
Route::post('/tours/saved', [SavedToursController::class, 'store'])
    ->name('tours.saved.store');
Route::delete('/tours/saved/{id}', [SavedToursController::class, 'destroy'])
    ->where('id', '[A-Za-z0-9_-]+')
    ->name('tours.saved.destroy');

Route::post('/tours/elevation', [ElevationController::class, 'lookup'])
    ->name('tours.elevation');

Route::get('/api/e1001/agenda', E1001AgendaController::class)
    ->name('e1001.agenda');

Route::get('/api/weather', [WeatherController::class, 'show'])
    ->name('weather.show');
Route::get('/api/e1001/weather', [WeatherController::class, 'terminal'])
    ->name('e1001.weather');

Route::get('/api/whoami', function () {
    return response()->json(\App\Support\ClientNetwork::debug());
});
