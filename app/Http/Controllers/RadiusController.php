<?php

namespace App\Http\Controllers;

use App\Models\ZoneRouter;
use App\Services\RadiusService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RadiusController extends Controller
{
    // Cap unlimited bandwidth to 100 Mbps
    static private int $unlimitedMbps = 100;

    public function __construct(private RadiusService $radius) {
    }

    public function authorize(Request $request)
    {
        Log::info($request->getContent()); // Debug only

        $data = $this->radiusData($request, [
            'User-Name' => 'string|required',
            'User-Password' => 'string|required',
            'Calling-Station-Id' => 'string|required',
            'NAS-IP-Address' => 'string|required',
            'Called-Station-Id' => 'uuid|required:exists:zone_routers,id',
        ]);

        $calledStationId = $data['Called-Station-Id'];
        $zoneId = Cache::remember("radius:zone_id:{$calledStationId}", Carbon::now()->addMinutes(30), function() use ($data) {
            return ZoneRouter::where('id', $data['Called-Station-Id'])->value('zone_id');
        });

        $userName = $data['User-Name'];
        $userPassword = $data['User-Password'];
        $userMac  = $data['Calling-Station-Id'];
        $routerIp = $data['NAS-IP-Address'];

        if (!App::isProduction()) {
            Log::info("RADIUS Auth attempt: {$userName} from MAC {$userMac} though router {$routerIp}");
        }

        $result = $this->radius->handleAuthentication($userName, $userPassword, $userMac, $routerIp);
        if (is_string($result)) {
            // Tells FreeRADIUS to issue an Access-Reject
            return $this->radiusResponse([ 'Auth-Type' => 'Reject' ], [ 'Reply-Message' => $result ]);
        }

        $voucher = $result;
        $reply = [
            'Mikrotik-Rate-Limit' => $voucher->speedRates($this->unlimitedMbps), // Upload/Download rate
            'Session-Timeout' => $voucher->remainingSeconds(),  // duration limit
            'Idle-Timeout'    => 600,  // 10 mins idle cutoff
        ];

        // Data Limit
        if ($voucher->remaining_bytes != null) {
            array_push($reply, [ 'Mikrotik-Recv-Limit' => $voucher->remaining_bytes ]);
        }

        return $this->radiusResponse([ 'Auth-Type' => 'Accept' ], $reply);
    }

    public function accounting(Request $request)
    {
        $userName     = $request->input('User-Name');
        $statusType   = $request->input('Acct-Status-Type'); // Start, Interim-Update, or Stop
        $sessionId    = $request->input('Acct-Session-Id');
        $sessionTime  = (int) $request->input('Acct-Session-Time', 0); // Total seconds
        $inputOctets  = (int) $request->input('Acct-Input-Octets', 0);  // Bytes uploaded
        $outputOctets = (int) $request->input('Acct-Output-Octets', 0); // Bytes downloaded
        $stationId    = $request->input('Calling-Station-Id');          // MAC Address

        if (!App::isProduction()) {
            Log::info("RADIUS Acct [{$statusType}]: {$userName} - Time: {$sessionTime}s - Down: " . round($outputOctets / 1048576, 2) . "MB");
        }

        // ToDo: router identification check here

        // Handle session tracking in database / Redis cache
        match ($statusType) {
            'Start'          => $this->radius->handleSessionStart($sessionId, $userName, $stationId),
            'Interim-Update' => $this->radius->handleSessionUpdate($sessionId, $userName, $sessionTime, $inputOctets, $outputOctets),
            'Stop'           => $this->radius->handleSessionStop($sessionId, $userName, $sessionTime, $inputOctets, $outputOctets),
            default          => null,
        };

        return response()->json([], 200);
    }

    private function radiusData(Request $request, array $rules): array {
        // 1. Intercept and flatten all incoming RADIUS array wrappers into plain strings
        $flattenedData = collect($request->all())->map(function ($item) {
            if (is_array($item) && isset($item['value'])) {
                // Extract the first item from the nested value array
                return data_get($item, 'value.0');
            }
            return $item;
        })->toArray();

        // 2. Replace the request payload with our cleaned-up, flat data array
        $request->replace($flattenedData);
        return $request->validate($rules);
    }

    private function radiusResponse(array $control, array $reply, int $status = 200): JsonResponse {
        return response()->json([ 'Control' => $control, 'Reply' => $reply, ], $status);
    }
}
