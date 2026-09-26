<?php

namespace App\Services;

use App\Models\InternetSession;
use App\Models\InternetVoucher;

class RadiusService extends Service
{
    public function handleAuthentication(string $username, string $password, string $mac, string $routerIp): InternetVoucher | string
    {
        // Perform business logic (Check voucher status, user balance, etc.)

        // Find voucher
        $voucher = InternetVoucher::query()
            ->where('username', $username)
            ->first();

        // Block if voucher not found
        if ($voucher == null) {
            return 'Voucher not found';
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
        if ($voucher->session_count != null && !$voucher->hasSessionWithMac($mac) && !$voucher->remainingSessionCount() == 0) {
            return 'Voucher device count exhausted';
        }

        // Activate voucher if not yet done
        if ($voucher->activated_at == null) {
            $voucher->activate();
        }

        // We can allow now
        return $voucher;
    }

    public function handleSessionStart(string $sessionId, string $userName, ?string $mac): void
    {
        // First, we get the voucher
        $voucherId = InternetVoucher::query()->select('id')->where('username', $userName)->firstOrFail();
        $session = InternetSession::query()->where('voucher_id', $voucherId)->where('mac_address', $mac)->firstOrFail();

        // If the session didn't exists yet, we first create one
        // This help linking mac to the voucher
        if ($session == null) {
            $session = new InternetSession();
            $session->voucher_id = $voucherId;
            $session->mac_address = $mac;
        }

        $session->id = $sessionId;
        $session->save();
    }

    public function handleSessionUpdate(string $sessionId, string $userName, int $duration, int $uploadBytes, int $downloadBytes): void
    {
        // Cache on Redis

        // First, we retrieve the voucher
        $voucher = InternetVoucher::where('username', $userName)->firstOrFail();

        // Deduct time/data quota or update live session stats
        $voucher->updateConsumption($uploadBytes, $downloadBytes);
    }

    public function handleSessionStop(string $sessionId, string $userName, int $duration, int $uploadBytes, int $downloadBytes): void
    {
        // Flush in DB
    }
}
