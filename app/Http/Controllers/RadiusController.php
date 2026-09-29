<?php

namespace App\Http\Controllers;

use App\Models\ZoneRouter;
use App\Services\RadiusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

class RadiusController extends Controller
{
    // Cap unlimited bandwidth to 100 Mbps
    private const UNLIMITED_MBPS = 100;

    public function __construct(private RadiusService $radius) {
    }

    public function authorize(Request $request)
    {
        $data = $this->radiusData($request, [
            'User-Name' => 'string|required',
            'User-Password' => 'string|required',
            'Calling-Station-Id' => 'string|required',
            'Called-Station-Id' => 'uuid|required:exists:zone_routers,id',
        ]);

        $calledStationId = $data['Called-Station-Id'];

        $userName = $data['User-Name'];
        $userPassword = $data['User-Password'];
        $userMac  = $data['Calling-Station-Id'];
        $routerId = $data['Called-Station-Id'];

        if (!App::isProduction()) {
            Log::info("RADIUS Auth attempt: {$userName} from MAC {$userMac} though router {$routerId}");
        }

        $result = $this->radius->authenticate($userName, $userPassword, $userMac, $routerId);
        if (is_string($result)) {
            // Tells FreeRADIUS to issue an Access-Reject
            return $this->radiusResponse([ 'Auth-Type' => 'Reject' ], [ 'Reply-Message' => $result ]);
        }

        $voucher = $result;
        $reply = [
            'Mikrotik-Rate-Limit' => $voucher->speedRates(self::UNLIMITED_MBPS), // Upload/Download rate
            'Session-Timeout' => $voucher->remainingSeconds(),  // duration limit
            'Idle-Timeout'    => 600,  // 10 mins idle cutoff
        ];

        // Data Limit
        if ($voucher->remaining_bytes) {
            $reply = array_merge($reply, [ 'Mikrotik-Recv-Limit' => $voucher->remaining_bytes ]);
        }

        return $this->radiusResponse([ 'Auth-Type' => 'Accept' ], $reply);
    }

    public function accounting(Request $request)
    {
        $data = $this->radiusData($request, [
            'User-Name' => 'string|required',
            'Acct-Status-Type' => 'required|string|in:Start,Interim-Update,Stop',
            'Calling-Station-Id' => 'required|string',
            'Called-Station-Id' => 'required|uuid|exists:zone_routers,id',
            'Acct-Session-Id' => 'required|string',
            'Acct-Session-Time' => 'integer|required',
            'Acct-Input-Octets' => 'integer|required',
            'Acct-Output-Octets' => 'integer|required',
            'Acct-Terminate-Cause' => 'string',
        ]);

        $userName     = $data['User-Name'];
        $statusType   = $data['Acct-Status-Type'];   // Start, Interim-Update, or Stop
        $sessionId    = $data['Acct-Session-Id'];
        $sessionTime  = $data['Acct-Session-Time'];  // Total seconds
        $inputOctets  = $data['Acct-Input-Octets'];  // Bytes uploaded
        $outputOctets = $data['Acct-Output-Octets']; // Bytes downloaded
        $mac          = $data['Calling-Station-Id']; // MAC Address
        $routerId     = $data['Called-Station-Id'];

        if (!App::isProduction()) {
            Log::info("RADIUS Acct [{$statusType}]: {$userName} - Time: {$sessionTime}s - Down: " . round($outputOctets / 1048576, 2) . "MB");
        }

        // Handle session tracking in database / Redis cache
        match ($statusType) {
            'Start'          => $this->radius->startSession($sessionId, $userName, $mac, $routerId),
            'Interim-Update' => $this->radius->updateSession($sessionId, $routerId, $sessionTime, $inputOctets, $outputOctets),
            'Stop'           => $this->radius->stopSession($sessionId, $routerId, $sessionTime, $inputOctets, $outputOctets),
            default          => null,
        };

        return response("{}", 200, [ 'Content-Type' => 'application/json' ]);
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
        $prefixedControl = [];
        foreach ($control as $key => $value) {
            $prefixedControl["control:{$key}"] = $value;
        }

        $prefixedReply = [];
        foreach ($reply as $key => $value) {
            $prefixedReply["reply:{$key}"] = $value;
        }

        return response()->json(array_merge($prefixedControl, $prefixedReply), $status);
    }
}
