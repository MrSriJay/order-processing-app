<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\KpiController;

Route::get('/', function () {
    return view('welcome');
});


// For curl testing using api client
Route::get('/kpis', [KpiController::class, 'index']);

// KPI Dashboard View
Route::get('/kpi-dashboard', function () {
    return view('kpi-dashboard');
});