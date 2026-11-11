<?php

namespace App\Services\BusinessFinder;

use App\Enums\WebsiteStatus;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Optional, polite website availability check.
 *
 * - Only http/https on the default ports, only public IP addresses (no localhost / LAN / metadata IPs).
 *   Each redirect hop is validated again and the connection is pinned to the checked IP.
 * - robots.txt is read first; if it disallows the page, the page itself is not fetched.
 * - At most one robots.txt request plus one request per redirect hop; results are cached.
 * - It never concludes that a website "does not exist": a failed check is reported as
 *   "could not reach right now" and the website status is left for the salesperson to decide.
 */
class WebsiteChecker
{
    public const REACHABLE = 'reachable';
    public const HTTP_ERROR = 'http_error';
    public const UNREACHABLE = 'unreachable';
    public const ROBOTS_BLOCKED = 'blocked_by_robots';
    public const TOO_MANY_REDIRECTS = 'too_many_redirects';
    public const INVALID_URL = 'invalid_url';
    public const NOT_ALLOWED = 'not_allowed';

    public function __construct(private readonly HostResolver $resolver) {}

    /** Normalise user input ("example.com" → "https://example.com/"). Null when it isn't a usable URL. */
    public static function normalize(?string $input): ?string
    {
        $url = trim((string) $input);
        if ($url === '') {
            return null;
        }
        if (! preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
            $url = 'https://'.$url;
        }
        $parts = parse_url($url);
        if ($parts === false || empty($parts['host']) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return null;
        }
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }
        $path = $parts['path'] ?? '/';
        // Credentials are kept so that the safety check can reject them.
        $userinfo = isset($parts['user']) ? $parts['user'].(isset($parts['pass']) ? ':'.$parts['pass'] : '').'@' : '';

