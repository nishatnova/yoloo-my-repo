<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Controllers\API\TemplateController;
use App\Http\Controllers\API\FaqController;
use App\Http\Controllers\API\PackageController;
use App\Http\Controllers\API\JobPostController;
use App\Http\Controllers\API\ContactController;



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

    Route::get('/templates', [TemplateController::class, 'index']);
    Route::get('/faqs', [FaqController::class, 'index']);
    Route::get('/search', [PackageController::class, 'search']);
    Route::get('/packages/search', [PackageController::class, 'searchPackagePage']);

    Route::get('/packages', [PackageController::class, 'index']);
    Route::get('/packages/{id}', [PackageController::class, 'show']);

    Route::get('/job-posts', [JobPostController::class, 'index']);
    Route::get('/job-posts/{id}', [JobPostController::class, 'show']);

    Route::post('/contact-us', [ContactController::class, 'store']);


Route::middleware([JwtAuthMiddleware::class])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/update-password', [AuthController::class, 'updatePassword']);

    //  USER Routes (Only "user" role can access)
    Route::middleware(['role:user'])->group(function () {
        
    });

    // ADMIN Routes (Only "admin" role can access)
    Route::middleware(['role:admin'])->group(function () {
        Route::get('admin/profile/{id}', [AuthController::class, 'getProfile']);
        Route::post('admin/profile/{id}', [AuthController::class, 'updateProfile']);
        Route::post('admin/profile/photo/{id}', [AuthController::class, 'uploadProfilePhoto']);
        Route::delete('admin/profile/photo/{id}', [AuthController::class, 'removeProfilePhoto']);

        // Templates
        Route::post('/templates/{id}', [TemplateController::class, 'update']);

        //FAQ
        Route::post('/faq/create-faq', [FaqController::class, 'store']);
        Route::post('/faq/update-faq/{id}', [FaqController::class, 'update']);
        Route::delete('/faq/delete-faq/{id}', [FaqController::class, 'destroy']);

       
        Route::post('/packages', [PackageController::class, 'store']);
        Route::post('/packages/{id}', [PackageController::class, 'update']);
        Route::delete('/packages/{id}', [PackageController::class, 'destroy']);

        Route::post('/job-posts', [JobPostController::class, 'store']);
        Route::post('/job-posts/{id}', [JobPostController::class, 'update']);
        Route::delete('/job-posts/{id}', [JobPostController::class, 'destroy']);
        
    });

    //  GUEST Routes (Only "guest" role can access)
    Route::middleware(['role:guest'])->group(function () {
       
    });
});
