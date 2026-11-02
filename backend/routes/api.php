<?php

use App\Support\ApiResponse;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', function () {
        return ApiResponse::success('API is healthy.', [
            'status' => 'ok',
        ]);
    })->name('api.v1.health');
});
