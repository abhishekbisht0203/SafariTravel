<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ContentController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\LeadController;
use App\Http\Controllers\Api\V1\LeadManagementController;
use App\Http\Controllers\Api\V1\LeadNoteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Safari Travel API — v1
|--------------------------------------------------------------------------
|
| Registered by bootstrap/app.php with the `api` middleware group and the
| `/api` prefix, so everything here is reachable at /api/v1/... . This file
| never boots WordPress and WordPress never boots Laravel: the two
| applications communicate only over HTTP.
|
| Three trust levels:
|
|   public        — lead intake and content reads. Safe to call from a
|                    browser; spam protection lives in the handler, exactly as
|                    it does for the WordPress endpoint.
|   safari.api    — server-to-server from WordPress, authenticated with the
|                    shared API key.
|   auth:sanctum  — API operators holding a bearer token.
|
*/

Route::prefix('v1')->name('api.v1.')->group(function (): void {

    /*
    |----------------------------------------------------------------------
    | Public
    |----------------------------------------------------------------------
    */

    Route::get('health', HealthController::class)->name('health');

    // Lead intake. The response envelope matches safari/v1/leads exactly, so
    // the theme's lead form works against either endpoint unchanged.
    Route::post('leads', [LeadController::class, 'store'])
        ->middleware('throttle:safari-leads')
        ->name('leads.store');

    // Content projection of the WordPress CMS.
    Route::get('search', [ContentController::class, 'search'])
        ->middleware('throttle:safari-read')
        ->name('search');

    Route::get('content/{type}', [ContentController::class, 'index'])
        ->middleware('throttle:safari-read')
        ->name('content.index');

    Route::get('content/{type}/{id}', [ContentController::class, 'show'])
        ->middleware('throttle:safari-read')
        ->name('content.show');

    /*
    |----------------------------------------------------------------------
    | Authenticated operators
    |----------------------------------------------------------------------
    */

    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:safari-login')
            ->name('login');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('me', [AuthController::class, 'me'])->name('me');
            Route::delete('login', [AuthController::class, 'logout'])->name('logout');
        });
    });

    Route::middleware(['auth:sanctum', 'operator:lead.view'])->group(function (): void {
        Route::get('leads', [LeadManagementController::class, 'index'])->name('leads.index');
        Route::get('leads/stats', [LeadManagementController::class, 'stats'])->name('leads.stats');
        Route::get('leads/{lead}', [LeadManagementController::class, 'show'])->name('leads.show');
        Route::get('leads/{lead}/notes', [LeadManagementController::class, 'notes'])->name('leads.notes.index');

        Route::middleware('operator:lead.manage')->group(function (): void {
            Route::patch('leads/{lead}/status', [LeadManagementController::class, 'updateStatus'])
                ->name('leads.status');
            Route::post('leads/{lead}/notes', [LeadNoteController::class, 'store'])
                ->name('leads.notes.store');
        });
    });
});
