<?php

declare(strict_types=1);

namespace EdgeCache\Support;

final class Signature
{
    public static function verify(string $rawBody, string $signatureHeader, string $secret): bool
    {
        if ($secret === '') {
            return true;
        }

        if ($signatureHeader === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expected, trim($signatureHeader));
    }
}
