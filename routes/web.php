<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\BrandKnowledgeController;
use App\Http\Controllers\BrandPillarController;
use App\Http\Controllers\BrandProductController;
use App\Http\Controllers\ContentPlanController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    // A loose limit per IP address; the real brute-force protection is per username inside the controller.
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:120,1');
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
        Route::get('/{brand}', [BrandController::class, 'show'])->name('show');
        Route::get('/{brand}/edit', [BrandController::class, 'edit'])->name('edit');
        Route::put('/{brand}', [BrandController::class, 'update'])->name('update');
        Route::get('/{brand}/logo', [BrandController::class, 'logo'])->name('logo');
        Route::put('/{brand}/team', [BrandController::class, 'updateTeam'])->name('team.update');

        // Everything below belongs to one brand; scoped bindings return 404 for another brand's entry.
        Route::scopeBindings()->group(function () {
            Route::post('/{brand}/pillars', [BrandPillarController::class, 'store'])->name('pillars.store');
            Route::put('/{brand}/pillars/{pillar}', [BrandPillarController::class, 'update'])->name('pillars.update');
            Route::patch('/{brand}/pillars/{pillar}/archive', [BrandPillarController::class, 'archive'])->name('pillars.archive');
            Route::delete('/{brand}/pillars/{pillar}', [BrandPillarController::class, 'destroy'])->name('pillars.destroy');

            Route::get('/{brand}/knowledge/create', [BrandKnowledgeController::class, 'create'])->name('knowledge.create');
            Route::post('/{brand}/knowledge', [BrandKnowledgeController::class, 'store'])->name('knowledge.store');
            Route::get('/{brand}/knowledge/{knowledgeItem}', [BrandKnowledgeController::class, 'show'])->name('knowledge.show');
            Route::get('/{brand}/knowledge/{knowledgeItem}/edit', [BrandKnowledgeController::class, 'edit'])->name('knowledge.edit');
            Route::put('/{brand}/knowledge/{knowledgeItem}', [BrandKnowledgeController::class, 'update'])->name('knowledge.update');
            Route::delete('/{brand}/knowledge/{knowledgeItem}', [BrandKnowledgeController::class, 'destroy'])->name('knowledge.destroy');

            Route::get('/{brand}/products/create', [BrandProductController::class, 'create'])->name('products.create');
            Route::post('/{brand}/products', [BrandProductController::class, 'store'])->name('products.store');
            Route::get('/{brand}/products/{product}', [BrandProductController::class, 'show'])->name('products.show');
            Route::get('/{brand}/products/{product}/edit', [BrandProductController::class, 'edit'])->name('products.edit');
            Route::put('/{brand}/products/{product}', [BrandProductController::class, 'update'])->name('products.update');
            Route::delete('/{brand}/products/{product}', [BrandProductController::class, 'destroy'])->name('products.destroy');
        });
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
