<?php

namespace App\Models;

use Illuminate\Console\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Hidden(['package_id'])]
#[Fillable(['name', 'price', 'description', 'up_mbps', 'down_mbps', 'limit_mb', 'duration_hours', 'status'])]
class Bundle extends Model
{
    use HasUuids;
}
