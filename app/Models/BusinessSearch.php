<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One generated set of manual search links (search history). */
class BusinessSearch extends Model
{
    protected $fillable = [
        'business_name', 'match_type', 'keywords', 'category', 'state', 'district', 'city', 'area',
        'pincode', 'quantity', 'website_filter', 'contact_filter', 'query',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function businessLeads(): HasMany
    {
        return $this->hasMany(BusinessLead::class);
    }
}
