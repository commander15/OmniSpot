<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Hidden(['owner_id'])]
#[Fillable(['name', 'phone', 'description', 'status'])]
class Zone extends Model
{
    use HasUuids;

    public function owner(): BelongsTo {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function routers(): HasMany {
        return $this->hasMany(ZoneRouter::class);
    }

    public function packages(): BelongsToMany {
        return $this->belongsToMany(InternetPackage::class, 'zone_packages');
    }

    public function vouchers(): HasMany {
        return $this->hasMany(InternetVoucher::class);
    }
}
