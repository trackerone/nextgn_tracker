<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

final class BencodeService
{
    /**
     * @param  mixed  $value
     */
    public function encode($value): string
    {
        if (is_int($value)) {
            return 'i'.$value.'e';
        }

        if (is_string($value)) {
            return strlen($value).':'.$value;
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                $out = 'l';
                foreach ($value as $item) {
                    $out .= $this->encode($item);
                }

                return $out.'e';
            }

            $keys = array_keys($value);
            foreach ($keys as $k) {
                if (! is_string($k)) {
                    throw new InvalidArgumentException('Dictionary keys must be strings.');
                }
            }

            sort($keys, SORT_STRING);

            $out = 'd';
            foreach ($keys as $k) {
                $out .= $this->encode($k);
                $out .= $this->encode($value[$k]);
            }

            return $out.'e';
        }

        throw new InvalidArgumentException('Unsupported type for bencode encode.');
    }

    /**
     * @return mixed
     */
    public function decode(string $payload)
    {
        $offset = 0;
        $len = strlen($payload);

        $value = $this->decodeValue($payload, $offset, $len);

        if ($offset !== $len) {
            throw new InvalidArgumentException('Trailing data after bencode value.');
        }

        return $value;
    }

    /**
     * @return mixed
     */
    private function decodeValue(string $payload, int &$offset, int $len)
    {
        if ($offset >= $len) {
            throw new InvalidArgumentException('Unexpected end of payload.');
        }

        $char = $payload[$offset];

        return match ($char) {
            'i' => $this->decodeInteger($payload, $offset, $len),
            'l' => $this->decodeList($payload, $offset, $len),
            'd' => $this->decodeDictionary($payload, $offset, $len),
            default => ($char >= '0' && $char <= '9')
                ? $this->decodeString($payload, $offset, $len)
                : throw new InvalidArgumentException('Invalid bencode value type at offset '.$offset.'.'),
        };
    }

    private function decodeInteger(string $payload, int &$offset, int $len): int
    {
        if ($offset >= $len || $payload[$offset] !== 'i') {
            throw new InvalidArgumentException('Invalid integer prefix.');
        }

        $offset++; // skip 'i'

        $end = strpos($payload, 'e', $offset);
        if ($end === false) {
            throw new InvalidArgumentException('Integer not terminated.');
        }

        $raw = substr($payload, $offset, $end - $offset);

        if ($raw === '' || ($raw !== '0' && $raw[0] === '0') || $raw === '-0') {
            throw new InvalidArgumentException('Invalid integer encoding.');
        }

        if (! preg_match('/^-?\d+$/', $raw)) {
            throw new InvalidArgumentException('Invalid integer encoding.');
        }

        $offset = $end + 1; // skip 'e'

        return (int) $raw;
    }

    private function decodeString(string $payload, int &$offset, int $len): string
    {
        $colon = strpos($payload, ':', $offset);
        if ($colon === false) {
            throw new InvalidArgumentException('String length delimiter missing.');
        }

        $rawLen = substr($payload, $offset, $colon - $offset);
        if ($rawLen === '' || ! ctype_digit($rawLen)) {
            throw new InvalidArgumentException('Invalid string length.');
        }

        $strLen = (int) $rawLen;
        $offset = $colon + 1;

        if ($offset + $strLen > $len) {
            throw new InvalidArgumentException('String exceeds payload length.');
        }

        $value = substr($payload, $offset, $strLen);
        $offset += $strLen;

        return $value;
    }

    /**
     * @return array<int, mixed>
     */
    private function decodeList(string $payload, int &$offset, int $len): array
    {
        if ($offset >= $len || $payload[$offset] !== 'l') {
            throw new InvalidArgumentException('Invalid list prefix.');
        }

        $offset++; // skip 'l'
        $items = [];

        while (true) {
            if ($offset >= $len) {
                throw new InvalidArgumentException('List not terminated.');
            }

            if ($payload[$offset] === 'e') {
                $offset++; // consume list terminator

                return $items;
            }

            $items[] = $this->decodeValue($payload, $offset, $len);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeDictionary(string $payload, int &$offset, int $len): array
    {
        if ($offset >= $len || $payload[$offset] !== 'd') {
            throw new InvalidArgumentException('Invalid dictionary prefix.');
        }

        $offset++; // skip 'd'
        $items = [];

        while (true) {
            if ($offset >= $len) {
                throw new InvalidArgumentException('Dictionary not terminated.');
            }

            if ($payload[$offset] === 'e') {
                $offset++; // consume dict terminator

                return $items;
            }

            $c = $payload[$offset];
            if (! ($c >= '0' && $c <= '9')) {
                throw new InvalidArgumentException('Dictionary key must be a string.');
            }

            $key = $this->decodeString($payload, $offset, $len);
            $items[$key] = $this->decodeValue($payload, $offset, $len);
        }
    }
}
