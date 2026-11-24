<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PinLoginController;
use App\Http\Controllers\Cashier\CashierController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Guest\GuestController;
use App\Http\Controllers\Guest\ReviewController as GuestReviewController;
use App\Http\Controllers\Guests\GuestController as GuestBaseController;
use App\Http\Controllers\Hall\HallController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Kitchen\KitchenController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Menu\MenuController;
use App\Http\Controllers\Menu\StopListController;
use App\Http\Controllers\Menu\TableController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Reviews\ReviewController;
use App\Http\Controllers\Staff\ActionLogController;
use App\Http\Controllers\Staff\RoleController;
use App\Http\Controllers\Staff\SettingsController;
use App\Http\Controllers\Staff\UserController;
use App\Http\Controllers\Waiter\WaiterController;
use Illuminate\Support\Facades\Route;

Route::post('/locale/{locale}', LocaleController::class)->name('locale.switch');

/*
| Страница для гостей: QR-код стола → меню → заказ. Без регистрации.
*/
Route::name('guest.')->group(function () {
    Route::get('/t/{token}', [GuestController::class, 'menu'])->name('menu');
    Route::get('/t/{token}/menu.json', [GuestController::class, 'menuJson'])->name('menu.json');
    Route::post('/t/{token}/orders', [GuestController::class, 'store'])->middleware('throttle:guest-orders')->name('orders.store');
    Route::get('/o/{publicToken}', [GuestController::class, 'show'])->name('order');
    Route::get('/o/{publicToken}/status.json', [GuestController::class, 'status'])->name('order.status');
    Route::get('/r/{token}', [GuestReviewController::class, 'show'])->name('review');
    Route::post('/r/{token}', [GuestReviewController::class, 'store'])->middleware('throttle:guest-orders')->name('review.store');
});

