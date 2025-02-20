<?php

namespace App\Providers;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * @var string
     */
    public const HOME = '/';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        // Here you can add any custom middleware or route configurations
        // For example, if you want to customize expired JWT handling globally.
    }

    /**
     * Define your routes for the application.
     *
     * @return void
     */
    public function map()
    {
        $this->mapApiRoutes();
    }

    /**
     * Map the API routes for your application.
     *
     * These routes are typically prefixed with "api" and are loaded by the RouteServiceProvider.
     *
     * @return void
     */
    protected function mapApiRoutes()
    {
        Route::prefix('api')
             ->middleware('api')
             ->namespace($this->namespace)
             ->group(base_path('routes/api.php'));
    }

    /**
     * Customize the unauthenticated error response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Auth\AuthenticationException  $exception
     * @return \Illuminate\Http\JsonResponse
     */
    public function unauthenticated(Request $request, AuthenticationException $exception)
    {
        if ($exception instanceof JWTException) {
            return response()->json(['error' => 'Your session has expired. Please log in again.'], 401);
        }

        return response()->json(['error' => 'Unauthenticated.'], 401);
    }
}
