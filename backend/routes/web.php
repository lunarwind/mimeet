<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['app' => 'MiMeet API', 'version' => '1.0.0']);
});
