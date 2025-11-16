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

    public function decode(string $payload): mixed
    {
        $offset = 0;
        $value = $this->decodeValue($payload, $offset);

        return $value;
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
     * @param  array<int, mixed>  $values
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
     * @param  array<string, mixed>  $dictionary
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

    private function decodeValue(string $payload, int &$offset): mixed
    {
        if ($offset >= strlen($payload)) {
            throw new InvalidArgumentException('Unexpected end of payload while decoding bencode.');
        }

        $indicator = $payload[$offset];

        if ($indicator === 'i') {
            return $this->decodeInteger($payload, $offset);
        }

        if ($indicator === 'l') {
            return $this->decodeList($payload, $offset);
        }

        if ($indicator === 'd') {
            return $this->decodeDictionary($payload, $offset);
        }

        if (ctype_digit($indicator)) {
            return $this->decodeStringValue($payload, $offset);
        }

        throw new InvalidArgumentException('Invalid bencode payload.');
    }

    private function decodeInteger(string $payload, int &$offset): int
    {
        $end = strpos($payload, 'e', $offset);

        if ($end === false) {
            throw new InvalidArgumentException('Invalid integer encoding.');
        }

        $number = substr($payload, $offset + 1, $end - $offset - 1);
        $offset = $end + 1;

        return (int) $number;
    }

    private function decodeStringValue(string $payload, int &$offset): string
    {
        $colon = strpos($payload, ':', $offset);

        if ($colon === false) {
            throw new InvalidArgumentException('Invalid string length encoding.');
        }

        $length = (int) substr($payload, $offset, $colon - $offset);
        $offset = $colon + 1;

        $value = substr($payload, $offset, $length);
        $offset += $length;

        return $value;
    }

    /**
     * @return array<int, mixed>
     */
    private function decodeList(string $payload, int &$offset): array
    {
        $offset++;

        $items = [];

        while ($offset < strlen($payload) && $payload[$offset] !== 'e') {
            $items[] = $this->decodeValue($payload, $offset);
        }

        if ($offset >= strlen($payload)) {
            throw new InvalidArgumentException('List not terminated.');
        }

        $offset++;

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeDictionary(string $payload, int &$offset): array
    {
        $offset++;

        $items = [];

        while ($offset < strlen($payload) && $payload[$offset] !== 'e') {
            $key = $this->decodeStringValue($payload, $offset);
            $items[$key] = $this->decodeValue($payload, $offset);
        }

        if ($offset >= strlen($payload)) {
            throw new InvalidArgumentException('Dictionary not terminated.');
        }

        $offset++;

        return $items;
    }
}
