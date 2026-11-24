<?php

namespace Muscobytes\Poizon\Enums;

enum RequestState: string
{
    case NotSent = 'not_sent';
    case PossiblySent = 'possibly_sent';
    case ResponseReceived = 'response_received';
}
