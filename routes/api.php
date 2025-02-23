<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Controllers\API\TemplateController;



    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    Route::post('/refresh-token', [AuthController::class, 'refreshToken']);
    Route::options('/refresh-token', function () {
        return response('', 200)->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Authorization, Content-Type');
    });



Route::middleware([JwtAuthMiddleware::class])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/update-password', [AuthController::class, 'updatePassword']);

    //  USER Routes (Only "user" role can access)
    Route::middleware(['role:user'])->group(function () {
        
    });

    // ADMIN Routes (Only "admin" role can access)
    Route::middleware(['role:admin'])->group(function () {
        Route::get('user/profile/{id}', [AuthController::class, 'getProfile']);
        Route::post('user/profile/{id}', [AuthController::class, 'updateProfile']);
        Route::post('user/profile/photo/{id}', [AuthController::class, 'uploadProfilePhoto']);
        Route::delete('user/profile/photo/{id}', [AuthController::class, 'removeProfilePhoto']);

        Route::get('/templates', [TemplateController::class, 'index']);
        Route::post('/templates/{id}', [TemplateController::class, 'update']);
    });

    //  GUEST Routes (Only "guest" role can access)
    Route::middleware(['role:guest'])->group(function () {
       
    });
});
