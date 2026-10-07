<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AppVersionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\Manager\CompanyController;
use App\Http\Controllers\Manager\CustomerController;
use App\Http\Controllers\Manager\DashboardController;
use App\Http\Controllers\Manager\QuoteController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SubscriptionPlanController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Préfixe : /api (bootstrap/app.php)

// Version attendue de l'application mobile (public : vérifiée avant la connexion)
Route::get('app-version', AppVersionController::class)->middleware('throttle:60,1');

// Forfaits proposés et contact pour payer (public : affiché aussi au gestionnaire bloqué)
Route::get('subscription-offers', [SubscriptionPlanController::class, 'offers'])->middleware('throttle:60,1');

Route::prefix('auth')->name('auth.')->group(function () {

    Route::middleware('throttle:auth')->group(function () {
        Route::post('login',   [AuthController::class, 'login'])->name('login');
        Route::post('refresh', [AuthController::class, 'refresh'])->name('refresh');
    });

    // Codes OTP WhatsApp : inscription, première connexion, numéro modifié, mot de passe oublié
    Route::middleware('throttle:otp')->group(function () {
        Route::post('register',        [AuthController::class, 'register'])->middleware('throttle:signup')->name('register');
        Route::post('otp/verify',      [AuthController::class, 'verifyOtp'])->name('otp.verify');
        Route::post('otp/resend',      [AuthController::class, 'resendOtp'])->name('otp.resend');
        Route::post('password/set',    [AuthController::class, 'setPassword'])->name('password.set');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
        Route::post('reset-password',  [AuthController::class, 'resetPassword'])->name('reset-password');
    });

    Route::middleware('jwt.auth')->group(function () {
        Route::post('logout',          [AuthController::class, 'logout'])->name('logout');
        // Gestionnaire : coupé aussi ici quand l'abonnement a expiré
        Route::middleware('subscription')->group(function () {
            Route::get('me',               [AuthController::class, 'me'])->name('me');
            Route::put('me',               [AuthController::class, 'updateMe'])->name('update-me');
            Route::post('change-password', [AuthController::class, 'changePassword'])->name('change-password');
        });
    });
});

// -----------------------------------------------------------------------------
// Administrateur de la plateforme (application web Angular)
// -----------------------------------------------------------------------------
Route::middleware(['jwt.auth', 'role:ADMIN'])->group(function () {

    Route::get('/admin/dashboard', AdminDashboardController::class);

    Route::prefix('users')->group(function () {
        Route::get('/list',                    [UserController::class, 'index']);
        Route::post('/add',                    [UserController::class, 'store']);
        Route::get('/show/{id}',               [UserController::class, 'show']);
        Route::put('/update/{id}',             [UserController::class, 'update']);
        Route::put('/toggle-status/{id}',      [UserController::class, 'toggleStatus']);
        Route::post('/reset-access/{id}',      [UserController::class, 'resetAccess']);
        Route::delete('/disable/{id}',         [UserController::class, 'disable']);
        Route::delete('/destroy/{id}/force',   [UserController::class, 'destroy']);
        Route::post('/restore/{id}',           [UserController::class, 'restore']);
    });

    Route::prefix('roles')->group(function () {
        Route::get('/list', [RoleController::class, 'index']);
    });

    // Entreprises
    Route::prefix('tenants')->group(function () {
        Route::get('/list',                    [TenantController::class, 'index']);
        Route::post('/add',                    [TenantController::class, 'store']);
        Route::get('/show/{id}',               [TenantController::class, 'show']);
        // POST accepté en plus de PUT : formulaire multipart avec logo
        Route::match(['put', 'post'], '/update/{id}', [TenantController::class, 'update']);
        Route::put('/toggle-status/{id}',      [TenantController::class, 'toggleStatus']);
        Route::delete('/disable/{id}',         [TenantController::class, 'disable']);
        Route::delete('/destroy/{id}/force',   [TenantController::class, 'destroy']);
        Route::post('/restore/{id}',           [TenantController::class, 'restore']);
        // Inscriptions faites depuis l'application
        Route::put('/approve/{id}',            [TenantController::class, 'approve']);
        Route::put('/reject/{id}',             [TenantController::class, 'reject']);
    });

    // Types d'abonnement (forfaits) : prix et durée non modifiables
    Route::prefix('subscription-plans')->group(function () {
        Route::get('/list',               [SubscriptionPlanController::class, 'index']);
        Route::post('/add',               [SubscriptionPlanController::class, 'store']);
        Route::put('/update/{id}',        [SubscriptionPlanController::class, 'update']);
        Route::put('/toggle-status/{id}', [SubscriptionPlanController::class, 'toggleStatus']);
        Route::delete('/delete/{id}',     [SubscriptionPlanController::class, 'destroy']);
    });

    Route::prefix('subscriptions')->group(function () {
        Route::get('/overview',          [SubscriptionController::class, 'overview']);
        Route::get('/tenant/{tenantId}', [SubscriptionController::class, 'index']);
        Route::post('/add',              [SubscriptionController::class, 'store']);
        Route::put('/update/{id}',       [SubscriptionController::class, 'update']);
        Route::delete('/delete/{id}',    [SubscriptionController::class, 'destroy']);
    });
});

