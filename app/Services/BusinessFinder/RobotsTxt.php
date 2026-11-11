<?php

namespace App\Services\BusinessFinder;

/**
 * Minimal robots.txt evaluator (RFC 9309): picks the group for our user agent (else "*"),
 * longest matching rule wins, Allow wins ties, supports "*" and "$".
 */
final class RobotsTxt
{
    public static function allows(string $robots, string $userAgent, string $path): bool
    {
        $token = strtolower(strtok($userAgent, '/ ') ?: $userAgent);
        $groups = self::groups($robots);

        $rules = null;
        foreach ($groups as $g) {
            foreach ($g['agents'] as $agent) {
                if ($agent !== '*' && $agent !== '' && str_contains($token, $agent)) {
                    $rules = $g['rules'];
                    break 2;
                }
            }
        }
        if ($rules === null) {
            $rules = [];
            foreach ($groups as $g) {
                if (in_array('*', $g['agents'], true)) {
                    $rules = [...$rules, ...$g['rules']];
                }
            }
        }

        $best = null;
        foreach ($rules as [$type, $pattern]) {
            if ($pattern === '' || ! self::matches($pattern, $path)) {
                continue;   // empty Disallow = allow everything
            }
            $len = strlen($pattern);
            if ($best === null || $len > $best[1] || ($len === $best[1] && $type === 'allow')) {
                $best = [$type, $len];
            }
        }

        return $best === null || $best[0] === 'allow';
    }

    /** @return list<array{agents: list<string>, rules: list<array{0: string, 1: string}>}> */
    private static function groups(string $robots): array
    {
        $groups = [];
        $current = null;
        $lastWasAgent = false;
        foreach (preg_split('/\r\n|\r|\n/', $robots) as $line) {
            $line = trim(preg_replace('/#.*/', '', $line));
            if (! str_contains($line, ':')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode(':', $line, 2));
            $key = strtolower($key);
            if ($key === 'user-agent') {
                if (! $lastWasAgent) {
                    $groups[] = ['agents' => [], 'rules' => []];
                    $current = array_key_last($groups);
                }
                $groups[$current]['agents'][] = strtolower($value);
                $lastWasAgent = true;
            } elseif (in_array($key, ['allow', 'disallow'], true) && $current !== null) {
                $groups[$current]['rules'][] = [$key, $value];
                $lastWasAgent = false;
            } else {
                $lastWasAgent = false;
            }
        }

        return $groups;
    }

    private static function matches(string $pattern, string $path): bool
    {
        $anchored = str_ends_with($pattern, '$');
        $pattern = rtrim($pattern, '$');
        $regex = '#^'.str_replace('\*', '.*', preg_quote($pattern, '#')).($anchored ? '$' : '').'#';

        return (bool) preg_match($regex, $path);
    }
}
