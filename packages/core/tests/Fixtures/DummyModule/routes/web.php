<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/dummy', fn () => 'dummy web')->name('dummy.index');
