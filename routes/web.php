<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'status' => 'API conectada',
        'version' => '1.0'
    ]);
});