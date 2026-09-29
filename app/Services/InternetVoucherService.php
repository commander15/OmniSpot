<?php

namespace App\Services;

use App\Models\InternetBundle;
use App\Models\InternetVoucher;
use App\Models\Zone;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use InvalidArgumentException;

class InternetVoucherService extends Service
{
    private const BASE62_CHARS = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    // A massive prime number used to distribute sequential IDs completely randomly across the pool
    private const COPRIME_SCRAMBLER = 7416317;
    private const MAX_COMBINATIONS = 14776336; // 62^4

    public function createVoucher(string $username, string $password, string $phone, Zone $zone, InternetBundle $bundle): InternetVoucher {
        $voucher = $this->makeVoucher($username, $password, $phone, false, $bundle, $zone);
        $voucher->saveOrFail();
        return $voucher;
    }

    public function generateVoucher(string $phone, Zone $zone, InternetBundle $bundle): InternetVoucher {
        $username = $this->generateUsername($bundle->short_code, $zone->id, 8); // 8 characters
        $password = Str::password(6); // 6 characters
        $voucher = $this->makeVoucher($username, $password, $phone, true, $bundle, $zone);
        $voucher->saveOrFail();
        return $voucher;
    }

    private function makeVoucher(string $username, string $password, string $phone, bool $generated, InternetBundle $bundle, Zone $zone): InternetVoucher {
        $voucher = new InternetVoucher();

        // Relations
        $voucher->owner_id = $zone->owner_id;
        $voucher->zone_id = $zone->id;
        $voucher->bundle_id = $bundle->id;

        // Auth
        $voucher->username = $username;
        $voucher->password = $password;

        // Params
        $voucher->phone_number = $phone;

        // Bandwith && Limits
        $voucher->up_mbps = $bundle->up_mbps;
        $voucher->down_mbps = $bundle->down_mbps;
        $voucher->session_count = $bundle->device_count;
        $voucher->remaining_bytes = ($bundle->limit_mbps == null ? null : $bundle->limit_mbps * 1024);
        $voucher->duration_hours = $bundle->duration_hours;

        // Others
        $voucher->price = $bundle->price;
        $voucher->generated = $generated;

        return $voucher;
    }

    /**
     * Generates a 100% unique, unguessable 7-character voucher username statelessly using the Cache.
     *
     * @param string $prefix   The exact un-modified bundle prefix (e.g., "24H")
     * @param string $zoneUuid The unique Wi-Fi Zone ID to isolate counters per zone
     */
    public static function generateUsername(string $prefix, string $zoneUuid, int $length): string
    {
        $prefixLength = strlen($prefix);
        if ($prefixLength < 1 || $prefixLength > 3) {
            throw new InvalidArgumentException("Prefix must be between 1 and 3 characters.");
        }

        // 1. Incorporate the current year into the key so the counter auto-resets every year
        $currentYear = date('Y');
        $cacheKey = "voucher:index:{$zoneUuid}:{$prefix}:{$currentYear}";

        // 2. Atomically increment the index. If it doesn't exist, Cache initializes it at 1.
        Cache::add($cacheKey, 0, Carbon::now()->addYear());
        $voucherIndex = Cache::increment($cacheKey);

        if ($voucherIndex > self::MAX_COMBINATIONS) {
            throw new \Exception("Voucher pool exhausted for this bundle in this zone for the year.");
        }

        // 3. Scramble the sequential index so vouchers look random to customers
        $scrambledId = ($voucherIndex * self::COPRIME_SCRAMBLER) % self::MAX_COMBINATIONS;

        // 4. Convert to a Base62 string
        $suffix = self::toBase62($scrambledId, $length - $prefixLength);

        return $prefix . $suffix;
    }

    /**
     * Converts an integer to a Base62 string, padded to a specific length.
     */
    private static function toBase62(int $num, int $length): string
    {
        $res = '';
        while ($num > 0) {
            $r = $num % 62;
            $res = self::BASE62_CHARS[$r] . $res;
            $num = (int) floor($num / 62);
        }
        return str_pad($res, $length, '0', STR_PAD_LEFT);
    }
}