// -----------------------------------------------------------------------------
// Gestionnaire d'une entreprise (application mobile Flutter)
// Accès coupé si l'entreprise est désactivée ou l'abonnement expiré.
// -----------------------------------------------------------------------------
Route::prefix('manager')->middleware(['jwt.auth', 'role:MANAGER', 'subscription'])->group(function () {

    Route::get('/dashboard', DashboardController::class);

    Route::get('/company',      [CompanyController::class, 'show']);
    Route::post('/company',     [CompanyController::class, 'update']);
    Route::get('/company/stamp', [CompanyController::class, 'stamp']);
    Route::put('/company/stamp', [CompanyController::class, 'updateStamp']);
    Route::get('/subscription', [CompanyController::class, 'subscription']);

    Route::get('/customers',         [CustomerController::class, 'index']);
    Route::post('/customers',        [CustomerController::class, 'store']);
    Route::get('/customers/{id}',    [CustomerController::class, 'show'])->whereNumber('id');
    Route::put('/customers/{id}',    [CustomerController::class, 'update'])->whereNumber('id');
    Route::delete('/customers/{id}', [CustomerController::class, 'destroy'])->whereNumber('id');

    Route::get('/quotes',                    [QuoteController::class, 'index']);
    Route::post('/quotes',                   [QuoteController::class, 'store']);
    Route::get('/quotes/units',              [QuoteController::class, 'units']);
    Route::get('/quotes/{id}',               [QuoteController::class, 'show'])->whereNumber('id');
    Route::put('/quotes/{id}',               [QuoteController::class, 'update'])->whereNumber('id');
    Route::delete('/quotes/{id}',            [QuoteController::class, 'destroy'])->whereNumber('id');
    Route::post('/quotes/{id}/duplicate',    [QuoteController::class, 'duplicate'])->whereNumber('id');
    Route::post('/quotes/{id}/mark-sent',    [QuoteController::class, 'markSent'])->whereNumber('id');
    Route::post('/quotes/{id}/decision',     [QuoteController::class, 'decide'])->whereNumber('id');
    Route::put('/quotes/{id}/deposit',       [QuoteController::class, 'recordDeposit'])->whereNumber('id');
    Route::delete('/quotes/{id}/deposit',    [QuoteController::class, 'cancelDeposit'])->whereNumber('id');
    Route::get('/quotes/{id}/pdf',           [QuoteController::class, 'pdf'])->whereNumber('id');
    Route::post('/quotes/{id}/whatsapp',     [QuoteController::class, 'whatsapp'])->whereNumber('id')->middleware('throttle:20,1');

    // Catalogue : suggestions pour les lignes de devis
    Route::prefix('categories')->group(function () {
        Route::get('/list',           [CategoryController::class, 'index']);
        Route::post('/add',           [CategoryController::class, 'store']);
        Route::get('/show/{id}',      [CategoryController::class, 'show']);
        Route::put('/update/{id}',    [CategoryController::class, 'update']);
        Route::delete('/delete/{id}', [CategoryController::class, 'destroy']);
    });

    Route::prefix('products')->group(function () {
        Route::get('/list',                     [ProductController::class, 'index']);
        Route::post('/add',                     [ProductController::class, 'store']);
        Route::get('/show/{id}',                [ProductController::class, 'show']);
        Route::put('/update/{id}',              [ProductController::class, 'update']);
        Route::put('/toggle-availability/{id}', [ProductController::class, 'toggleAvailability']);
        Route::delete('/delete/{id}',           [ProductController::class, 'destroy']);
    });
});
