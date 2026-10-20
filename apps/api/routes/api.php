<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CrmController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MfaController;
use App\Http\Controllers\Api\PlatformController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\TenantController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', HealthController::class)->middleware('throttle:60,1');
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
    Route::get('/auth/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])->middleware(['signed', 'throttle:10,1'])->name('verification.verify');
    Route::post('/auth/invitations/accept', [AuthController::class, 'acceptInvitation'])->middleware('throttle:10,1');
    Route::get('/plans', [TenantController::class, 'plans']);
    Route::get('/service-categories', [ServiceController::class, 'categories']);
    Route::post('/public/service-requests', [ServiceController::class, 'publicStore'])->middleware('throttle:20,1');
    Route::post('/public/service-requests/status', [ServiceController::class, 'publicStatus'])->middleware('throttle:30,1');
    Route::post('/public/service-requests/feedback', [ServiceController::class, 'publicFeedback'])->middleware('throttle:20,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/accept-terms', [AuthController::class, 'acceptTerms'])->middleware('throttle:10,1');
        Route::post('/auth/verification-notification', [AuthController::class, 'resendVerification'])->middleware('throttle:3,1');
        Route::get('/auth/mfa/setup', [MfaController::class, 'setup'])->middleware(['terms_accepted', 'verified_email', 'throttle:10,1']);
        Route::post('/auth/mfa/setup/rotate', [MfaController::class, 'rotateSetup'])->middleware(['terms_accepted', 'verified_email', 'throttle:5,1']);
        Route::post('/auth/mfa/enable', [MfaController::class, 'enable'])->middleware(['terms_accepted', 'verified_email', 'throttle:10,1']);
        Route::post('/auth/mfa/verify', [MfaController::class, 'verify'])->middleware(['terms_accepted', 'verified_email', 'throttle:10,1']);
        Route::post('/auth/mfa/recovery-codes', [MfaController::class, 'regenerateRecoveryCodes'])->middleware(['terms_accepted', 'verified_email', 'throttle:5,1']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/tenants', [TenantController::class, 'index'])->middleware('terms_accepted');
        Route::post('/tenants', [TenantController::class, 'store'])->middleware(['terms_accepted', 'verified_email']);
        Route::post('/tenants/{tenant}/switch', [TenantController::class, 'switch'])->middleware(['terms_accepted', 'verified_email']);

        Route::get('/professional/jobs', [ServiceController::class, 'professionalJobs'])->middleware(['terms_accepted', 'verified_email']);
        Route::patch('/professional/jobs/{id}', [ServiceController::class, 'updateProfessionalJob'])->middleware(['terms_accepted', 'verified_email']);

        Route::middleware(['tenant', 'terms_accepted', 'verified_email'])->group(function () {
            Route::get('/settings/company', [TenantController::class, 'settings']);
            Route::patch('/settings/company', [TenantController::class, 'updateSettings']);
            Route::get('/dashboard', [CrmController::class, 'dashboard']);
            Route::get('/contacts', [CrmController::class, 'contacts']);
            Route::post('/contacts', [CrmController::class, 'createContact']);
            Route::patch('/contacts/{id}', [CrmController::class, 'updateContact']);
            Route::get('/pipelines', [CrmController::class, 'pipelines']);
            Route::get('/opportunities', [CrmController::class, 'opportunities']);
            Route::post('/opportunities', [CrmController::class, 'createOpportunity']);
            Route::patch('/opportunities/{id}', [CrmController::class, 'updateOpportunity']);
            Route::get('/opportunities/{id}/history', [CrmController::class, 'opportunityHistory']);
            Route::get('/tasks', [CrmController::class, 'tasks']);
            Route::post('/tasks', [CrmController::class, 'createTask']);
            Route::patch('/tasks/{id}', [CrmController::class, 'updateTask']);
            Route::get('/service-requests', [ServiceController::class, 'requests']);
            Route::patch('/service-requests/{id}', [ServiceController::class, 'updateRequest']);
            Route::post('/service-requests/{id}/assignments', [ServiceController::class, 'assign']);
            Route::get('/professionals', [ServiceController::class, 'professionals']);
            Route::post('/professionals', [ServiceController::class, 'createProfessional']);
            Route::get('/memberships', [TeamController::class, 'index']);
            Route::post('/memberships', [TeamController::class, 'store']);
            Route::patch('/memberships/{id}', [TeamController::class, 'update']);
        });

        Route::middleware(['platform_owner', 'terms_accepted', 'verified_email', 'owner_mfa'])->prefix('platform')->group(function () {
            Route::get('/metrics', [PlatformController::class, 'metrics']);
            Route::get('/audit-logs', [PlatformController::class, 'auditLogs']);
            Route::get('/tenants', [PlatformController::class, 'tenants']);
            Route::patch('/tenants/{id}', [PlatformController::class, 'updateTenant']);
            Route::get('/plans', [PlatformController::class, 'plans']);
            Route::patch('/plans/{id}', [PlatformController::class, 'updatePlan']);
            Route::get('/service-categories', [PlatformController::class, 'categories']);
            Route::post('/service-categories', [PlatformController::class, 'createCategory']);
            Route::patch('/service-categories/{id}', [PlatformController::class, 'updateCategory']);
            Route::get('/professionals', [PlatformController::class, 'professionals']);
            Route::patch('/professionals/{id}', [PlatformController::class, 'updateProfessional']);
            Route::get('/service-requests', [PlatformController::class, 'publicRequests']);
            Route::post('/service-requests/{id}/assign', [PlatformController::class, 'assignRequest']);
        });
    });
});
