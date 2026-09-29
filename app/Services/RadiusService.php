<?php

namespace App\Services;

use App\Models\InternetSession;
use App\Models\InternetVoucher;
use App\Models\ZoneRouter;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class RadiusService extends Service
{
    private const ROUTER_DATA_TIMEOUT = 3600; // 1 HOUR
    private const VOUCHER_EXPIRY_TIMEOUT = 3600; // 1 HOUR
    private const SESSION_EXPIRY_TIMEOUT = 3600; // 1 HOUR
    private const USAGE_FLUSH_INTERVAL = 30; // 30 MINUTES
    public function authenticate(string $username, string $password, string $mac, string $routerId): InternetVoucher | string
    {
        // Perform business logic (Check voucher status, user balance, etc.)

        // Find voucher
        $voucher = $this->findVoucherByUserName($username, $routerId);

        // Block if voucher not found
        if ($voucher == null) {
            return 'Voucher not found: ' . $this->findZoneIdByRouter($routerId) ?? -1;
        }

        // Block if bad password
        if (!$voucher->checkPassword($password)) {
            return 'Bad password';
        }

        // Block expired vouchers
        if ($voucher->isExpired()) {
            return 'Voucher expired';
        }

        // Block exhausted vouchers
        if ($voucher->isExhausted()) {
            return 'Data exhausted';
        }

        // Block if voucher has session count limit, mac is not yet registered and no session remains
        if ($voucher->session_count != null) {
            if (!$voucher->hasSessionWithMac($mac) && $voucher->remainingSessionCount() == 0) {
                return 'Voucher device count exhausted ' . $voucher->remainingSessionCount();
            }
        }

        // Activate voucher if not yet done
        if ($voucher->activated_at == null) {
            $voucher->activate();
        }

        // We can allow now
        return $voucher;
    }

    public function startSession(string $sessionId, string $userName, ?string $mac, ?string $routerId): void
    {
        // First, we get the voucher and session (if available)
        $voucher = $this->findVoucherBySession($sessionId, $mac, $routerId) ?? $this->findVoucherByUserName($userName, $routerId);
        if (!$voucher) return;

        $session = InternetSession::query()->where('voucher_id', $voucher->id)->where('mac_address', $mac)->firstOrFail();

        // If the session didn't exists yet, we first create one
        // This help linking mac to the voucher
        if ($session == null) {
            $session = new InternetSession();
            $session->voucher_id = $voucher->id;
            $session->mac_address = $mac;
        }

        $session->session_id = $sessionId;
        $session->save();

        Cache::put("radius:router:{$routerId}:session:{$sessionId}:voucher", $voucher, self::SESSION_EXPIRY_TIMEOUT);
    }

    public function updateSession(string $sessionId, string $routerId, int $duration, int $uploadBytes, int $downloadBytes): int
    {
        // Cache Keys
        $uploadedBytesKey = "radius:router:{$routerId}:session:{$sessionId}:bytes_up";
        $downloadedBytesKey = "radius:router:{$routerId}:session:{$sessionId}:bytes_down";
        $throttleKey = "radius:router:{$routerId}:session:{$sessionId}:flush_lock";

        // 1. Store usage
        Cache::put($uploadBytes, $uploadBytes, self::SESSION_EXPIRY_TIMEOUT);
        Cache::put($downloadBytes, $downloadBytes, self::SESSION_EXPIRY_TIMEOUT);

        // 2. Throttled Database Flush (Executes at most once every x minutes)
        // Cache::add() sets key ONLY if it doesn't already exist
        $shouldFlushToDb = Cache::add($throttleKey, true, now()->addMinutes(self::USAGE_FLUSH_INTERVAL));

        if ($shouldFlushToDb) {
            $voucher = $this->findVoucherBySession($sessionId, null, $routerId);
            $voucher->updateConsumption($uploadBytes, $downloadBytes);
            return 1;
        }

        return 0;
    }

    public function stopSession(string $sessionId, string $routerId, int $duration, int $uploadBytes, int $downloadBytes): void
    {
        // Flush in DB
        if ($this->updateSession($sessionId, $routerId, $duration, $uploadBytes, $downloadBytes) == 1) {
            $this->flushSession($sessionId, $routerId);
        }
    }

    private function findVoucherByUserName(string $username, string $routerId): InternetVoucher|null 
    {
        // Find the zone
        $zoneId = $this->findZoneIdByRouter($routerId);

        // Find voucher
        $voucherId = Cache::remember("radius:zone:{$zoneId}:voucher:{$username}:id", self::VOUCHER_EXPIRY_TIMEOUT, function() use ($zoneId, $username) {
            return InternetVoucher::query()
                ->where('zone_id', $zoneId)
                ->where('username', $username)
                ->value('id');
        });

        // Return voucher
        return $voucherId ? InternetVoucher::find($voucherId) : null;
    }

    private function findVoucherBySession(string $sessionId, ?string $mac, string $routerId): ?InternetVoucher
    {
        // Cache ONLY the Voucher ID (scalar integer/UUID)
        $cacheKey = "radius:router:{$routerId}:session:{$sessionId}:voucher_id";

        $voucherId = Cache::get($cacheKey);
        if ($voucherId === null) {
            $voucherId = InternetSession::query()
                ->where('session_id', $sessionId)
                ->when($mac, fn ($query) => $query->where('mac_address', $mac))
                ->value('voucher_id');

            // Only cache if a non-null voucher ID was retrieved
            if ($voucherId !== null) {
                Cache::put($cacheKey, $voucherId, self::VOUCHER_EXPIRY_TIMEOUT);
            }
        }

        // Always fetch a fresh Model instance from DB to reflect latest quota & status
        return InternetVoucher::find($voucherId);
    }

    private function findZoneIdByRouter(string $routerId): ?string
    {
        return Cache::remember("radius:router:{$routerId}:zone_id", self::ROUTER_DATA_TIMEOUT, function () use ($routerId) {
            $zoneId = ZoneRouter::where('id', $routerId)->value('zone_id');
            return $zoneId !== null ? (string) $zoneId : null;
        });
    }
    
    private function flushSession(string $sessionId, string $routerId): void
    {
        // Cache Keys
        $uploadedBytesKey = "radius:router:{$routerId}:session:{$sessionId}:bytes_up";
        $downloadedBytesKey = "radius:router:{$routerId}:session:{$sessionId}:bytes_down";

        // Retrieve accumulated buffer
        $up = (int) Cache::get($uploadedBytesKey, 0);
        $dw = (int) Cache::get($downloadedBytesKey, 0);

        if ($up + $dw <= 0) {
            return;
        }

        // Reset buffer counter immediately
        Cache::forget([$uploadedBytesKey, $downloadedBytesKey]);

        $voucher = $this->findVoucherBySession($sessionId, null, $routerId);
        if ($voucher) {
            // Atomic DB update
            $voucher->updateConsumption($up, $dw);

            // Check if voucher has depleted its total quota
            if ($voucher->remaining_bytes <= 0) {
                $voucher->update(['status' => 'depleted']);

                // Multi-device disconnect job trigger
                if ($voucher->device_count > 1) {
                    //$this->dispatchPoDForVoucher($voucher);
                }
            }
        }
    }
}
