<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\FriendController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\GameMoveController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\PlayerController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:6,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);

        Route::post('devices', [DeviceController::class, 'store']);
        Route::delete('devices/{token}', [DeviceController::class, 'destroy']);

        Route::get('players/{player:username}', [PlayerController::class, 'show']);

        Route::get('friends', [FriendController::class, 'index']);
        Route::post('friends', [FriendController::class, 'store']);
        Route::delete('friends/{friend}', [FriendController::class, 'destroy']);
        Route::post('invitations', [InvitationController::class, 'store'])->middleware('throttle:10,1');

        Route::get('games', [GameController::class, 'index']);
        Route::post('games', [GameController::class, 'store']);
        Route::get('games/{game}', [GameController::class, 'show']);
        Route::post('games/{game}/invite', [GameController::class, 'invite']);
        Route::post('games/{game}/accept', [GameController::class, 'accept']);
        Route::post('games/{game}/decline', [GameController::class, 'decline']);
        Route::post('games/{game}/resign', [GameController::class, 'resign']);
        Route::post('games/{game}/moves', [GameMoveController::class, 'store']);
    });
});
