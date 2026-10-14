<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ladcms\Core\Http\Controllers\Api\MentionSearchController;
use Ladcms\Core\Http\Controllers\BannedController;
use Ladcms\Core\Http\Controllers\HomeController;

Route::get('/', HomeController::class)->name('home');
Route::get('/banned', BannedController::class)->middleware('auth')->name('banned');
Route::middleware(['auth', 'throttle:60,1'])->get('/api/users/mention-search', MentionSearchController::class)->name('api.users.mention-search');
