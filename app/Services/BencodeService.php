<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

class BencodeService
{
    public function encode(mixed $value): string
    {
        if (is_int($value)) {
            return $this->encodeInteger($value);
        }

        if (is_bool($value)) {
            return $this->encodeInteger($value ? 1 : 0);
        }

        if (is_string($value)) {
            return $this->encodeString($value);
        }

        if (is_array($value)) {
            return array_is_list($value)
                ? $this->encodeList($value)
                : $this->encodeDictionary($value);
        }

        throw new InvalidArgumentException('Unsupported type for bencode encoding.');
    }

    private function encodeInteger(int $value): string
    {
        return 'i'.$value.'e';
    }

    private function encodeString(string $value): string
    {
        return strlen($value).':'.$value;
    }

    /**
     * @param array<int, mixed> $values
     */
    private function encodeList(array $values): string
    {
        $encoded = 'l';

        foreach ($values as $value) {
            $encoded .= $this->encode($value);
        }

        return $encoded.'e';
    }

    /**
     * @param array<string, mixed> $dictionary
     */
    private function encodeDictionary(array $dictionary): string
    {
        ksort($dictionary, SORT_STRING);

        $encoded = 'd';

        foreach ($dictionary as $key => $value) {
            $encoded .= $this->encodeString((string) $key);
            $encoded .= $this->encode($value);
        }

        return $encoded.'e';
    }
}
