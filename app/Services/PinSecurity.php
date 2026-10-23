<?php

namespace App\Services;

use Illuminate\Validation\Rule;

class PinSecurity
{
    private const LOCAL_BOOTSTRAP_PIN = '4868';

    public static function normalize(mixed $pin): mixed
    {
        if (! is_string($pin)) {
            return $pin;
        }

        return strtr($pin, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }

    public static function isValid(mixed $pin): bool
    {
        return is_string($pin) && preg_match('/\A[0-9]{4,12}\z/', $pin) === 1;
    }

    public static function bootstrapPin(): ?string
    {
        $configured = config('uja.bootstrap_pin');
        if ($configured === null || $configured === '') {
            return app()->environment('local', 'testing') ? self::LOCAL_BOOTSTRAP_PIN : null;
        }

        $pin = self::normalize($configured);

        return self::isValid($pin) ? $pin : null;
    }

    public static function bootstrapPinRules(): array
    {
        $pin = self::bootstrapPin();

        return $pin === null ? [] : [Rule::notIn([$pin])];
    }

    public static function fingerprint(string $hash): string
    {
        return hash('sha256', $hash);
    }
}
