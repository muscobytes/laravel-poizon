<?php

namespace Muscobytes\Poizon\Enums;

enum AuthorizationReason: string
{
    case Missing = 'missing_connection';
    case Rejected = 'access_rejected';
    case RefreshExpired = 'refresh_expired';
    case RefreshUncertain = 'refresh_outcome_unknown';
}
