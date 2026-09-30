<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Hidden(['owner_id', 'zone_id', 'bundle_id'])]
#[Fillable(['username', 'password', 'phone_number', 'up_mbps', 'down_mbps', 'duration_hours', 'session_count', 'remaining_bytes', 'price', 'generated'])]
class InternetVoucher extends Model
{
    use HasUuids;

    public function isExpired(Carbon | null $now = null): bool {
        if ($now == null) $now = Carbon::now();
        return ($this->expires_at == null ? false : $now > $this->expires_at);
    }

    public function checkPassword(string $password): bool {
        return $this->password == $password;
    }

    public function isExhausted(): bool {
        return $this->remaining_bytes && $this->remaining_bytes <= 0;
    }

    public function hasSessionWithMac(string $mac): bool {
        return $this->sessions->contains('mac_address', $mac);
    }

    public function session(string $mac): InternetSession|null {
        return $this->sessions->find('mac_address', $mac);
    }

    public function remainingSessionCount(int $unlimitedSession = 100): int {
        $sessions = $this->sessions;
        $count = ($this->session_count ?? $unlimitedSession) - $sessions->count();
        return ($count <= 0 ? 0 : $count);
    }

    public function speedRates(int $unlimitedSpeed = 100): string {
        $up = $this->up_mbps ?? $unlimitedSpeed;
        $dw = $this->down_mbps ?? $unlimitedSpeed;
        return "{$up}M/{$dw}M";
    }

    public function remainingSeconds(): int {
        if ($this->expires_at == null) {
            return 2592000; // 30 days
        }

        return (int) Carbon::now()->diffInSeconds($this->expires_at, false);
    }

    public function activate(): bool {
        $now = Carbon::now();
        $this->activated_at = $now;

        if ($this->duration_hours != null) {
            $this->expires_at = $now->addHours($this->duration_hours);
        }

        return $this->save();
    }

    public function updateConsumption(int $uploadedBytes, int $downloadedBytes): bool {
        // If unlimited plan, we do nothing
        if ($this->remaining_bytes == null) {
            return false;
        }

        $this->remaining_bytes -= ($uploadedBytes + $downloadedBytes);
        if ($this->remaining_bytes < 0)
            $this->remaining_bytes = 0;

        return $this->save();
    }

    public function owner(): BelongsTo {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function zone(): BelongsTo {
        return $this->belongsTo(Zone::class);
    }

    public function bundle(): BelongsTo {
        return $this->belongsTo(InternetBundle::class);
    }

    public function sessions(): HasMany {
        return $this->hasMany(InternetSession::class);
    }

    public function getForeignKey(): string {
        return 'voucher_' . $this->getKeyName();
    }
}
