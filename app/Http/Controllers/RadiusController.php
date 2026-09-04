<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

class RadiusController extends Controller
{
    public function authorize(Request $request)
    {
        $userName   = $request->input('User-Name');
        $password   = $request->input('User-Password');
        $stationId  = $request->input('Calling-Station-Id');
        $nasIp      = $request->input('NAS-IP-Address');

        if (!App::isProduction()) {
            Log::info("RADIUS Auth attempt: {$userName} from MAC {$stationId}");
        }

        // 1. Perform business logic (Check voucher status, user balance, etc.)
        $accept = true;

        if (!$accept) {
            // Non-2xx response tells FreeRADIUS to issue an Access-Reject
            return response()->json(['error' => 'Invalid voucher or expired time'], 403);
        }

        // 2. Define Vendor-Specific Attributes (VSAs)
        $mikrotikAttributes = [
            'Rate-Limit' => '2M/5M', // Upload/Download rate
        ];

        // Format keys to "reply:Mikrotik-Rate-Limit"
        $formattedMikrotik = collect($mikrotikAttributes)
            ->mapWithKeys(fn ($val, $key) => ["reply:Mikrotik-{$key}" => $val])
            ->toArray();

        // 3. Merge base RADIUS attributes with Vendor attributes
        $responsePayload = array_merge([
            'control:Auth-Type'     => 'Accept',
            'reply:Session-Timeout' => 3600, // 1 hour max session
            'reply:Idle-Timeout'    => 600,  // 10 mins idle cutoff
        ], $formattedMikrotik);

        return response()->json($responsePayload, 200);
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

        // Handle session tracking in database / Redis cache
        match ($statusType) {
            'Start'          => $this->handleSessionStart($sessionId, $userName, $stationId),
            'Interim-Update' => $this->handleSessionUpdate($sessionId, $sessionTime, $inputOctets, $outputOctets),
            'Stop'           => $this->handleSessionStop($sessionId, $sessionTime, $inputOctets, $outputOctets),
            default          => null,
        };

        return response()->json([], 200);
    }

    private function handleSessionStart(string $sessionId, string $userName, ?string $mac): void
    {
        // Record active session entry
    }

    private function handleSessionUpdate(string $sessionId, int $duration, int $uploadBytes, int $downloadBytes): void
    {
        // Deduct time/data quota or update live session stats
    }

    private function handleSessionStop(string $sessionId, int $duration, int $uploadBytes, int $downloadBytes): void
    {
        // Mark session closed and record final data consumption
    }
}