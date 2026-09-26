<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

#[Hidden(['zone_id'])]
#[Fillable(['name', 'net_address', 'mac_address', 'description'])]
class ZoneRouter extends Model
{
}
