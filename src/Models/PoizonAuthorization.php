<?php

namespace Muscobytes\Poizon\Models;

use Illuminate\Database\Eloquent\Model;

class PoizonAuthorization extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['access_token', 'refresh_token', 'refresh_attempt'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'access_token_expires_at' => 'immutable_datetime',
            'refresh_token_expires_at' => 'immutable_datetime',
            'problem_notified_at' => 'immutable_datetime',
            'refresh_pending' => 'boolean',
        ];
    }
}
