<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\SocialPostController;
use App\Http\Controllers\SocialPostAuthController;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::get('/test-jwt', function () {
    $user = User::first(); // Fetch any user from the database

    if (!$user) {
        return response()->json(['error' => 'No user found'], 404);
    }

    $token = JWTAuth::fromUser($user);

    return response()->json(['token' => $token]);
});

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login'])->name('login');
Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:api');
Route::post('refresh', [AuthController::class, 'refresh']);

Route::post('password/forgot', [AuthController::class, 'forgotPassword']);
Route::post('password/reset', [AuthController::class, 'resetPassword']);

Route::get('auth/{provider}/redirect', [SocialAuthController::class, 'redirect']);
Route::get('auth/{provider}/callback', [SocialAuthController::class, 'callback']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('social')->middleware(['auth:api'])->group(function () {
    Route::get('/auth/{provider}', [SocialPostAuthController::class, 'redirect'])->name('social.auth');
    Route::get('/auth/{provider}/callback', [SocialPostAuthController::class, 'callback'])->name('social.auth.callback');
    Route::post('/post/{provider}', [SocialPostController::class, 'post'])->name('social.post');
});

