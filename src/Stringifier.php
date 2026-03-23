<?php

declare(strict_types=1);

namespace Maml;

final class Stringifier
{
    private const string KEY_PATTERN = '/^[A-Za-z0-9_\-]+$/';

    public static function stringify(mixed $value): string
    {
        return self::doStringify($value, 0);
    }

    private static function doStringify(mixed $value, int $level): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            $str = (string) $value;
            if ($str === '-0') {
                return '-0';
            }
            return $str;
        }
        if (is_string($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }
        if (is_array($value)) {
            if (array_is_list($value)) {
                return self::stringifyArray($value, $level);
            }
            return self::stringifyObject($value, $level);
        }
        throw new \InvalidArgumentException('Unsupported value type: ' . get_debug_type($value));
    }

    private static function stringifyArray(array $arr, int $level): string
    {
        if (count($arr) === 0) {
            return '[]';
        }
        $childIndent = self::getIndent($level + 1);
        $parentIndent = self::getIndent($level);
        $out = "[\n";
        for ($i = 0; $i < count($arr); $i++) {
            if ($i > 0) {
                $out .= "\n";
            }
            $out .= $childIndent . self::doStringify($arr[$i], $level + 1);
        }
        return $out . "\n" . $parentIndent . ']';
    }

    private static function stringifyObject(array $obj, int $level): string
    {
        $keys = array_keys($obj);
        $childIndent = self::getIndent($level + 1);
        $parentIndent = self::getIndent($level);
        $out = "{\n";
        for ($i = 0; $i < count($keys); $i++) {
            if ($i > 0) {
                $out .= "\n";
            }
            $key = (string) $keys[$i];
            $out .= $childIndent . self::stringifyKey($key) . ': ' . self::doStringify($obj[$key], $level + 1);
        }
        return $out . "\n" . $parentIndent . '}';
    }

    private static function stringifyKey(string $key): string
    {
        if (preg_match(self::KEY_PATTERN, $key)) {
            return $key;
        }
        return json_encode($key, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private static function getIndent(int $level): string
    {
        return str_repeat('  ', $level);
    }
}
