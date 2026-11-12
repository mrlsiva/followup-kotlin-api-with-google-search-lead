<?php

namespace App\Support;

use App\Models\FinderCategory;
use Illuminate\Support\Collection;

/**
 * Business Finder categories from the database (Admin → Categories), cached per request.
 * Replaces the former hard-coded App\Enums\BusinessCategory.
 */
final class Categories
{
    public const ALL = 'All businesses';
    public const OTHER = 'Other businesses';

    private static ?Collection $cache = null;

    /** @return Collection<int, FinderCategory> every category, ordered */
    public static function all(): Collection
    {
        return self::$cache ??= FinderCategory::query()->ordered()->get();
    }

    /** @return Collection<int, FinderCategory> */
    public static function active(): Collection
    {
        return self::all()->where('is_active', true)->values();
    }

    /** Active names for search filters (includes "All businesses"). */
    public static function searchable(): array
    {
        return self::active()->pluck('name')->all();
    }

    /** Active names a business can be saved with; $keep (the current value) stays valid even if deactivated. */
    public static function assignable(?string $keep = null): array
    {
        $names = self::active()->filter->isAssignable()->pluck('name')->all();
        if ($keep !== null && $keep !== '' && ! in_array($keep, $names, true) && self::find($keep)) {
            $names[] = $keep;
        }

        return $names;
    }

    public static function find(?string $name): ?FinderCategory
    {
        return $name === null ? null : self::all()->firstWhere('name', $name);
    }

    /** Google query words; unknown categories fall back to their own name. */
    public static function searchTerm(?string $name): string
    {
        return self::find($name)?->term() ?? (string) $name;
    }

    /** Display label in the current language (Tamil name when set, else the translation file, else the name). */
    public static function label(?string $name): string
    {
        if ($name === null || $name === '') {
            return '';
        }
        if (app()->getLocale() === 'ta' && ($ta = self::find($name)?->name_ta)) {
            return $ta;
        }

        return __($name);
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
