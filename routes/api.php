<?php

use App\Http\Controllers\Owner\VoucherController;
use App\Http\Controllers\Owner\ZoneController;
use App\Http\Controllers\Public\ZoneController as PublicZoneController;
use App\Http\Controllers\RadiusController;
use App\Http\Controllers\Zone\ZoneRouterController;
use App\Http\Middleware\EnsureRadiusServerOnly;
use Illuminate\Support\Facades\Route;

Route::prefix('/v1')
    ->group(function() {
        Route::apiResource('/owners/{owner_id}/zones', ZoneController::class);
        Route::apiResource('/owners/{owner_id}/zones/{zone_id}/routers', ZoneRouterController::class);
        Route::get('/owners/{owner_id}/zones/{zone_id}/routers/{router}/setup-script', [ ZoneRouterController::class, 'generateSetupScript' ]);
        Route::apiResource('/owners/{owner_id}/vouchers', VoucherController::class);
    });

Route::get('/v1/payment-methods', function() { return [ [ 'name' => 'OM', 'logo_url' => '' ], [ 'name' => 'MoMo', 'logo_url' => '' ],  ]; });

Route::prefix('/v1/zones/{zone_id}')
    ->group(function() {
        Route::get('/', [PublicZoneController::class, 'showZone']);
        Route::get('/bundles', [PublicZoneController::class, 'indexBundles']);
        Route::post('/bundles/{bundle_id}/purchase', function() { return []; });
        Route::get('/vouchers', [PublicZoneController::class, 'indexVouchers']);
        Route::post('/vouchers', [PublicZoneController::class, 'purchaseVoucher']);
    });

Route::prefix('/radius')
    ->middleware(EnsureRadiusServerOnly::class)
    ->group(function() {
        Route::post('/authorize', [ RadiusController::class, 'authorize' ]);
        Route::post('/accounting', [ RadiusController::class, 'accounting' ]);
    });
