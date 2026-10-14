<?php

use Illuminate\Support\Facades\Route;

Route::get('/broken', fn () => 'broken web');
