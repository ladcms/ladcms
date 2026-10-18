<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ladcms\Core\Http\Controllers\Admin\DashboardController;
use Ladcms\Core\Http\Controllers\Admin\UsersController;

Route::get('/', DashboardController::class)->name('dashboard');

Route::get('/users', [UsersController::class, 'index'])->middleware('can:users.view')->name('users.index');
