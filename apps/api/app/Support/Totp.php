<?php

namespace App\Support;

use InvalidArgumentException;

final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    public static function provisioningUri(string $secret, string $email, string $issuer = 'Vitaleevo'): string
    {
        $label = rawurlencode($issuer.':'.$email);
        $query = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => 30,
        ], '', '&', PHP_QUERY_RFC3986);

        return "otpauth://totp/{$label}?{$query}";
    }

    public static function code(string $secret, ?int $timestamp = null): string
    {
        $counter = intdiv($timestamp ?? time(), 30);
        $key = self::base32Decode($secret);
        $high = intdiv($counter, 4294967296);
        $low = $counter % 4294967296;
        $binaryCounter = pack('N2', $high, $low);
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($binary % 1000000), 6, '0', STR_PAD_LEFT);
    }

    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $now = time();
        for ($step = -$window; $step <= $window; $step++) {
            if (hash_equals(self::code($secret, $now + ($step * 30)), $code)) {
                return true;
            }
        }

        return false;
    }

    private static function base32Encode(string $data): string
    {
        $buffer = 0;
        $bitsLeft = 0;
        $output = '';

        foreach (unpack('C*', $data) as $byte) {
            $buffer = ($buffer << 8) | $byte;
            $bitsLeft += 8;
            while ($bitsLeft >= 5) {
                $bitsLeft -= 5;
                $output .= self::ALPHABET[($buffer >> $bitsLeft) & 31];
            }
            $buffer = $bitsLeft > 0 ? ($buffer & ((1 << $bitsLeft) - 1)) : 0;
        }

        if ($bitsLeft > 0) {
            $output .= self::ALPHABET[($buffer << (5 - $bitsLeft)) & 31];
        }

        return $output;
    }

    private static function base32Decode(string $value): string
    {
        $value = strtoupper(preg_replace('/[^A-Z2-7]/', '', $value) ?? '');
        if ($value === '') {
            throw new InvalidArgumentException('Segredo TOTP inválido.');
        }

        $buffer = 0;
        $bitsLeft = 0;
        $output = '';
        foreach (str_split($value) as $char) {
            $index = strpos(self::ALPHABET, $char);
            if ($index === false) {
                throw new InvalidArgumentException('Segredo TOTP inválido.');
            }
            $buffer = ($buffer << 5) | $index;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
                $buffer = $bitsLeft > 0 ? ($buffer & ((1 << $bitsLeft) - 1)) : 0;
            }
        }

        return $output;
    }
}
