<?php

namespace App\Support;

/**
 * Answers "is this visitor's IP address in Indonesia?" from a local copy of APNIC's
 * delegation list (resources/geo/indonesia-ip-ranges.php, refreshed by
 * `php artisan geo:update-indonesia-ip`), so picking a language never sends a visitor's IP
 * to a third-party geolocation service and needs no API key.
 *
 * APNIC records the country an address block is registered to, which for Indonesian
 * ISPs and mobile carriers is where their customers actually are.
 */
class IndonesiaIp
{
    public const DATA_PATH = 'geo/indonesia-ip-ranges.php';

    /** @var array{ipv4: list<array{int, int}>, ipv6: list<array{string, string}>}|null */
    private static ?array $ranges = null;

    /** False for loopback, private and reserved addresses (local development, internal hops). */
    public static function isPublic(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    public static function contains(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return self::search(self::ranges()['ipv4'], ip2long($ip));
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            // Fixed-length lowercase hex sorts the same as the 128-bit number it encodes.
            return self::search(self::ranges()['ipv6'], bin2hex(inet_pton($ip)));
        }

        return false;
    }

    /** Lets tests swap in a small fixed list; null goes back to the real file. */
    public static function useRanges(?array $ranges): void
    {
        self::$ranges = $ranges;
    }

    private static function ranges(): array
    {
        if (self::$ranges === null) {
            $path = resource_path(self::DATA_PATH);
            self::$ranges = is_file($path) ? require $path : ['ipv4' => [], 'ipv6' => []];
        }

        return self::$ranges;
    }

    /**
     * Binary search over ranges sorted by start and not overlapping.
     *
     * @param  list<array{int|string, int|string}>  $ranges
     */
    private static function search(array $ranges, int|string $needle): bool
    {
        $low = 0;
        $high = count($ranges) - 1;

        while ($low <= $high) {
            $mid = intdiv($low + $high, 2);
            [$start, $end] = $ranges[$mid];

            if (self::compare($needle, $start) < 0) {
                $high = $mid - 1;
            } elseif (self::compare($needle, $end) > 0) {
                $low = $mid + 1;
            } else {
                return true;
            }
        }

        return false;
    }

    /**
     * Integers compare as numbers; IPv6 hex strings must compare as text, because PHP's own
     * < and > treat an all-digit string like "20014488…" as a number and get the order wrong.
     */
    public static function compare(int|string $a, int|string $b): int
    {
        return is_int($a) ? $a <=> $b : strcmp($a, $b);
    }
}
