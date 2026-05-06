<?php

use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\CompanyLookupController;
use App\Http\Controllers\Api\TimeEntryController;
use Illuminate\Support\Facades\Route;

Route::get('/companies', [CompanyController::class, 'index']);
Route::get('/companies/{company}/lookups', [CompanyLookupController::class, 'show']);

Route::get('/time-entries', [TimeEntryController::class, 'index']);
Route::post('/time-entries/bulk', [TimeEntryController::class, 'store']);
Route::put('/time-entries/{timeEntry}', [TimeEntryController::class, 'update']);
