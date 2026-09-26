<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Hidden(['package_id'])]
#[Fillable(['number', 'name', 'price', 'short_code', 'description', 'up_mbps', 'down_mbps', 'limit_mbs', 'device_count', 'duration_hours', 'starts_at', 'ends_at', 'status'])]
class InternetBundle extends Model
{
    use HasUuids;

    public function getForeignKey(): string {
        return 'bundle_' . $this->getKeyName();
    }
}
