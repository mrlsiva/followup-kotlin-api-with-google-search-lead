<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * admin      – sees and edits every user's records, team reports, manages users
 * manager    – own work + sees and edits the records of the people assigned to them, team reports
 * sales      – own leads, follow-ups and Business Finder only
 * researcher – Business Finder only (search, add, verify businesses); no CRM leads or follow-ups.
 *              A manager/admin saves their businesses to the CRM and assigns the lead to a salesperson.
 */
enum UserRole: string
{
    use HasValues;

    case Admin = 'admin';
    case Manager = 'manager';
    case Sales = 'sales';
    case Researcher = 'researcher';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Manager => 'Manager',
            self::Sales => 'Sales',
            self::Researcher => 'Researcher',
        };
    }
}
