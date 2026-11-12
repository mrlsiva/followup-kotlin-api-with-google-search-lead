<?php

namespace App\Models;

use App\Support\Categories;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** A Business Finder category (admin-managed). Businesses store the category name. */
class FinderCategory extends Model
{
    protected $table = 'business_categories';

    protected $fillable = ['name', 'name_ta', 'search_term', 'sort_order', 'is_active'];

    protected $attributes = ['sort_order' => 100, 'is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Categories::flush());
        static::deleted(fn () => Categories::flush());
    }

    public function isSystem(): bool
    {
        return $this->system !== null;
    }

    /** "All businesses" is a search filter only, never stored on a business. */
    public function isAssignable(): bool
    {
        return $this->system !== 'all';
    }

    /** Words used in the Google query, e.g. "Hospitals in Chennai". */
    public function term(): string
    {
        return $this->search_term ?: $this->name;
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort_order')->orderBy('name');
    }
}
