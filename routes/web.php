<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\ClassificationController;
use App\Http\Controllers\Admin\CountryController;
use App\Http\Controllers\Admin\CustomsCodeController;
use App\Http\Controllers\Admin\LawDocumentController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\SettingsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::get('/', function () {
    return view('landing');
})->name('home');

Route::get('/pricing', function () {
    return redirect('/#pricing');
})->name('pricing');

Route::get('/features', function () {
    return redirect('/#features');
})->name('features');

// Authentication routes
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.post');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Registration routes
Route::get('/register', [RegisterController::class, 'showChoice'])->name('register.choice');
Route::get('/register/organization', [RegisterController::class, 'showOrganizationForm'])->name('register.organization');
Route::post('/register/organization', [RegisterController::class, 'registerOrganization'])->name('register.organization.post');
Route::get('/register/individual', [RegisterController::class, 'showIndividualForm'])->name('register.individual');
Route::post('/register/individual', [RegisterController::class, 'registerIndividual'])->name('register.individual.post');

// Onboarding routes (auth required, no onboarding check)
Route::middleware(['auth'])->group(function () {
    Route::get('/onboarding', [OnboardingController::class, 'index'])->name('onboarding.index');
    Route::post('/onboarding/complete', [OnboardingController::class, 'complete'])->name('onboarding.complete');
    Route::get('/onboarding/skip', [OnboardingController::class, 'skip'])->name('onboarding.skip');
});

// Protected routes (auth + onboarded + tenant context)
Route::middleware(['auth', 'onboarded', 'tenant'])->group(function () {
    
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // Invoice routes (with subscription check)
    Route::middleware(['subscription'])->group(function () {
        Route::resource('invoices', InvoiceController::class)->only(['index', 'show']);
        Route::get('invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::get('invoices/review', [InvoiceController::class, 'review'])->name('invoices.review');
        Route::post('invoices/confirm', [InvoiceController::class, 'confirm'])->name('invoices.confirm');
        Route::get('invoices/assign-codes', [InvoiceController::class, 'assignCodes'])->name('invoices.assign_codes');
        Route::post('invoices/finalize', [InvoiceController::class, 'finalize'])->name('invoices.finalize');
    });
    
    // Declaration forms (placeholder routes)
    Route::get('/declaration-forms', function () {
        return view('declaration-forms.index');
    })->name('declaration-forms.index');
    
    // Subscription management
    Route::get('/subscription', [SubscriptionController::class, 'index'])->name('subscription.index');
    Route::post('/subscription/upgrade', [SubscriptionController::class, 'upgrade'])->name('subscription.upgrade');
    Route::get('/subscription/expired', [SubscriptionController::class, 'expired'])->name('subscription.expired');
    Route::get('/subscription/usage', [SubscriptionController::class, 'checkUsage'])->name('subscription.usage');
    
    // Classification routes (AI-powered item classification)
    Route::get('/classify', [ClassificationController::class, 'index'])->name('classification.index');
    Route::post('/classify', [ClassificationController::class, 'classify'])->name('classification.classify');
    Route::post('/classify/bulk', [ClassificationController::class, 'classifyBulk'])->name('classification.bulk');
    
    // Admin routes (admin role only)
    Route::middleware(['admin'])->prefix('admin')->name('admin.')->group(function () {
        // User Management
        Route::resource('users', UserManagementController::class);
        
        // AI Settings
        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::post('settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::post('settings/test-claude', [SettingsController::class, 'testClaudeConnection'])->name('settings.test-claude');
        
        // Countries & Customs Codes
        Route::resource('countries', CountryController::class);
        Route::resource('customs-codes', CustomsCodeController::class);
        
        // Law Documents
        Route::resource('law-documents', LawDocumentController::class)->except(['edit', 'update']);
        Route::post('law-documents/{law_document}/process', [LawDocumentController::class, 'process'])->name('law-documents.process');
        Route::post('law-documents/{law_document}/reprocess', [LawDocumentController::class, 'reprocess'])->name('law-documents.reprocess');
        Route::get('law-documents/{law_document}/download', [LawDocumentController::class, 'download'])->name('law-documents.download');
    });
});