/*
| Вход: логин+пароль или PIN на общем планшете.
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
    Route::get('/pin', [PinLoginController::class, 'create'])->name('pin');
    Route::post('/pin', [PinLoginController::class, 'store'])->name('pin.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/', HomeController::class)->name('home');
    Route::get('/no-access', [HomeController::class, 'noAccess'])->name('no-access');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', DashboardController::class)->middleware('permission:reports')->name('dashboard');

    // Отчёты за период (ТЗ 2.10)
    Route::middleware('permission:reports')->prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/export/{report}', [ReportController::class, 'export'])->whereIn('report', ['revenue', 'load', 'dishes', 'staff'])->name('export');
    });

    // Меню и цены
    Route::middleware('permission:menu_prices')->prefix('menu')->name('menu.')->group(function () {
        Route::get('/', [MenuController::class, 'index'])->name('index');
        Route::post('/categories', [MenuController::class, 'storeCategory'])->name('categories.store');
        Route::put('/categories/{category}', [MenuController::class, 'updateCategory'])->name('categories.update');
        Route::delete('/categories/{category}', [MenuController::class, 'destroyCategory'])->name('categories.destroy');
        Route::get('/dishes/create', [MenuController::class, 'createDish'])->name('dishes.create');
        Route::post('/dishes', [MenuController::class, 'storeDish'])->name('dishes.store');
        Route::get('/dishes/{dish}/edit', [MenuController::class, 'editDish'])->name('dishes.edit');
        // Форма с фото уходит как POST multipart с _method=PUT (method spoofing).
        Route::put('/dishes/{dish}', [MenuController::class, 'updateDish'])->name('dishes.update');
        Route::delete('/dishes/{dish}', [MenuController::class, 'destroyDish'])->name('dishes.destroy');
    });

    // Стоп-лист
    Route::middleware('permission:stop_list')->prefix('stop-list')->name('stop-list.')->group(function () {
        Route::get('/', [StopListController::class, 'index'])->name('index');
        Route::post('/', [StopListController::class, 'store'])->name('store');
        Route::delete('/{entry}', [StopListController::class, 'destroy'])->name('destroy');
    });

    // Схема зала (ТЗ 2.2): работа — официант, столы — редактор, отчёты — просмотр
    Route::get('/hall', [HallController::class, 'index'])->middleware('permission:area_waiter,tables,reports')->name('hall.index');
    Route::middleware('permission:tables')->prefix('halls')->name('halls.')->group(function () {
        Route::post('/', [HallController::class, 'store'])->name('store');
        Route::put('/{hall}', [HallController::class, 'update'])->name('update');
        Route::delete('/{hall}', [HallController::class, 'destroy'])->name('destroy');
        Route::put('/{hall}/layout', [HallController::class, 'layout'])->name('layout');
    });

    // База гостей (ТЗ 2.1); экспорт — отдельное право
    Route::get('/guests/export', [GuestBaseController::class, 'export'])->middleware('permission:export_guests')->name('guests.export');
    Route::middleware('permission:guests')->prefix('guests')->name('guests.')->group(function () {
        Route::get('/', [GuestBaseController::class, 'index'])->name('index');
        Route::post('/', [GuestBaseController::class, 'store'])->name('store');
        Route::get('/{guest}', [GuestBaseController::class, 'show'])->whereNumber('guest')->name('show');
        Route::put('/{guest}', [GuestBaseController::class, 'update'])->whereNumber('guest')->name('update');
        Route::delete('/{guest}', [GuestBaseController::class, 'destroy'])->whereNumber('guest')->name('destroy');
    });

    // Столы и QR-коды
    Route::middleware('permission:tables')->prefix('tables')->name('tables.')->group(function () {
        Route::get('/', [TableController::class, 'index'])->name('index');
        Route::get('/print', [TableController::class, 'print'])->name('print');
        Route::post('/', [TableController::class, 'store'])->name('store');
        Route::put('/{table}', [TableController::class, 'update'])->name('update');
        Route::delete('/{table}', [TableController::class, 'destroy'])->name('destroy');
        Route::post('/{table}/regenerate', [TableController::class, 'regenerate'])->name('regenerate');
    });

    // Сотрудники и права
    Route::middleware('permission:staff_permissions')->group(function () {
        Route::get('/staff', [UserController::class, 'index'])->name('staff.index');
        Route::get('/staff/create', [UserController::class, 'create'])->name('staff.create');
        Route::post('/staff', [UserController::class, 'store'])->name('staff.store');
        Route::get('/staff/{user}/edit', [UserController::class, 'edit'])->name('staff.edit');
        Route::put('/staff/{user}', [UserController::class, 'update'])->name('staff.update');
        Route::post('/staff/{user}/toggle', [UserController::class, 'toggle'])->name('staff.toggle');

        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    });

    Route::middleware('permission:settings')->group(function () {
        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    });

    Route::get('/logs', [ActionLogController::class, 'index'])->middleware('permission:action_log')->name('logs.index');
    Route::get('/reviews', [ReviewController::class, 'index'])->middleware('permission:reviews')->name('reviews.index');

    // Экран официанта
    Route::middleware('permission:area_waiter')->prefix('waiter')->name('waiter.')->group(function () {
        Route::get('/', [WaiterController::class, 'index'])->name('index');
        Route::post('/tables/{table}/open', [WaiterController::class, 'open'])->name('open');
        Route::get('/orders/{order}', [WaiterController::class, 'show'])->name('order');
        Route::post('/orders/{order}/items', [WaiterController::class, 'addItems'])->name('items.store');
        Route::patch('/items/{item}', [WaiterController::class, 'updateItem'])->name('items.update');
        Route::delete('/items/{item}', [WaiterController::class, 'removeItem'])->name('items.destroy');
        Route::post('/orders/{order}/send', [WaiterController::class, 'send'])->name('send');
        Route::post('/orders/{order}/batches/{batch}/confirm', [WaiterController::class, 'confirmQr'])->whereNumber('batch')->name('qr.confirm');
        Route::post('/orders/{order}/batches/{batch}/reject', [WaiterController::class, 'rejectQr'])->whereNumber('batch')->name('qr.reject');
        Route::post('/orders/{order}/batches/{batch}/served', [WaiterController::class, 'served'])->whereNumber('batch')->name('served');
        Route::post('/orders/{order}/bill', [WaiterController::class, 'bill'])->name('bill');
        Route::post('/orders/{order}/transfer', [WaiterController::class, 'transfer'])->name('transfer');
        Route::post('/orders/{order}/move', [WaiterController::class, 'move'])->name('move');
        Route::get('/orders/{order}/print', [WaiterController::class, 'printBill'])->name('print');
    });

    // Экран кухни и бара
    Route::middleware('permission:area_kitchen')->prefix('kitchen')->name('kitchen.')->group(function () {
        Route::post('/orders/{order}/batches/{batch}/ready', [KitchenController::class, 'ready'])->whereNumber('batch')->name('ready');
        Route::get('/{station?}', [KitchenController::class, 'index'])->whereIn('station', ['kitchen', 'bar'])->name('index');
    });

    // Касса
    Route::middleware('permission:area_cashier')->prefix('cashier')->name('cashier.')->group(function () {
        Route::get('/', [CashierController::class, 'index'])->name('index');
        Route::post('/orders/{order}/pay', [CashierController::class, 'pay'])->name('pay');
        Route::get('/orders/{order}/receipt', [CashierController::class, 'receipt'])->name('receipt');
        Route::post('/payments/{payment}/cancel', [CashierController::class, 'cancel'])->name('cancel');
    });
});
