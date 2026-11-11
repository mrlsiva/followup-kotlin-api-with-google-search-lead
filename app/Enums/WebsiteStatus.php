<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** Website status of a researched business, set by the salesperson (the checker only suggests). */
enum WebsiteStatus: string
{
    use HasValues;

    case NoWebsite = 'No website found';
    case Available = 'Website available';
    case Outdated = 'Website outdated';
    case Inactive = 'Website inactive';
    case Pending = 'Pending verification';
}
