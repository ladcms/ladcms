<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ladcms\Core\Http\Controllers\Admin\DashboardController;

Route::get('/', DashboardController::class)->name('dashboard');
