<?php

use App\Http\Controllers\TelescopeAuthController;
use App\Http\Middleware\AuthenticateTelescope;
use Illuminate\Support\Facades\Route;

if (app()->environment(['local', 'testing']) && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
    Route::get('/telescope-login', [TelescopeAuthController::class, 'show'])
        ->name('telescope.login');
    Route::post('/telescope-login', [TelescopeAuthController::class, 'login'])
        ->middleware('throttle:dny-login')
        ->name('telescope.login.store');
    Route::post('/telescope-logout', [TelescopeAuthController::class, 'logout'])
        ->middleware(AuthenticateTelescope::class)
        ->name('telescope.logout');
}

/*
|--------------------------------------------------------------------------
| Web Routes — SPA Catch-All
|--------------------------------------------------------------------------
|
| Setiap panel (public, member, admin) memiliki catch-all route
| yang meneruskan semua request ke Blade template masing-masing.
| Vue Router akan menghandle routing di sisi client.
|
| Middleware auth/role akan ditambahkan nanti oleh BE developer.
|
*/

// Admin SPA — harus didefinisikan secara spesifik
Route::get('/admin/{any?}', function () {
    return view('admin');
})->where('any', '.*')->name('admin');

// Member SPA
Route::get('/member/{any?}', function () {
    return view('member');
})->where('any', '.*')->name('member');

// Redirect root ke member login
Route::get('/', function () {
    return redirect('/member/login');
});
