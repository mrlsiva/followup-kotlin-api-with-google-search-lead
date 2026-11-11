<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum BusinessCategory: string
{
    use HasValues;

    case Hospitals = 'Hospitals';
    case Clinics = 'Clinics';
    case Schools = 'Schools';
    case Colleges = 'Colleges';
    case Manufacturers = 'Manufacturers';
    case Traders = 'Traders';
    case Distributors = 'Distributors';
    case Textile = 'Textile companies';
    case Construction = 'Construction companies';
    case RealEstate = 'Real estate';
    case Automobile = 'Automobile dealers';
    case Restaurants = 'Restaurants';
    case Hotels = 'Hotels';
    case Interior = 'Interior designers';
    case IT = 'IT companies';
    case Other = 'Other businesses';
    case All = 'All businesses';

    /** Words used in the generated Google query ("Hospitals in Chennai"). */
    public function searchTerm(): string
    {
        return match ($this) {
            self::RealEstate => 'Real estate companies',
            self::All, self::Other => 'Businesses',
            default => $this->value,
        };
    }

    /** Categories a saved business can have ("All businesses" is a search filter only). */
    public static function assignable(): array
    {
        return array_values(array_filter(self::values(), fn ($v) => $v !== self::All->value));
    }
}
