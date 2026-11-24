<?php

namespace Muscobytes\Poizon\Internal;

final class Redactor
{
    public static function clean(array $data, array $secrets): array
    {
        $safe = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && (in_array(strtolower(str_replace('_', '', $key)), [
                'appkey', 'appsecret', 'accesstoken', 'refreshtoken', 'authorization', 'sign', 'envelope', 'requestpayload', 'requestbody',
            ], true) || str_replace($secrets, '', $key) !== $key)) {
                continue;
            }
            $safe[$key] = is_array($value) ? self::clean($value, $secrets)
                : (is_string($value) ? str_replace($secrets, '[redacted]', $value) : $value);
        }

        return $safe;
    }
}
