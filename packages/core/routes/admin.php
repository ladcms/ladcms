<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ladcms\Core\Http\Controllers\Admin\ActivityController;
use Ladcms\Core\Http\Controllers\Admin\BansController;
use Ladcms\Core\Http\Controllers\Admin\ComplaintsController;
use Ladcms\Core\Http\Controllers\Admin\DashboardController;
use Ladcms\Core\Http\Controllers\Admin\ModulesController;
use Ladcms\Core\Http\Controllers\Admin\RolesController;
use Ladcms\Core\Http\Controllers\Admin\SettingsController;
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

Route::prefix('/moderation')->name('moderation.')->group(function (): void {
    Route::get('/complaints', [ComplaintsController::class, 'index'])->middleware('can:complaints.review')->name('complaints.index');
    Route::patch('/complaints/{complaint}', [ComplaintsController::class, 'review'])->name('complaints.review');
    Route::get('/bans', [BansController::class, 'index'])->middleware('can:users.ban')->name('bans.index');
});

Route::prefix('/settings/{group}')->name('settings.')->middleware('can:settings.manage')->group(function (): void {
    Route::get('/', [SettingsController::class, 'edit'])->name('edit');
    Route::put('/', [SettingsController::class, 'update'])->name('update');
});

Route::get('/modules', [ModulesController::class, 'index'])->middleware('can:modules.manage')->name('modules.index');
Route::patch('/modules/{name}', [ModulesController::class, 'toggle'])->name('modules.toggle');

Route::get('/activity', [ActivityController::class, 'index'])->middleware('can:activity.view')->name('activity.index');
