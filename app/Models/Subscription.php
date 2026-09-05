<?php

namespace App\Models;

use Illuminate\Console\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Hidden(['zone_id', 'bundle_id'])]
#[Fillable(['username', 'password', 'price', 'ip_address', 'mac_address', 'bytes_up', 'bytes_down', 'session_id', 'session_duration', 'expires_at'])]
class Subscription extends Model
{
    use HasUuids;

    public function isExpired(Carbon $now = null): bool {
        if ($now == null) $now = Carbon::now();
        return $now->greaterThan($this->expires_at); 
    }

    public function isExhausted(): bool {
        return $this->remainingBytes() == 0;
    }

    public function remainingBytes(): float | null {
        if ($this->bundle->limit_mb == null) return null;
        $total = $this->bundle->limit_mb * 1024 * 1024;
        $result = $total - $this->bytes_down;
        return $result <= 0.0 ? 0.0 : $result;
    }

    public function bundle(): BelongsTo {
        return $this->belongsTo(Bundle::class);
    }
}