        return strtolower($parts['scheme']).'://'.$userinfo.strtolower($parts['host'])
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .($path === '' ? '/' : $path)
            .(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    public function check(string $input): array
    {
        $url = self::normalize($input);
        if ($url === null) {
            return $this->result($input, null, self::INVALID_URL, 'Enter a valid website address, e.g. https://example.com');
        }
        if ($reason = $this->disallowedReason($url)) {
            return $this->result($input, $url, self::NOT_ALLOWED, $reason);
        }

        $minutes = (int) config('business_finder.website_check.cache_minutes', 10);
        $cacheKey = 'bf:webcheck:'.sha1($url);
        if ($cached = Cache::get($cacheKey)) {
            return [...$cached, 'cached' => true];
        }

        $result = $this->run($input, $url);
        if ($result['result'] !== self::NOT_ALLOWED) {
            Cache::put($cacheKey, $result, now()->addMinutes($minutes));
        }

        return $result;
    }

    private function run(string $input, string $url): array
    {
        $started = microtime(true);

        // 0) DNS: refuse names that point into a private network; a name that doesn't resolve is "unreachable"
        $ips = $this->publicIps($url);
        if (is_string($ips)) {
            return $this->resolver->resolve(parse_url($url, PHP_URL_HOST)) === []
                ? $this->unreachable($input, $url, $ips, null, $started)
                : $this->result($input, $url, self::NOT_ALLOWED, $ips);
        }

        // 1) robots.txt
        $robots = $this->robots($url);
        if ($robots['error'] !== null) {
            return $this->unreachable($input, $url, $robots['error'], $robots['ssl_valid'], $started);
        }
        if (! $robots['allowed']) {
            return $this->result($input, $url, self::ROBOTS_BLOCKED,
                "The site's robots.txt asks automated tools not to open this page, so it was not fetched. "
                .'The server is online — please open the website manually to verify it.',
                ['ssl_valid' => $robots['ssl_valid'], 'response_time_ms' => $this->ms($started)]);
        }

        // 2) the page itself (HEAD, falling back to GET), following redirects manually
        $current = $url;
        $sslValid = true;
        $max = (int) config('business_finder.website_check.max_redirects', 5);
        for ($hop = 0; $hop <= $max; $hop++) {
            if ($hop > 0 && ($reason = $this->disallowedReason($current))) {
                return $this->result($input, $url, self::NOT_ALLOWED, "Redirected to a blocked address: $reason",
                    ['final_url' => $current, 'redirects' => $hop]);
            }
            $fetch = $this->fetch($current, 'head');
            if ($fetch['response'] && in_array($fetch['response']->status(), [403, 405, 501], true)) {
                $fetch = $this->fetch($current, 'get');
            }
            $sslValid = $sslValid && $fetch['ssl_valid'] !== false;
            if ($fetch['response'] === null) {
                return $this->unreachable($input, $url, $fetch['error'], $sslValid ? null : false, $started, $current, $hop);
            }

            $res = $fetch['response'];
            $status = $res->status();
            if ($status >= 300 && $status < 400 && $res->header('Location')) {
                $next = $this->resolveLocation($current, $res->header('Location'));
                if ($next === null) {
                    break;
                }
                $current = $next;

                continue;
            }

            $extra = [
                'final_url' => $current,
                'http_status' => $status,
                'ssl_valid' => str_starts_with($current, 'https://') ? $sslValid : null,
                'redirects' => $hop,
                'response_time_ms' => $this->ms($started),
            ];
            if ($status >= 200 && $status < 300) {
                $hints = [];
                if (str_starts_with($current, 'http://')) {
                    $hints[] = 'Site does not use HTTPS — it may be outdated.';
                }
                if (! $sslValid) {
                    $hints[] = 'SSL certificate problem — browsers will show a security warning.';
                }

                return $this->result($input, $url, self::REACHABLE, 'The website responded normally.', [
                    ...$extra, 'suggested_status' => WebsiteStatus::Available->value, 'hints' => $hints,
                ]);
            }

            return $this->result($input, $url, self::HTTP_ERROR,
                "The server answered HTTP $status. The website may still exist (moved page, maintenance or bot protection) — please verify it manually.",
                $extra);
        }

        return $this->result($input, $url, self::TOO_MANY_REDIRECTS, 'Too many redirects. Please open the website manually.',
            ['final_url' => $current, 'redirects' => $max]);
    }

    /** @return array{response: ?Response, error: ?string, ssl_valid: ?bool} */
    private function fetch(string $url, string $method): array
    {
        $ips = $this->publicIps($url);
        if (is_string($ips)) {
            return ['response' => null, 'error' => $ips, 'ssl_valid' => null];
        }

        try {
            return ['response' => $this->send($this->client($url, $ips[0], true), $method, $url), 'error' => null, 'ssl_valid' => true];
        } catch (ConnectionException $e) {
            if (! $this->isSslError($e)) {
                return ['response' => null, 'error' => $this->friendlyError($e), 'ssl_valid' => null];
            }
        } catch (Throwable $e) {
            return ['response' => null, 'error' => $this->friendlyError($e), 'ssl_valid' => null];
        }

        // SSL problem: retry once without certificate verification to learn whether the site is up.
        try {
            return ['response' => $this->send($this->client($url, $ips[0], false), $method, $url), 'error' => null, 'ssl_valid' => false];
        } catch (Throwable $e) {
            return ['response' => null, 'error' => $this->friendlyError($e), 'ssl_valid' => false];
        }
    }

    private function send(PendingRequest $client, string $method, string $url): Response
    {
        return match ($method) {
            'head' => $client->head($url),
            'get-body' => $client->get($url),                              // robots.txt (body needed)
            default => $client->withOptions(['stream' => true])->get($url), // page: headers only, body never read
        };
    }

    private function client(string $url, string $ip, bool $verify): PendingRequest
    {
        $p = parse_url($url);
        $port = $p['port'] ?? (($p['scheme'] ?? 'https') === 'https' ? 443 : 80);
        $pinned = str_contains($ip, ':') ? "[$ip]" : $ip;
        $cfg = config('business_finder.website_check');

        $client = Http::withHeaders(['User-Agent' => $cfg['user_agent'], 'Accept' => 'text/html,*/*;q=0.8'])
            ->connectTimeout($cfg['connect_timeout'])
            ->timeout($cfg['timeout'])
            ->withoutRedirecting()
            // Connect to the IP we validated (prevents DNS-rebinding to a private address).
            ->withOptions(['curl' => [CURLOPT_RESOLVE => ["{$p['host']}:$port:$pinned"]]]);

        return $verify ? $client : $client->withoutVerifying();
    }

    /** @return array{allowed: bool, error: ?string, ssl_valid: ?bool} */
    private function robots(string $url): array
    {
        $p = parse_url($url);
        $origin = $p['scheme'].'://'.$p['host'].(isset($p['port']) ? ':'.$p['port'] : '');
        $path = ($p['path'] ?? '/').(isset($p['query']) ? '?'.$p['query'] : '');
        $cacheKey = 'bf:robots:'.sha1($origin);

        $cached = Cache::get($cacheKey);
        if ($cached === null) {
            $fetch = $this->fetch($origin.'/robots.txt', 'get-body');
            if ($fetch['response'] === null) {
                return ['allowed' => true, 'error' => $fetch['error'], 'ssl_valid' => $fetch['ssl_valid']];
            }
            $res = $fetch['response'];
            // Only a 200 with content defines rules; 3xx/4xx/5xx → no rules (allowed).
            $cached = ['txt' => $res->status() === 200 ? substr($res->body(), 0, 512 * 1024) : '', 'ssl_valid' => $fetch['ssl_valid']];
            Cache::put($cacheKey, $cached, now()->addMinutes((int) config('business_finder.website_check.robots_cache_minutes', 60)));
        }

        return [
            'allowed' => RobotsTxt::allows($cached['txt'], config('business_finder.website_check.user_agent'), $path),
            'error' => null,
            'ssl_valid' => $cached['ssl_valid'],
        ];
    }

    /** @return list<string>|string Public IPs, or an error message. */
    private function publicIps(string $url): array|string
    {
        $host = parse_url($url, PHP_URL_HOST);
        $ips = $this->resolver->resolve($host);
        if ($ips === []) {
            return "The domain name \"$host\" could not be resolved right now.";
        }
        foreach ($ips as $ip) {
            if (! HostResolver::isPublicIp($ip)) {
                return 'The website address points to a private or local network address.';
            }
        }

        return $ips;
    }

    /** Static checks on the URL itself (before any DNS lookup). */
    private function disallowedReason(string $url): ?string
    {
        $p = parse_url($url);
        if (! $p || empty($p['host']) || ! in_array($p['scheme'] ?? '', ['http', 'https'], true)) {
            return 'Only http and https website addresses can be checked.';
        }
        if (isset($p['user']) || isset($p['pass'])) {
            return 'Website addresses with a username or password are not checked.';
        }
        if (isset($p['port']) && ! in_array((int) $p['port'], [80, 443], true)) {
            return 'Only the standard web ports (80, 443) are checked.';
        }
        $host = strtolower(trim($p['host'], '[]'));
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return HostResolver::isPublicIp($host) ? null : 'Local and private network addresses are not checked.';
        }
        if ($host === 'localhost' || ! str_contains($host, '.')
            || preg_match('/\.(localhost|local|internal|lan|home|corp|intranet)$/', $host)) {
            return 'Local and private network addresses are not checked.';
        }

        return null;
    }

