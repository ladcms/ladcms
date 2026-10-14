<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ladcms\Core\Http\Controllers\BannedController;
use Ladcms\Core\Http\Controllers\HomeController;

Route::get('/', HomeController::class)->name('home');
Route::get('/banned', BannedController::class)->middleware('auth')->name('banned');
