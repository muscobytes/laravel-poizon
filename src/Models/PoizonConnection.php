<?php

namespace Muscobytes\Poizon\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoizonConnection extends Model
{
    protected $guarded = ['id'];

    public function authorization(): BelongsTo
    {
        return $this->belongsTo(PoizonAuthorization::class, 'poizon_authorization_id');
    }
}
