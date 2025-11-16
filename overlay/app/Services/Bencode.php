<?php

declare(strict_types=1);

namespace App\Services;

final class Bencode
{
    public static function encode(mixed $value): string
    {
        if (is_int($value)) {
            return 'i'.$value.'e';
        }

        if (is_string($value)) {
            return strlen($value).':'.$value;
        }

        if (is_array($value)) {
            $isList = array_keys($value) === range(0, count($value) - 1);

            if ($isList) {
                $encodedList = 'l';

                foreach ($value as $item) {
                    $encodedList .= self::encode($item);
                }

                return $encodedList.'e';
            }

            ksort($value, SORT_STRING);
            $encodedDictionary = 'd';

            foreach ($value as $key => $item) {
                $encodedDictionary .= self::encode((string) $key).self::encode($item);
            }

            return $encodedDictionary.'e';
        }

        return self::encode((string) $value);
    }
}
