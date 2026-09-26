<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\InternetBundle;
use App\Models\InternetVoucher;
use App\Models\Zone;
use Illuminate\Http\Request;

class ZoneController extends Controller
{
    public function showZone(Request $request, string $zone_id)
    {
        $zone = Zone::findOrFail($zone_id);

        if ($request->input('with_packages', false)) {
            $zone->load('packages.bundles');
        }

        return $zone;
    }

    public function indexBundles(string $zone_id)
    {
        sleep(5);
        $zone = Zone::findOrFail($zone_id);
        return response()->json($zone->packages[0]->bundles, 200, [ 'Cache-C' => 'public,max-age' ]);
    }

    public function indexVouchers(Request $request, string $zone_id)
    {
        return InternetVoucher::where('zone_id', $zone_id)
            ->where('phone_number', $request->input('phone'))
            ->get();
    }

    public function purchaseVoucher(Request $request, string $zone_id)
    {
        return response();
    }
}
