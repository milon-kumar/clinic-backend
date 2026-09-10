<?php

use App\Http\Controllers\Api\V1\Admin\AppointmentController as AdminAppointmentController;
use App\Http\Controllers\Api\V1\Admin\ClinicController as AdminClinicController;
use App\Http\Controllers\Api\V1\Admin\DoctorController as AdminDoctorController;
use App\Http\Controllers\Api\V1\Admin\InventoryController as AdminInventoryController;
use App\Http\Controllers\Api\V1\Admin\InvoiceController as AdminInvoiceController;
use App\Http\Controllers\Api\V1\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\V1\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Api\V1\Admin\RoleController as AdminRoleController;
use App\Http\Controllers\Api\V1\Admin\ServiceController as AdminServiceController;
use App\Http\Controllers\Api\V1\Admin\HomeBlockController as AdminHomeBlockController;
use App\Http\Controllers\Api\V1\Admin\CategoryLandingController as AdminCategoryLandingController;
use App\Http\Controllers\Api\V1\Admin\HomeSlideController as AdminHomeSlideController;
use App\Http\Controllers\Api\V1\Admin\SiteSettingController as AdminSiteSettingController;
use App\Http\Controllers\Api\V1\Admin\StaffController as AdminStaffController;
use App\Http\Controllers\Api\V1\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookController;
use App\Http\Controllers\Api\V1\BuyController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\ClinicController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DoctorController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ServiceController;
use App\Http\Controllers\Api\V1\HomeBlockController;
use App\Http\Controllers\Api\V1\CategoryLandingController;
use App\Http\Controllers\Api\V1\HomeSlideController;
use App\Http\Controllers\Api\V1\SiteSettingController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('verify-email', [AuthController::class, 'verifyEmail']);
        Route::post('resend-otp', [AuthController::class, 'resendOtp']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:6,1');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:10,1');

        Route::middleware(['auth:sanctum', 'staff:superadmin,admin,manager'])->group(function () {
            Route::post('create-staff', [AuthController::class, 'createStaff']);
        });
    });

    Route::get('clinics', [ClinicController::class, 'index']);
    Route::get('clinics/{id}', [ClinicController::class, 'show']);
    Route::get('clinics/{id}/services', [CatalogController::class, 'services']);
    Route::get('clinics/{clinicId}/services/{slug}', [CatalogController::class, 'serviceBySlug']);

    Route::get('services', [ServiceController::class, 'index']);
    Route::get('services/{serviceId}/prerequisites', [CatalogController::class, 'prerequisites'])->whereNumber('serviceId');
    Route::get('services/{id}', [ServiceController::class, 'show'])->whereNumber('id');

    Route::get('settings', [SiteSettingController::class, 'show']);
    Route::get('slides', [HomeSlideController::class, 'index']);
    Route::get('home-blocks', [HomeBlockController::class, 'index']);
    Route::get('landings', [CategoryLandingController::class, 'index']);
    Route::get('landings/{slug}', [CategoryLandingController::class, 'show']);
    Route::post('contact', [ContactController::class, 'store']);
    Route::get('doctors', [DoctorController::class, 'index']);
    Route::get('doctors/{id}', [DoctorController::class, 'show'])->whereNumber('id');

    Route::middleware('auth:sanctum')->group(function () {
        Route::put('session/clinic', [ClinicController::class, 'setSessionClinic']);

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::patch('notifications/{id}/read', [NotificationController::class, 'markRead'])->whereNumber('id');
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);

        Route::get('cart', [CartController::class, 'show']);
        Route::post('cart/lines', [CartController::class, 'addLine']);
        Route::patch('cart/lines/{lineId}', [CartController::class, 'updateLine']);
        Route::patch('cart/clinic', [CartController::class, 'updateClinic']);
        Route::post('cart/promo', [CartController::class, 'applyPromo']);
        Route::delete('cart/lines/{lineId}', [CartController::class, 'removeLine']);

        Route::post('buy/checkout/start', [BuyController::class, 'start']);
        Route::post('buy/checkout/clinic', [BuyController::class, 'clinic']);
        Route::post('buy/checkout/finalise', [BuyController::class, 'finalise']);
        Route::post('buy/checkout/payment-intent', [BuyController::class, 'paymentIntent']);
        Route::post('buy/checkout/confirm', [BuyController::class, 'confirm']);

        Route::post('payment/checkout', [PaymentController::class, 'checkout']);
        Route::get('payment/session/{sessionId}', [PaymentController::class, 'session']);

        Route::post('book/cart/prior-treatment', [BookController::class, 'priorTreatment']);
        Route::post('book/cart/addons', [BookController::class, 'addons']);
        Route::get('book/availability', [BookController::class, 'availability']);
        Route::post('book/holds', [BookController::class, 'createHold']);
        Route::delete('book/holds/{holdId}', [BookController::class, 'releaseHold']);
        Route::post('book/appointments/confirm', [BookController::class, 'confirmAppointment']);

        Route::middleware('verified.email')->prefix('customers/me')->group(function () {
            Route::get('/', [CustomerController::class, 'me']);
            Route::patch('/', [CustomerController::class, 'updateMe']);
            Route::get('appointments', [CustomerController::class, 'appointments']);
            Route::get('appointments/{id}', [CustomerController::class, 'appointment'])->whereNumber('id');
            Route::get('packages', [CustomerController::class, 'packages']);
        });

        Route::prefix('admin')->middleware('staff:superadmin,admin,manager,receptionist,practitioner')->group(function () {
            Route::get('appointments/stats', [AdminAppointmentController::class, 'stats']);
            Route::get('appointments/availability', [AdminAppointmentController::class, 'availability']);
            Route::patch('appointments/{id}/confirm', [AdminAppointmentController::class, 'confirm']);
            Route::patch('appointments/{id}/cancel', [AdminAppointmentController::class, 'cancel']);
            Route::patch('appointments/{id}/complete', [AdminAppointmentController::class, 'complete']);
            Route::apiResource('appointments', AdminAppointmentController::class);

            Route::post('services/upload', [AdminServiceController::class, 'upload']);
            Route::apiResource('services', AdminServiceController::class);

            Route::get('settings', [AdminSiteSettingController::class, 'show']);
            Route::patch('settings', [AdminSiteSettingController::class, 'update']);
            Route::post('settings/upload', [AdminSiteSettingController::class, 'upload']);
            Route::post('settings/test-email', [AdminSiteSettingController::class, 'testEmail']);

            Route::get('slides', [AdminHomeSlideController::class, 'index']);
            Route::post('slides', [AdminHomeSlideController::class, 'store']);
            Route::post('slides/upload', [AdminHomeSlideController::class, 'upload']);
            Route::patch('slides/{id}', [AdminHomeSlideController::class, 'update'])->whereNumber('id');
            Route::delete('slides/{id}', [AdminHomeSlideController::class, 'destroy'])->whereNumber('id');

            Route::get('home-blocks', [AdminHomeBlockController::class, 'index']);
            Route::post('home-blocks/upload', [AdminHomeBlockController::class, 'upload']);
            Route::patch('home-blocks/{id}', [AdminHomeBlockController::class, 'update'])->whereNumber('id');

            Route::get('landings', [AdminCategoryLandingController::class, 'index']);
            Route::post('landings', [AdminCategoryLandingController::class, 'store']);
            Route::post('landings/upload', [AdminCategoryLandingController::class, 'upload']);
            Route::patch('landings/{id}', [AdminCategoryLandingController::class, 'update'])->whereNumber('id');
            Route::delete('landings/{id}', [AdminCategoryLandingController::class, 'destroy'])->whereNumber('id');

            Route::get('roles', [AdminRoleController::class, 'index']);
            Route::apiResource('users', AdminUserController::class);

            Route::get('reports', AdminReportController::class);

            Route::get('orders', [AdminOrderController::class, 'index']);
            Route::get('orders/{id}', [AdminOrderController::class, 'show']);
            Route::get('carts', [AdminOrderController::class, 'carts']);

            Route::get('inventory', [AdminInventoryController::class, 'index']);
            Route::post('inventory/restock', [AdminInventoryController::class, 'restock']);
            Route::get('inventory/movements', [AdminInventoryController::class, 'movements']);
            Route::get('suppliers', [AdminInventoryController::class, 'suppliers']);
            Route::post('suppliers', [AdminInventoryController::class, 'storeSupplier']);

            Route::get('invoices', [AdminInvoiceController::class, 'index']);
            Route::post('invoices', [AdminInvoiceController::class, 'store']);
            Route::get('invoices/{id}', [AdminInvoiceController::class, 'show']);

            Route::get('clinics/{id}/staff', [AdminStaffController::class, 'index']);
            Route::post('clinics/{id}/staff', [AdminStaffController::class, 'store']);
            Route::delete('clinics/{id}/staff/{userId}', [AdminStaffController::class, 'destroy']);

            Route::get('doctors', [AdminDoctorController::class, 'index']);
            Route::post('doctors', [AdminDoctorController::class, 'store']);
            Route::post('doctors/upload', [AdminDoctorController::class, 'upload']);
            Route::get('doctors/{id}', [AdminDoctorController::class, 'show'])->whereNumber('id');
            Route::put('doctors/{id}', [AdminDoctorController::class, 'update'])->whereNumber('id');
            Route::delete('doctors/{id}', [AdminDoctorController::class, 'destroy'])->whereNumber('id');
            Route::post('clinics/{id}/doctors', [AdminDoctorController::class, 'assign']);
            Route::delete('clinics/{id}/doctors/{doctorId}', [AdminDoctorController::class, 'unassign']);
            Route::get('staff-schedules', [AdminStaffController::class, 'schedules']);
            Route::post('staff-schedules', [AdminStaffController::class, 'storeSchedule']);
            Route::delete('staff-schedules/{id}', [AdminStaffController::class, 'destroySchedule']);

            Route::get('clinics/{id}/matrix', [AdminClinicController::class, 'matrix']);
            Route::put('clinics/{id}/matrix', [AdminClinicController::class, 'updateMatrix']);
            Route::get('clinics/{id}/schedules', [AdminClinicController::class, 'schedules']);
            Route::post('clinics/{id}/schedules', [AdminClinicController::class, 'storeSchedule']);
            Route::patch('schedules/{scheduleId}', [AdminClinicController::class, 'updateSchedule']);
            Route::delete('schedules/{scheduleId}', [AdminClinicController::class, 'destroySchedule']);
            Route::get('clinics/{id}/closures', [AdminClinicController::class, 'closures']);
            Route::post('clinics/{id}/closures', [AdminClinicController::class, 'storeClosure']);
            Route::delete('closures/{closureId}', [AdminClinicController::class, 'destroyClosure']);
            Route::apiResource('clinics', AdminClinicController::class);
        });
    });
});
