<?php

declare(strict_types=1);

namespace App\Support\OpenApi;

final class OpenApiYaml
{
    /**
     * @param  array<string, mixed>  $document
     */
    public static function dump(array $document): string
    {
        return trim(self::encode($document, 0))."\n";
    }

    private static function encode(mixed $value, int $indent): string
    {
        if (is_array($value)) {
            if ($value === []) {
                return " []\n";
            }

            if (array_is_list($value)) {
                $out = "\n";
                foreach ($value as $item) {
                    $out .= str_repeat('  ', $indent).'-';
                    if (is_array($item) && $item !== [] && ! array_is_list($item)) {
                        $out .= self::encodeMappingInline($item, $indent + 1);
                    } else {
                        $encoded = self::encode($item, $indent + 1);
                        $out .= str_starts_with($encoded, "\n") ? $encoded : ' '.ltrim($encoded);
                    }
                }

                return $out;
            }

            $out = "\n";
            foreach ($value as $key => $item) {
                $out .= str_repeat('  ', $indent).self::key((string) $key).':';
                $out .= self::encode($item, $indent + 1);
            }

            return $out;
        }

        return ' '.self::scalar($value)."\n";
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function encodeMappingInline(array $item, int $indent): string
    {
        $first = true;
        $out = '';
        foreach ($item as $key => $value) {
            $prefix = $first ? ' ' : str_repeat('  ', $indent);
            $first = false;
            $out .= $prefix.self::key((string) $key).':'.self::encode($value, $indent + 1);
        }

        return $out;
    }

    private static function key(string $key): string
    {
        return preg_match('#^[A-Za-z0-9._/{}-]+$#', $key) === 1 ? $key : json_encode($key, JSON_THROW_ON_ERROR);
    }

    private static function scalar(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $string = (string) $value;

        if (preg_match('/^[A-Za-z0-9._\/-]+$/', $string) === 1) {
            return $string;
        }

        return json_encode($string, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
