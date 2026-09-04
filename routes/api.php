<?php

use App\Http\Controllers\RadiusController;
use Illuminate\Support\Facades\Route;

Route::prefix('/v1')
    ->group(function() {
        //
    });

Route::prefix('/radius')
    ->group(function() {
        Route::post('/authorize', [RadiusController::class, 'authorize']);
        Route::post('/accounting', [RadiusController::class, 'accounting']);
    });
