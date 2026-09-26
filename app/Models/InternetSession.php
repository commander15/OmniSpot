<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Hidden(['voucher_id'])]
#[Fillable(['mac_address'])]
class InternetSession extends Model
{
    public function voucher(): BelongsTo {
        return $this->belongsTo(InternetVoucher::class);
    }

    public function getForeignKey(): string {
        return 'session_' . $this->getKeyName();
    }
}
