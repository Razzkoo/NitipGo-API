<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

/*
|--------------------------------------------------------------------------
| AUTH API
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {

    Route::get('/google', [AuthController::class,'googleRedirect'])->middleware("throttle:5,1");
    Route::get('/google/callback', [AuthController::class,'googleCallback'])->middleware("throttle:5,1");
    Route::post('/google/token', [AuthController::class, 'googleTokenLogin']);

    // CUSTOMER
    Route::post('/register-customer', [AuthController::class,'registerCustomer']);
    Route::post('/login-customer', [AuthController::class,'loginCustomer'])->middleware('throttle:3,1');

    // TRAVELER
    Route::post('/register-traveler', [AuthController::class,'registerTraveler']);
    Route::post('/login-traveler', [AuthController::class,'loginTraveler'])->middleware('throttle:3,1');

    // REFRESH TOKEN
    Route::post('/refresh-token', [AuthController::class,'refreshToken']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password',  [AuthController::class, 'resetPassword']);

    // PROTECTED ROUTE
    Route::middleware('auth:sanctum')->group(function () {

        Route::get('/me', [AuthController::class,'me']);

        Route::post('/logout', [AuthController::class,'logout']);

        Route::post('/logout-all', [AuthController::class,'logoutAll']);

    });

});