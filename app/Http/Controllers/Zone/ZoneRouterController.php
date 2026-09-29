<?php

namespace App\Http\Controllers\Zone;

use App\Http\Controllers\Controller;
use App\Models\ZoneRouter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ZoneRouterController extends Controller
{
    private const WIREGUARD_BASE_IP = "100.64.0.0";
    private const WIREGUARD_NET_MASK = "16";
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(ZoneRouter $zoneRouter)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ZoneRouter $zoneRouter)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ZoneRouter $zoneRouter)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ZoneRouter $zoneRouter)
    {
        //
    }

    public function generateSetupScript(string $ownerId, string $zoneId, ZoneRouter $router)
    {
        if (!$router->wg_address) {
            $offset = Cache::increment('wireguard:ip:offset');
            $baseLong = ip2long(self::WIREGUARD_BASE_IP) + 20; // We leave the 20 first address for potential future use
            $router->wg_address = long2ip($baseLong + $offset);
            $router->save();
        }

        // 1. Build the RouterOS Script Content
        $scriptContent = $this->buildMikrotikScript($router->id, $router->wg_address);

        // 2. Return as downloadable/plain-text RouterOS script (.rsc)
        $filename = "omnispot-setup-{$router->id}.rsc";

        return response($scriptContent, 200, [
            'Content-Type'        => 'text/plain; charset=utf-8',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Construct the RouterOS command commands.
     */
    private function buildMikrotikScript(string $id, string $wgIp): string
    {
        $baseLong = ip2long(self::WIREGUARD_BASE_IP);

        $wgHost = long2ip($baseLong + 1);
        $wgPort = 51820;
        $wgKey = 'YOUR_SERVER_WIREGUARD_PUBLIC_KEY_HERE';
        $wgIpMask = self::WIREGUARD_BASE_IP . '/' . self::WIREGUARD_NET_MASK;

        $radiusHost = long2ip($baseLong + 2);
        $authPort = 1812;
        $acctPort = 1813;
        $secret = '...';

        $trustedDomain = '*' . config('app.domain', 'commander15.com');

        return <<<ROUTEROS
# ==============================================================================
# OmniSpot MikroTik Auto-Configuration Script
# Generated for Router ID: {$id}
# ==============================================================================

:log info "Starting OmniSpot Wi-Fi Hotspot & RADIUS Configuration..."

# ------------------------------------------------------------------------------
# 1. WIREGUARD VPN SETUP
# ------------------------------------------------------------------------------
/interface wireguard
add name=wg-omnispot listen-port=13231 comment="OmniSpot RADIUS VPN Tunnel"

/interface wireguard peers
add interface=wg-omnispot \
    public-key="{$wgKey}" \
    endpoint-address="{$wgHost}" \
    endpoint-port={$wgPort} \
    allowed-address={$wgIpMask} \
    persistent-keepalive=25 \
    comment="OmniSpot Server Peer"

/ip address
add address={$wgIp} interface=wg-omnispot comment="OmniSpot WG IP"


# ------------------------------------------------------------------------------
# 2. RADIUS SERVER CONFIGURATION
# ------------------------------------------------------------------------------
# Clean up existing RADIUS entries for hotspot
/radius remove [find service=hotspot]

# Add OmniSpot RADIUS Server over WireGuard
/radius
add service=hotspot \
    address={$radiusHost} \
    secret="{$secret}" \
    auth-port={$authPort} \
    acct-port={$acctPort} \
    timeout=3000ms \
    called-format=mac \
    comment="OmniSpot RADIUS Server"

# Enable Incoming RADIUS CoA / Packet of Disconnect (PoD)
/radius incoming
set accept=yes port=3799


# ------------------------------------------------------------------------------
# 3. HOTSPOT SERVER PROFILE CONFIGURATION
# ------------------------------------------------------------------------------
# Configure Hotspot Profile to use RADIUS
/ip hotspot profile
set [find default=yes] \
    use-radius=yes \
    radius-accounting=yes \
    radius-interim-update=1m \
    radius-mac-format=XX:XX:XX:XX:XX:XX \
    radius-location-id="{$id}" \
    login-by=http-pap,cookie


# ------------------------------------------------------------------------------
# 4. WALLED GARDEN (ALLOW UNAUTHENTICATED TRAFFIC)
# ------------------------------------------------------------------------------
/ip hotspot walled-garden
add dst-host=*{$trustedDomain} comment="OmniSpot Cloud API & Portal"

:log info "OmniSpot Configuration successfully applied!"
ROUTEROS;
    }
}
