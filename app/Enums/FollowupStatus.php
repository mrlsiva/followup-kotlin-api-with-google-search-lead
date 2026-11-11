<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum FollowupStatus: string
{
    use HasValues;

    case Pending = 'pending';
    case Completed = 'completed';
}
