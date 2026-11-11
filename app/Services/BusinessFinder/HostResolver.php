<?php

namespace App\Services\BusinessFinder;

/** DNS lookups for the website checker (swappable in tests). */
class HostResolver
{
    /** @return list<string> IPv4/IPv6 addresses of $host (empty when it does not resolve). */
    public function resolve(string $host): array
    {
        $host = trim($host, '[]');
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $ips = @gethostbynamel($host) ?: [];
        $aaaa = @dns_get_record($host, DNS_AAAA) ?: [];
        foreach ($aaaa as $rec) {
            if (! empty($rec['ipv6'])) {
                $ips[] = $rec['ipv6'];
            }
        }

        return array_values(array_unique($ips));
    }

    /** True only for globally routable addresses (no private, loopback, link-local, reserved, CGNAT). */
    public static function isPublicIp(string $ip): bool
    {
        // IPv4-mapped IPv6 (::ffff:127.0.0.1) → check the IPv4 part
        if (preg_match('/^::ffff:(\d+\.\d+\.\d+\.\d+)$/i', $ip, $m)) {
            $ip = $m[1];
        }
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($ip);
            $inRange = fn (string $cidr) => (function () use ($cidr, $long) {
                [$net, $bits] = explode('/', $cidr);
                $mask = -1 << (32 - (int) $bits);

                return ($long & $mask) === (ip2long($net) & $mask);
            })();
            foreach (['100.64.0.0/10', '192.0.0.0/24', '192.0.2.0/24', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4'] as $cidr) {
                if ($inRange($cidr)) {
                    return false;
                }
            }
        } else {
            $lower = strtolower($ip);
            if (str_starts_with($lower, 'fe80:') || str_starts_with($lower, 'ff') || str_starts_with($lower, '2001:db8:')) {
                return false;
            }
        }

        return true;
    }
}
