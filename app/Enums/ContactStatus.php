<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** Contact verification status. Never set to Verified automatically — only by the salesperson. */
enum ContactStatus: string
{
    use HasValues;

    case Pending = 'Pending';
    case Verified = 'Verified';
    case IncorrectNumber = 'Incorrect number';
    case Disconnected = 'Disconnected';
    case DoNotContact = 'Do not contact';
}
