<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Hidden(['owner_id', 'pivot'])]
#[Fillable(['name', 'description', 'status'])]
class InternetPackage extends Model
{
    use HasUuids;

    public function owner(): BelongsTo {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function zone(): BelongsTo {
        return $this->belongsTo(Zone::class);
    }

    public function bundles(): HasMany {
        return $this->hasMany(InternetBundle::class)
            ->orderBy('number', 'asc');
    }

    public function getForeignKey(): string {
        return 'package_' . $this->getKeyName();
    }
}
