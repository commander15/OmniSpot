<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

class RadiusController extends Controller
{
    public function authorize(Request $request)
    {
        $userName = $request->input('User-Name');
        $password = $request->input('User-Password');
        $userMac  = $request->input('Calling-Station-Id');
        $routerIp = $request->input('NAS-IP-Address');

        if (!App::isProduction()) {
            Log::info("RADIUS Auth attempt: {$userName} from MAC {$userMac} though router {$routerIp}");
        }

        $result = $this->authenticate($userName, $password, $userMac, $routerIp);
        if (is_string($result)) {
            // Non-2xx response tells FreeRADIUS to issue an Access-Reject
            return response()->json(['error' => $result], 403);
        }

        $bundle = $result->bundle;

        // 2. Define Vendor-Specific Attributes (VSAs)
        $mikrotikAttributes = [
            'Rate-Limit' => "{$bundle->up_mbps}M/{$bundle->down_mbps}M", // Upload/Download rate
            'Recv-Limit' => $result->remainingBytes(), // Download limit
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

    private function authenticate(string $username, string $password, string $mac, string $routerIp): Subscription | string
    {
        // Perform business logic (Check voucher status, user balance, etc.)

        // Find subscription
        $subscription = Subscription::query()
            ->with('bundle')
            ->where('username', $username)
            ->where('password', $password)
            ->first();

        // Block if subscription not found
        if ($subscription == null) {
            return 'Subscription not found';
        }

        // Block expired subscribers
        if ($subscription->isExpired()) {
            return 'Subscription expired';
        }

        // Block different MAC
        if ($subscription->mac_address != null && $subscription->mac_address != $mac) {
            return 'Device MAC address didn\'t match the subscription one';
        }

        // If no bundle linked, we block
        if ($subscription->bundle == null) {
            return 'No bundle linked to the subscription';
        }

        // We can allow now
        return $subscription;
    }

    private function handleSessionStart(string $sessionId, string $userName, ?string $mac): void
    {
        // Record active session entry
        $subscription = Subscription::where('username', $userName)->firstOrFail();
        $subscription->session_id = $sessionId;
        $subscription->mac_address = $mac;
        $subscription->save();
    }

    private function handleSessionUpdate(string $sessionId, int $duration, int $uploadBytes, int $downloadBytes): void
    {
        // Deduct time/data quota or update live session stats
        // Careful here: better leave for now
    }

    private function handleSessionStop(string $sessionId, int $duration, int $uploadBytes, int $downloadBytes): void
    {
        // Mark session closed and record final data consumption
        // We find the subcription by sessionId and *increment* to usage (maybe not the first network session)
        // Duration is irelevant for us cause even when offline, time pass, we have an expiration policy

        $subscription = Subscription::where('session_id', $sessionId)->firstOrFail();
        $subscription->session_duration = $duration;
        $subscription->bytes_up += $uploadBytes;
        $subscription->bytes_down += $downloadBytes;
        $subscription->save();
    }
}