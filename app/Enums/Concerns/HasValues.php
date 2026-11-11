<?php

namespace App\Enums\Concerns;

trait HasValues
{
    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    /**
     * Case-insensitive, punctuation-insensitive lookup ("walk in", "WALK-IN" → Walk-in).
     * Used when importing spreadsheets typed by hand.
     */
    public static function fromLoose(?string $value, self $default): self
    {
        $norm = fn (string $s) => preg_replace('/[^a-z]/', '', strtolower($s));
        $needle = $norm((string) $value);
        if ($needle === '') {
            return $default;
        }
        foreach (self::cases() as $case) {
            if ($norm($case->value) === $needle) {
                return $case;
            }
        }

        return $default;
    }
}
