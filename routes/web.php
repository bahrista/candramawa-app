<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\ContentPlanController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:10,1');
});

Route::middleware(['auth', 'active', 'password.changed'])->group(function () {
    Route::get('/', fn () => Inertia::render('Home'))->name('home');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/profile/avatar', [ProfileController::class, 'storeAvatar'])->name('profile.avatar.store');
    Route::delete('/profile/avatar', [ProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');
    Route::post('/profile/sessions/others', [ProfileController::class, 'logoutOtherSessions'])->name('profile.sessions.others');

    Route::get('/avatars/{user}', [AvatarController::class, 'show'])->name('avatars.show');

    Route::prefix('brands')->name('brands.')->group(function () {
        Route::get('/', [BrandController::class, 'index'])->name('index');
        Route::get('/create', [BrandController::class, 'create'])->name('create');
        Route::post('/', [BrandController::class, 'store'])->name('store');
        Route::get('/{brand}/edit', [BrandController::class, 'edit'])->name('edit');
        Route::put('/{brand}', [BrandController::class, 'update'])->name('update');
        Route::get('/{brand}/logo', [BrandController::class, 'logo'])->name('logo');
    });

    Route::prefix('content-plans')->name('content-plans.')->group(function () {
        Route::get('/', [ContentPlanController::class, 'index'])->name('index');
        Route::get('/create', [ContentPlanController::class, 'create'])->name('create');
        Route::post('/', [ContentPlanController::class, 'store'])->name('store');
        Route::get('/{contentPlan}', [ContentPlanController::class, 'show'])->name('show');
        Route::put('/{contentPlan}', [ContentPlanController::class, 'update'])->name('update');
        Route::delete('/{contentPlan}', [ContentPlanController::class, 'destroy'])->name('destroy');
    });

    Route::middleware('superadmin')->prefix('users')->name('users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/create', [UserController::class, 'create'])->name('create');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::get('/{user}/edit', [UserController::class, 'edit'])->name('edit');
        Route::put('/{user}', [UserController::class, 'update'])->name('update');
        Route::put('/{user}/password', [UserController::class, 'updatePassword'])->name('password');
        Route::patch('/{user}/active', [UserController::class, 'toggleActive'])->name('active');
    });
});
