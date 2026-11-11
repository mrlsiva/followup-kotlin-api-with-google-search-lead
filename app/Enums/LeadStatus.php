<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum LeadStatus: string
{
    use HasValues;

    case New = 'New';
    case Interested = 'Interested';
    case FollowUp = 'Follow-up';
    case Won = 'Won';
    case Lost = 'Lost';
}
