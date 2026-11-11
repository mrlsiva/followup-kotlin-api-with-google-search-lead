<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** Status recorded after a follow-up call. */
enum CallStatus: string
{
    use HasValues;

    case Interested = 'Interested';
    case NotAnswered = 'Not Answered';
    case Callback = 'Callback';
    case Closed = 'Closed';
}
