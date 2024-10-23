<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\DeclarationFormController;
use App\Http\Controllers\CustomsCodeController;

Route::get('/dashboard', function () {
    // Load necessary data for the dashboard
    $pendingInvoices = // ... load pending invoices
    $processedInvoices = // ... load processed invoices
    $recentForms = // ... load recent forms

    return view('dashboard', compact('pendingInvoices', 'processedInvoices', 'recentForms'));
})->middleware(['auth'])->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
    Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::get('/invoices/review', [InvoiceController::class, 'review'])->name('invoices.review');
    Route::post('/invoices/confirm', [InvoiceController::class, 'confirm'])->name('invoices.confirm');
    Route::get('/invoices/assign-codes', [InvoiceController::class, 'assignCodes'])->name('invoices.assign_codes');
    Route::post('/invoices/finalize', [InvoiceController::class, 'finalize'])->name('invoices.finalize');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::resource('declaration-forms', DeclarationFormController::class);
    Route::resource('customs-codes', CustomsCodeController::class);
});

require __DIR__.'/auth.php';
