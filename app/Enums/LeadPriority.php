<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum LeadPriority: string
{
    use HasValues;

    case High = 'High';
    case Medium = 'Medium';
    case Low = 'Low';
}