    private function resolveLocation(string $base, string $location): ?string
    {
        $location = trim($location);
        if (preg_match('#^https?://#i', $location)) {
            return self::normalize($location);
        }
        $b = parse_url($base);
        $origin = $b['scheme'].'://'.$b['host'].(isset($b['port']) ? ':'.$b['port'] : '');
        if (str_starts_with($location, '//')) {
            return self::normalize($b['scheme'].':'.$location);
        }
        if (str_starts_with($location, '/')) {
            return self::normalize($origin.$location);
        }
        $dir = rtrim(substr($b['path'] ?? '/', 0, strrpos($b['path'] ?? '/', '/') + 1), '/');

        return self::normalize("$origin$dir/$location");
    }

    private function isSslError(Throwable $e): bool
    {
        return (bool) preg_match('/cURL error (35|51|53|58|59|60|77|80|82|83|90|91)\b|SSL|certificate/i', $e->getMessage());
    }

    private function friendlyError(Throwable $e): string
    {
        $m = $e->getMessage();

        return match (true) {
            (bool) preg_match('/cURL error 28|timed out/i', $m) => 'The website did not respond in time.',
            (bool) preg_match('/cURL error 6\b|resolve host/i', $m) => 'The domain name could not be resolved right now.',
            (bool) preg_match('/cURL error 7\b|refused/i', $m) => 'The server refused the connection.',
            $this->isSslError($e) => 'Secure connection (SSL) failed.',
            default => 'Could not connect to the website.',
        };
    }

    private function unreachable(string $input, string $url, ?string $error, ?bool $ssl, float $started, ?string $final = null, int $hops = 0): array
    {
        return $this->result($input, $url, self::UNREACHABLE,
            trim(($error ?? 'Could not connect.').' This does not mean the website does not exist — it may be temporarily down. Please check it manually before changing its status.'),
            ['final_url' => $final ?? $url, 'ssl_valid' => $ssl, 'redirects' => $hops, 'response_time_ms' => $this->ms($started)]);
    }

    private function result(string $input, ?string $url, string $result, string $message, array $extra = []): array
    {
        return [
            'input_url' => $input,
            'url' => $url,
            'final_url' => $extra['final_url'] ?? $url,
            'result' => $result,
            'reachable' => $result === self::REACHABLE,
            'http_status' => $extra['http_status'] ?? null,
            'ssl_valid' => $extra['ssl_valid'] ?? null,
            'redirects' => $extra['redirects'] ?? 0,
            'response_time_ms' => $extra['response_time_ms'] ?? null,
            'suggested_status' => $extra['suggested_status'] ?? null,
            'hints' => $extra['hints'] ?? [],
            'message' => $message,
            'checked_at' => now()->toIso8601String(),
            'cached' => false,
        ];
    }

    private function ms(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }
}
