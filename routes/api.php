<?php

use App\Http\Controllers\Api\V1\CatalogueController;
use App\Http\Controllers\Api\V1\PrescriptionController;
use Illuminate\Support\Facades\Route;

/*
| No session or cookies: API keys only. Each route names the one scope it needs.
*/
Route::get('/medicines', [CatalogueController::class, 'index'])->middleware('api.key:catalogue.read')->name('api.medicines.index');
Route::get('/prescriptions/{id}', [PrescriptionController::class, 'show'])->whereNumber('id')->middleware('api.key:prescriptions.read')->name('api.prescriptions.show');
