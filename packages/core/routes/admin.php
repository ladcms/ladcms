<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ladcms\Core\Http\Controllers\Admin\DashboardController;
use Ladcms\Core\Http\Controllers\Admin\RolesController;
use Ladcms\Core\Http\Controllers\Admin\UserBanController;
use Ladcms\Core\Http\Controllers\Admin\UserController;
use Ladcms\Core\Http\Controllers\Admin\UserRolesController;
use Ladcms\Core\Http\Controllers\Admin\UsersController;

Route::get('/', DashboardController::class)->name('dashboard');

Route::get('/users', [UsersController::class, 'index'])->middleware('can:users.view')->name('users.index');

Route::prefix('/users/{user}')->name('users.')->scopeBindings()->group(function (): void {
    Route::get('/', [UserController::class, 'show'])->middleware('can:users.view')->name('show');
    Route::patch('/', [UserController::class, 'update'])->name('update');
    Route::delete('/', [UserController::class, 'destroy'])->name('destroy');
    Route::patch('/login', [UserController::class, 'changeLogin'])->name('login');
    Route::patch('/suspicious', [UserController::class, 'markSuspicious'])->name('suspicious');
    Route::put('/roles', [UserRolesController::class, 'update'])->name('roles');
    Route::post('/ban', [UserBanController::class, 'store'])->name('ban');
    Route::delete('/ban/{ban}', [UserBanController::class, 'destroy'])->name('ban.lift');
});

Route::prefix('/roles')->name('roles.')->group(function (): void {
    Route::get('/', [RolesController::class, 'index'])->middleware('can:roles.manage')->name('index');
    Route::post('/', [RolesController::class, 'store'])->name('store');
    Route::get('/{role}/edit', [RolesController::class, 'edit'])->middleware('can:roles.manage')->name('edit');
    Route::put('/{role}', [RolesController::class, 'update'])->name('update');
    Route::put('/{role}/permissions', [RolesController::class, 'permissions'])->name('permissions');
    Route::delete('/{role}', [RolesController::class, 'destroy'])->name('destroy');
});
