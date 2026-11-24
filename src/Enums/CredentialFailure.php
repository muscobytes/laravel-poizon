<?php

namespace Muscobytes\Poizon\Enums;

enum CredentialFailure: string
{
    case LockTimeout = 'lock_timeout';
    case LockLost = 'lock_lost';
    case Database = 'database';
    case Encryption = 'encryption';
}
