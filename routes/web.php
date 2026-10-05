<?php

use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DailyMenuController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeliveryAreaController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SettingController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [LoginController::class, 'create'])->name('login');
        Route::post('login', [LoginController::class, 'store'])->name('login.store');
    });

    Route::post('logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

    Route::middleware(['auth', 'admin'])->group(function () {
        Route::get('/', fn () => redirect()->route('admin.dashboard'));
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('orders/{order}/transition', [OrderController::class, 'transition'])->name('orders.transition');
        Route::get('orders/{order}/proof', [OrderController::class, 'proof'])->name('orders.proof');

        Route::resource('categories', CategoryController::class)->except('show');
        Route::patch('categories/{category}/toggle', [CategoryController::class, 'toggle'])->name('categories.toggle');

        Route::resource('products', ProductController::class)->except('show');
        Route::patch('products/{product}/toggle', [ProductController::class, 'toggle'])->name('products.toggle');

        Route::resource('daily-menus', DailyMenuController::class)->except('show');

        Route::resource('delivery-areas', DeliveryAreaController::class)->except('show');
        Route::patch('delivery-areas/{delivery_area}/toggle', [DeliveryAreaController::class, 'toggle'])->name('delivery-areas.toggle');

        Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
    });
});
