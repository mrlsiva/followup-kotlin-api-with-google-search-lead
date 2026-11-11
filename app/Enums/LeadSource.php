<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum LeadSource: string
{
    use HasValues;

    case WhatsApp = 'WhatsApp';
    case Facebook = 'Facebook';
    case Website = 'Website';
    case Referral = 'Referral';
    case Call = 'Call';
    case WalkIn = 'Walk-in';
    case BusinessFinder = 'Business Finder';
    case Other = 'Other';
}
