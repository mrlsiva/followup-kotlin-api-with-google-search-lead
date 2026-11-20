<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** Direction of a phone call logged by the app. */
enum CallDirection: string
{
    use HasValues;

    case Outgoing = 'outgoing';
    case Incoming = 'incoming';
}
