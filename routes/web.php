<?php

/**
 * File: Browser routes for the progressively enhanced service-desk interface.
 * Symbols: dashboard, ticket creation, ticket comments, and lifecycle transitions.
 * State: route parameters only; exact declarations are indexed in docs/code-index.md.
 */

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::middleware('demo.actor')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::post('/tickets/{ticket}/comments', [TicketController::class, 'comment'])->name('tickets.comments.store');
    Route::post('/tickets/{ticket}/transition', [TicketController::class, 'transition'])->name('tickets.transition');
});
