<?php

use Illuminate\Support\Facades\Route;

// API-only project: the root opens the Swagger documentation.
Route::redirect('/', 'api/documentation');
