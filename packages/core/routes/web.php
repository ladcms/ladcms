<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ladcms\Core\Http\Controllers\Api\MentionSearchController;
use Ladcms\Core\Http\Controllers\BannedController;
use Ladcms\Core\Http\Controllers\HomeController;
use Ladcms\Core\Http\Controllers\NotificationsController;
use Ladcms\Core\Http\Controllers\NotificationsPageController;
use Ladcms\Core\Http\Controllers\ProfileController;
use Ladcms\Core\Http\Controllers\RulesController;
use Ladcms\Core\Http\Controllers\Settings\AvatarController;
use Ladcms\Core\Http\Controllers\Settings\LocaleController;
use Ladcms\Core\Http\Controllers\Settings\NotificationPreferencesController;
use Ladcms\Core\Http\Controllers\Settings\PrivacyController;
use Ladcms\Core\Http\Controllers\Settings\ProfileSettingsController;
use Ladcms\Core\Http\Controllers\UsersController;

Route::get('/', HomeController::class)->name('home');
Route::get('/users', [UsersController::class, 'index'])->name('users.index');
Route::get('/u/{login}', [ProfileController::class, 'show'])->name('users.show');
Route::get('/rules', [RulesController::class, 'show'])->name('rules');
Route::get('/notifications', [NotificationsPageController::class, 'index'])->middleware('auth')->name('notifications.page');
Route::get('/banned', BannedController::class)->middleware('auth')->name('banned');
Route::middleware(['auth', 'throttle:60,1'])->get('/api/users/mention-search', MentionSearchController::class)->name('api.users.mention-search');

Route::middleware(['auth', 'throttle:60,1'])->prefix('api/notifications')->name('notifications.')->group(function (): void {
    Route::get('/', [NotificationsController::class, 'index'])->name('index');
    Route::post('read-all', [NotificationsController::class, 'markAllRead'])->name('read-all');
    Route::post('{id}/read', [NotificationsController::class, 'markRead'])->name('read');
});

Route::post('/locale', [LocaleController::class, 'update'])->middleware('throttle:30,1')->name('locale.update');

Route::middleware('auth')->prefix('settings')->group(function (): void {
    // Not Route::redirect(): Wayfinder 0.1.x emits a "query" method for it that its TS types lack.
    Route::get('/', fn () => redirect()->route('settings.profile'))->name('settings');
    Route::get('profile', [ProfileSettingsController::class, 'edit'])->name('settings.profile');
    Route::patch('profile', [ProfileSettingsController::class, 'update'])->middleware('throttle:6,1')->name('settings.profile.update');
    Route::post('profile/avatar', [AvatarController::class, 'store'])->middleware('throttle:20,1')->name('settings.avatar.store');
    Route::delete('profile/avatar', [AvatarController::class, 'destroy'])->name('settings.avatar.destroy');
    Route::patch('login', [ProfileSettingsController::class, 'updateLogin'])->middleware('throttle:6,1')->name('settings.login.update');
    Route::get('privacy', [PrivacyController::class, 'edit'])->name('settings.privacy');
    Route::patch('privacy', [PrivacyController::class, 'update'])->middleware('throttle:30,1')->name('settings.privacy.update');
    Route::get('notifications', [NotificationPreferencesController::class, 'edit'])->name('settings.notifications');
    Route::patch('notifications', [NotificationPreferencesController::class, 'update'])->middleware('throttle:30,1')->name('settings.notifications.update');
    Route::inertia('appearance', 'core::settings/appearance')->name('settings.appearance');
});

// Несуществующий URL проходит через группу `web` (сессия, пользователь, локаль, layout) и отдаёт обычный 404,
// который рендерит `ErrorPageRenderer`: страница ошибки показывается залогиненному пользователю в его локали.
Route::fallback(fn () => abort(404));
