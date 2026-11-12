<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * admin   – sees every user's leads, team reports, manages users
 * manager – own work + read-only view and reports of the salespeople assigned to them
 * sales   – own leads, follow-ups and Business Finder only
 */
enum UserRole: string
{
    use HasValues;

    case Admin = 'admin';
    case Manager = 'manager';
    case Sales = 'sales';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Manager => 'Manager',
            self::Sales => 'Sales',
        };
    }
}
