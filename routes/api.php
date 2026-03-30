<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SystemSettingController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserRequestController;
use App\Http\Controllers\Api\TravelerController;
use App\Http\Controllers\Api\TravelerRequestController;
use App\Http\Controllers\Api\ProfileController;

/*
|--------------------------------------------------------------------------
| AUTH API
|--------------------------------------------------------------------------
*/
Route::get('/settings/public', [SystemSettingController::class, 'publicSettings']);

Route::post('/user-requests',     [UserRequestController::class, 'store']);
Route::post('/traveler-requests', [TravelerRequestController::class, 'store']);

Route::prefix('auth')->group(function () {

    Route::get('/google', [AuthController::class, 'googleRedirect'])->middleware('throttle:5,1');
    Route::get('/google/callback', [AuthController::class, 'googleCallback'])->middleware('throttle:5,1');
    Route::post('/google/token', [AuthController::class, 'googleTokenLogin']);

    Route::post('/register-customer', [AuthController::class, 'registerCustomer']);
    Route::post('/register-traveler', [AuthController::class, 'registerTraveler']);

    // Unified login
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:7,1');

    Route::post('/refresh-token', [AuthController::class, 'refreshToken']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    // ← GANTI auth:sanctum → multi.auth
    Route::middleware('multi.auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    });
});

// ADMIN ONLY ← GANTI auth:sanctum → multi.auth
Route::middleware(['multi.auth', 'role:admin'])->prefix('admin')->group(function () {

    Route::get('/profile',              [ProfileController::class, 'show']);
    Route::put('/profile',              [ProfileController::class, 'update']);
    Route::post('/profile/photo',       [ProfileController::class, 'updatePhoto']);
    Route::delete('/profile',           [ProfileController::class, 'destroy']);

    Route::get('/settings',               [SystemSettingController::class, 'index']);
    Route::put('/settings',               [SystemSettingController::class, 'update']);
    Route::get('/settings/history',       [SystemSettingController::class, 'history']);
    Route::post('/settings/reset/{key}',  [SystemSettingController::class, 'reset']);
    Route::patch('/settings/{key}',       [SystemSettingController::class, 'updateSingle']);

    Route::get('/users',               [UserController::class, 'index']);
    Route::post('/users',              [UserController::class, 'store']);
    Route::get('/users/{id}',          [UserController::class, 'show']);
    Route::put('/users/{id}',          [UserController::class, 'update']);
    Route::patch('/users/{id}/status', [UserController::class, 'updateStatus']);
    Route::delete('/users/{id}',       [UserController::class, 'destroy']);

    Route::get('/user-requests',                [UserRequestController::class, 'index']);
    Route::get('/user-requests/{id}',           [UserRequestController::class, 'show']);
    Route::post('/user-requests/{id}/approve',  [UserRequestController::class, 'approve']);
    Route::patch('/user-requests/{id}/reject',  [UserRequestController::class, 'reject']);
    Route::delete('/user-requests/{id}',        [UserRequestController::class, 'destroy']);

    Route::get('/travelers',               [TravelerController::class, 'index']);
    Route::post('/travelers',              [TravelerController::class, 'store']);
    Route::get('/travelers/{id}',          [TravelerController::class, 'show']);
    Route::put('/travelers/{id}',          [TravelerController::class, 'update']);
    Route::patch('/travelers/{id}/status', [TravelerController::class, 'updateStatus']);
    Route::delete('/travelers/{id}',       [TravelerController::class, 'destroy']);

    Route::get('/traveler-requests',                [TravelerRequestController::class, 'index']);
    Route::get('/traveler-requests/{id}',           [TravelerRequestController::class, 'show']);
    Route::post('/traveler-requests/{id}/approve',  [TravelerRequestController::class, 'approve']);
    Route::patch('/traveler-requests/{id}/reject',  [TravelerRequestController::class, 'reject']);
    Route::delete('/traveler-requests/{id}',        [TravelerRequestController::class, 'destroy']);
});

// TRAVELER ← GANTI auth:sanctum → multi.auth
Route::middleware(['multi.auth', 'role:traveler'])->group(function () {

    Route::get('/traveler/profile',        [ProfileController::class, 'show']);
    Route::put('/traveler/profile',        [ProfileController::class, 'update']);
    Route::post('/traveler/profile/photo', [ProfileController::class, 'updatePhoto']);
    Route::delete('/traveler/profile',     [ProfileController::class, 'destroy']);
});

// CUSTOMER ← GANTI auth:sanctum → multi.auth
Route::middleware(['multi.auth', 'role:customer'])->group(function () {

    Route::get('/customer/profile',              [ProfileController::class, 'show']);
    Route::put('/customer/profile',              [ProfileController::class, 'update']);
    Route::post('/customer/profile/photo',       [ProfileController::class, 'updatePhoto']);
    Route::delete('/customer/profile',           [ProfileController::class, 'destroy']);
});