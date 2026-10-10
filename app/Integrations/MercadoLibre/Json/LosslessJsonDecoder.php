<?php

declare(strict_types=1);

namespace App\Integrations\MercadoLibre\Json;

use JsonException;

final class LosslessJsonDecoder
{
    /** @return array<string,mixed> */
    public static function decodeObject(string $json): array
    {
        if (!json_validate($json, 512)) {
            throw new JsonException('Invalid JSON.');
        }

        $preserved = self::quoteNumbersOutsideStrings($json);
        $decoded = json_decode($preserved, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new JsonException('Expected JSON object.');
        }

        /** @var array<string,mixed> $decoded */
        return $decoded;
    }

    private static function quoteNumbersOutsideStrings(string $json): string
    {
        $length = strlen($json);
        $output = '';
        $inString = false;
        $escaped = false;
        $index = 0;

        while ($index < $length) {
            $char = $json[$index];

            if ($inString) {
                $output .= $char;
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $inString = false;
                }
                $index++;
                continue;
            }

            if ($char === '"') {
                $inString = true;
                $output .= $char;
                $index++;
                continue;
            }

            if ($char !== '-' && !self::isDigit($char)) {
                $output .= $char;
                $index++;
                continue;
            }

            $start = $index;
            if ($char === '-') {
                $index++;
            }

            if ($json[$index] === '0') {
                $index++;
            } else {
                while ($index < $length && self::isDigit($json[$index])) {
                    $index++;
                }
            }

            if ($index < $length && $json[$index] === '.') {
                $index++;
                while ($index < $length && self::isDigit($json[$index])) {
                    $index++;
                }
            }

            if ($index < $length && ($json[$index] === 'e' || $json[$index] === 'E')) {
                $index++;
                if ($index < $length && ($json[$index] === '+' || $json[$index] === '-')) {
                    $index++;
                }
                while ($index < $length && self::isDigit($json[$index])) {
                    $index++;
                }
            }

            $output .= '"' . substr($json, $start, $index - $start) . '"';
        }

        return $output;
    }

    private static function isDigit(string $char): bool
    {
        return $char >= '0' && $char <= '9';
    }
}
