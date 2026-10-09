<?php

use App\Http\Controllers\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Admin\CalendarController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FacilitySettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BookingController;
use Illuminate\Support\Facades\Route;

// Public Portal Routes
Route::get('/', [BookingController::class, 'index'])->name('home');
Route::get('/book', [BookingController::class, 'create'])->name('bookings.create');
Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
Route::get('/booking/success/{code}', [BookingController::class, 'success'])->name('booking.success');
Route::get('/track', [BookingController::class, 'track'])->name('bookings.track');
Route::post('/track', [BookingController::class, 'trackSearch'])->name('bookings.track.search');

// API Routes for AJAX calls
Route::post('/api/check-availability', [BookingController::class, 'checkSlotAvailability'])->name('api.check-availability');
Route::get('/api/facilities/{facility}/slots', [BookingController::class, 'availableSlots'])->name('api.facility-slots');

// QR Code Route
Route::get('/qr/{code}', [BookingController::class, 'qrRedirect'])->name('qr.redirect');

// Admin Authentication Routes
Route::get('/admin/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/admin/login', [LoginController::class, 'login'])->name('login.submit');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Admin Dashboard Routes (Protected)
Route::middleware(['admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
    Route::post('/bookings/{booking}/approve', [DashboardController::class, 'approve'])->name('admin.approve');
    Route::post('/bookings/{booking}/reject', [DashboardController::class, 'reject'])->name('admin.reject');
    Route::post('/bookings/{booking}/status', [DashboardController::class, 'updateStatus'])->name('admin.bookings.status.update');
    Route::get('/bookings/{booking}/edit', [AdminBookingController::class, 'edit'])->name('admin.bookings.edit');
    Route::put('/bookings/{booking}', [AdminBookingController::class, 'update'])->name('admin.bookings.update');
    Route::get('/visitations/create', [AdminBookingController::class, 'createOnSite'])->name('admin.visitations.create');
    Route::post('/visitations', [AdminBookingController::class, 'storeOnSite'])->name('admin.visitations.store');
    Route::get('/calendar', [CalendarController::class, 'index'])->name('admin.calendar');
    Route::get('/facilities/settings', [FacilitySettingsController::class, 'index'])->name('admin.facilities.settings');
    Route::put('/facilities/{facility}/capacity', [FacilitySettingsController::class, 'updateCapacity'])->name('admin.facilities.capacity.update');
    Route::patch('/facilities/{facility}/branding', [FacilitySettingsController::class, 'updateBranding'])->name('admin.facilities.branding.update');
    Route::get('/facilities/{facility}/schedule', [FacilitySettingsController::class, 'editSchedule'])->name('admin.facilities.schedule.edit');
    Route::put('/facilities/{facility}/schedule', [FacilitySettingsController::class, 'updateSchedule'])->name('admin.facilities.schedule.update');

    Route::middleware('super-admin')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('admin.users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('admin.users.create');
        Route::post('/users', [UserController::class, 'store'])->name('admin.users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('admin.users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('admin.users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');
    });
});
