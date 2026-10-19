<?php

use App\Http\Controllers\Api\V1\ClientApi;
use App\Http\Controllers\Api\V1\ExpenseApi;
use App\Http\Controllers\Api\V1\InvoiceApi;
use App\Http\Controllers\Api\V1\LeadApi;
use App\Http\Controllers\Api\V1\PaymentApi;
use App\Http\Controllers\Api\V1\ProductApi;
use App\Http\Controllers\Api\V1\ProjectApi;
use App\Http\Controllers\Api\V1\ProposalApi;
use App\Http\Controllers\Api\V1\TaskApi;
use Illuminate\Support\Facades\Route;

// Namespaced `api.*` so they never shadow the web routes' names
// (an un-prefixed apiResource would steal names like clients.show).
// api.json (global) forces JSON even without an Accept header; api.active blocks
// deactivated users' tokens; throttle caps integrations at 120 req/min.
Route::middleware(['installed', 'auth:sanctum', 'api.active', 'license', 'module', 'throttle:120,1'])->prefix('v1')->name('api.')->group(function () {
    Route::get('ping', fn () => ['ok' => true, 'version' => app_version()])->name('ping');

    // Read-only surface: any valid token (full or read-only scope).
    // Each resource is additionally gated by the owner's module permission.
    Route::apiResource('clients', ClientApi::class)->only(['index', 'show'])->middleware('perm:sales');
    Route::apiResource('leads', LeadApi::class)->only(['index', 'show'])->middleware('perm:sales');
    Route::apiResource('projects', ProjectApi::class)->only(['index', 'show'])->middleware('perm:work');
    Route::apiResource('tasks', TaskApi::class)->only(['index', 'show'])->middleware('perm:work');
    Route::apiResource('invoices', InvoiceApi::class)->only(['index', 'show'])->middleware('perm:billing');
    Route::apiResource('payments', PaymentApi::class)->only(['index', 'show'])->middleware('perm:billing');
    Route::apiResource('expenses', ExpenseApi::class)->only(['index', 'show'])->middleware('perm:billing');
    Route::apiResource('proposals', ProposalApi::class)->only(['index', 'show'])->middleware('perm:sales');
    Route::apiResource('products', ProductApi::class)->only(['index', 'show'])->middleware('perm:sales');

    // Mutating surface: requires a token with the `write` ability.
    Route::middleware('token.write')->group(function () {
        Route::apiResource('clients', ClientApi::class)->only(['store', 'update', 'destroy'])->middleware('perm:sales');
        Route::apiResource('leads', LeadApi::class)->only(['store', 'update', 'destroy'])->middleware('perm:sales');
        Route::post('leads/{lead}/move', [LeadApi::class, 'move'])->name('leads.move')->middleware('perm:sales');
        Route::apiResource('projects', ProjectApi::class)->only(['store', 'update', 'destroy'])->middleware('perm:work');
        Route::apiResource('tasks', TaskApi::class)->only(['store', 'update', 'destroy'])->middleware('perm:work');
        Route::post('tasks/{task}/move', [TaskApi::class, 'move'])->name('tasks.move')->middleware('perm:work');
        Route::apiResource('invoices', InvoiceApi::class)->only(['store'])->middleware('perm:billing');
        Route::post('invoices/{invoice}/sent', [InvoiceApi::class, 'markSent'])->name('invoices.sent')->middleware('perm:billing');
        Route::apiResource('payments', PaymentApi::class)->only(['store', 'destroy'])->middleware('perm:billing');
        Route::apiResource('expenses', ExpenseApi::class)->only(['store', 'update', 'destroy'])->middleware('perm:billing');
        Route::apiResource('proposals', ProposalApi::class)->only(['store', 'update', 'destroy'])->middleware('perm:sales');
        Route::post('proposals/{proposal}/send', [ProposalApi::class, 'send'])->name('proposals.send')->middleware('perm:sales');
        Route::post('proposals/{proposal}/convert', [ProposalApi::class, 'convert'])->name('proposals.convert')->middleware('perm:sales');
        Route::apiResource('products', ProductApi::class)->only(['store', 'update', 'destroy'])->middleware('perm:sales');
    });
});
