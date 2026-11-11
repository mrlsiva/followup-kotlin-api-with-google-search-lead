<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /** Page size from ?per_page= (1–100, default 20). */
    protected function perPage(): int
    {
        return max(1, min(100, (int) request('per_page', 20)));
    }
}
