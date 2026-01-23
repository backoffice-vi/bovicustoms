<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\ClassificationController;
use App\Http\Controllers\LegacyClearancesController;
use App\Http\Controllers\DeclarationFormController;
use App\Http\Controllers\Admin\CountryController;
use App\Http\Controllers\Admin\CustomsCodeController;
use App\Http\Controllers\Admin\LawDocumentController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\ExclusionRuleController;
use App\Http\Controllers\Admin\ExemptionController;
use App\Http\Controllers\Admin\ProhibitedRestrictedController;
use App\Http\Controllers\Admin\ClassificationTesterController;
use App\Http\Controllers\Admin\CountryDocumentController;
use App\Http\Controllers\Admin\CountryLevyController;
use App\Http\Controllers\TradeContactController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\ShippingDocumentController;
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
        // Specific invoice routes MUST come before the resource route
        Route::get('invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
        Route::get('invoices/review', [InvoiceController::class, 'review'])->name('invoices.review');
        Route::get('invoices/assign-codes', [InvoiceController::class, 'assignCodes'])->name('invoices.assign_codes');
        Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::post('invoices/confirm', [InvoiceController::class, 'confirm'])->name('invoices.confirm');
        Route::post('invoices/finalize', [InvoiceController::class, 'finalize'])->name('invoices.finalize');
        // Resource route for index and show (must be last to avoid catching /create, /review, etc)
        Route::resource('invoices', InvoiceController::class)->only(['index', 'show']);
    });
    
    // Declaration forms
    Route::resource('declaration-forms', DeclarationFormController::class)->only(['index', 'show', 'store']);
    
    // Declaration form filling workflow
    Route::get('declaration-forms/{declarationForm}/select-templates', [DeclarationFormController::class, 'selectTemplates'])->name('declaration-forms.select-templates');
    Route::post('declaration-forms/{declarationForm}/analyze-template', [DeclarationFormController::class, 'analyzeTemplate'])->name('declaration-forms.analyze-template');
    Route::get('declaration-forms/{declarationForm}/fill/{filledForm}', [DeclarationFormController::class, 'fillForm'])->name('declaration-forms.fill');
    Route::post('declaration-forms/{declarationForm}/fill/{filledForm}', [DeclarationFormController::class, 'processFill'])->name('declaration-forms.process-fill');
    Route::get('declaration-forms/{declarationForm}/preview/{filledForm}', [DeclarationFormController::class, 'preview'])->name('declaration-forms.preview');
    Route::get('declaration-forms/{declarationForm}/auto-map/{filledForm}', [DeclarationFormController::class, 'getAutoMappedData'])->name('declaration-forms.auto-map');

    // Trade Contacts (reusable shipper, consignee, broker data)
    Route::resource('trade-contacts', TradeContactController::class);
    Route::patch('trade-contacts/{tradeContact}/toggle-default', [TradeContactController::class, 'toggleDefault'])->name('trade-contacts.toggle-default');
    Route::get('api/trade-contacts/by-type', [TradeContactController::class, 'byType'])->name('api.trade-contacts.by-type');
    Route::get('api/trade-contacts/{tradeContact}/form-data', [TradeContactController::class, 'getFormData'])->name('api.trade-contacts.form-data');

    // Shipments (groups invoices + shipping documents)
    Route::resource('shipments', ShipmentController::class);
    Route::post('shipments/{shipment}/add-invoices', [ShipmentController::class, 'addInvoices'])->name('shipments.add-invoices');
    Route::delete('shipments/{shipment}/invoices/{invoice}', [ShipmentController::class, 'removeInvoice'])->name('shipments.remove-invoice');
    Route::post('shipments/{shipment}/generate-declaration', [ShipmentController::class, 'generateDeclaration'])->name('shipments.generate-declaration');
    Route::get('api/shipments/available-invoices', [ShipmentController::class, 'getAvailableInvoices'])->name('api.shipments.available-invoices');
    Route::post('api/shipments/{shipment}/recalculate', [ShipmentController::class, 'recalculate'])->name('api.shipments.recalculate');

    // Shipping Documents (within shipments)
    Route::post('shipments/{shipment}/documents', [ShippingDocumentController::class, 'store'])->name('shipping-documents.store');
    Route::post('shipping-documents/{shippingDocument}/extract', [ShippingDocumentController::class, 'extract'])->name('shipping-documents.extract');
    Route::patch('shipping-documents/{shippingDocument}', [ShippingDocumentController::class, 'update'])->name('shipping-documents.update');
    Route::post('shipping-documents/{shippingDocument}/verify', [ShippingDocumentController::class, 'verify'])->name('shipping-documents.verify');
    Route::delete('shipping-documents/{shippingDocument}', [ShippingDocumentController::class, 'destroy'])->name('shipping-documents.destroy');
    Route::get('shipping-documents/{shippingDocument}/download', [ShippingDocumentController::class, 'download'])->name('shipping-documents.download');
    Route::post('shipping-documents/{shippingDocument}/create-contact', [ShippingDocumentController::class, 'createContact'])->name('shipping-documents.create-contact');
    Route::post('shipments/{shipment}/link-contact', [ShippingDocumentController::class, 'linkContact'])->name('shipments.link-contact');

    // Legacy Clearances (import historical clearance data)
    Route::middleware(['subscription'])->group(function () {
        Route::get('/legacy-clearances', [LegacyClearancesController::class, 'index'])->name('legacy-clearances.index');
        Route::post('/legacy-clearances/upload', [LegacyClearancesController::class, 'uploadClearance'])->name('legacy-clearances.upload');
        Route::get('/legacy-clearances/shipments/{shipment}', [LegacyClearancesController::class, 'showShipment'])->name('legacy-clearances.shipments.show');
        Route::get('/legacy-clearances/invoices/{invoice}', [LegacyClearancesController::class, 'showInvoice'])->name('legacy-clearances.invoices.show');
        Route::get('/legacy-clearances/declarations/{declarationForm}', [LegacyClearancesController::class, 'showDeclaration'])->name('legacy-clearances.declarations.show');
    });
    
    // Subscription management
    Route::get('/subscription', [SubscriptionController::class, 'index'])->name('subscription.index');
    Route::post('/subscription/upgrade', [SubscriptionController::class, 'upgrade'])->name('subscription.upgrade');
    Route::get('/subscription/expired', [SubscriptionController::class, 'expired'])->name('subscription.expired');
    Route::get('/subscription/usage', [SubscriptionController::class, 'checkUsage'])->name('subscription.usage');
    
    // Classification routes (AI-powered item classification)
    Route::get('/classify', [ClassificationController::class, 'index'])->name('classification.index');
    Route::post('/classify', [ClassificationController::class, 'classify'])->name('classification.classify');
    Route::post('/classify/bulk', [ClassificationController::class, 'classifyBulk'])->name('classification.bulk');
    
    // Classification Rules Settings
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/classification-rules', [App\Http\Controllers\ClassificationRuleController::class, 'index'])->name('classification-rules');
        Route::post('/classification-rules', [App\Http\Controllers\ClassificationRuleController::class, 'store'])->name('classification-rules.store');
        Route::put('/classification-rules/{rule}', [App\Http\Controllers\ClassificationRuleController::class, 'update'])->name('classification-rules.update');
        Route::delete('/classification-rules/{rule}', [App\Http\Controllers\ClassificationRuleController::class, 'destroy'])->name('classification-rules.destroy');
        Route::post('/classification-rules/{rule}/toggle', [App\Http\Controllers\ClassificationRuleController::class, 'toggle'])->name('classification-rules.toggle');
        Route::get('/classification-rules/search-codes', [App\Http\Controllers\ClassificationRuleController::class, 'searchCodes'])->name('classification-rules.search-codes');
        Route::post('/classification-rules/test', [App\Http\Controllers\ClassificationRuleController::class, 'testRule'])->name('classification-rules.test');
    });
    
    // API-style routes for AJAX requests
    Route::get('/api/customs-codes/search', [InvoiceController::class, 'searchCustomsCodes'])->name('api.customs-codes.search');
    
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
        Route::get('law-documents/{law_document}/status', [LawDocumentController::class, 'status'])->name('law-documents.status');
        
        // Exclusion Rules
        Route::resource('exclusion-rules', ExclusionRuleController::class)->parameters(['exclusion-rules' => 'exclusionRule']);
        Route::post('exclusion-rules/parse-from-notes', [ExclusionRuleController::class, 'parseFromNotes'])->name('exclusion-rules.parse');
        
        // Exemptions
        Route::resource('exemptions', ExemptionController::class);
        
        // Prohibited & Restricted Goods
        Route::get('prohibited-restricted', [ProhibitedRestrictedController::class, 'index'])->name('prohibited-restricted.index');
        Route::get('prohibited-restricted/create-prohibited', [ProhibitedRestrictedController::class, 'createProhibited'])->name('prohibited-restricted.create-prohibited');
        Route::post('prohibited-restricted/prohibited', [ProhibitedRestrictedController::class, 'storeProhibited'])->name('prohibited-restricted.store-prohibited');
        Route::get('prohibited-restricted/create-restricted', [ProhibitedRestrictedController::class, 'createRestricted'])->name('prohibited-restricted.create-restricted');
        Route::post('prohibited-restricted/restricted', [ProhibitedRestrictedController::class, 'storeRestricted'])->name('prohibited-restricted.store-restricted');
        Route::delete('prohibited-restricted/prohibited/{prohibited}', [ProhibitedRestrictedController::class, 'destroyProhibited'])->name('prohibited-restricted.destroy-prohibited');
        Route::delete('prohibited-restricted/restricted/{restricted}', [ProhibitedRestrictedController::class, 'destroyRestricted'])->name('prohibited-restricted.destroy-restricted');
        
        // Classification Tester
        Route::get('classification-tester', [ClassificationTesterController::class, 'index'])->name('classification-tester.index');
        Route::post('classification-tester', [ClassificationTesterController::class, 'test'])->name('classification-tester.test');
        
        // Country Documents (Form Templates & Support Documents)
        Route::get('country-documents', [CountryDocumentController::class, 'index'])->name('country-documents.index');
        
        // Form Templates
        Route::get('country-documents/templates/create', [CountryDocumentController::class, 'createTemplate'])->name('country-documents.templates.create');
        Route::post('country-documents/templates', [CountryDocumentController::class, 'storeTemplate'])->name('country-documents.templates.store');
        Route::get('country-documents/templates/{template}', [CountryDocumentController::class, 'showTemplate'])->name('country-documents.templates.show');
        Route::get('country-documents/templates/{template}/download', [CountryDocumentController::class, 'downloadTemplate'])->name('country-documents.templates.download');
        Route::patch('country-documents/templates/{template}/toggle', [CountryDocumentController::class, 'toggleTemplate'])->name('country-documents.templates.toggle');
        Route::delete('country-documents/templates/{template}', [CountryDocumentController::class, 'destroyTemplate'])->name('country-documents.templates.destroy');
        
        // Support Documents
        Route::get('country-documents/support/create', [CountryDocumentController::class, 'createSupport'])->name('country-documents.support.create');
        Route::post('country-documents/support', [CountryDocumentController::class, 'storeSupport'])->name('country-documents.support.store');
        Route::get('country-documents/support/{document}', [CountryDocumentController::class, 'showSupport'])->name('country-documents.support.show');
        Route::get('country-documents/support/{document}/download', [CountryDocumentController::class, 'downloadSupport'])->name('country-documents.support.download');
        Route::post('country-documents/support/{document}/extract', [CountryDocumentController::class, 'extractText'])->name('country-documents.support.extract');
        Route::patch('country-documents/support/{document}/toggle', [CountryDocumentController::class, 'toggleSupport'])->name('country-documents.support.toggle');
        Route::delete('country-documents/support/{document}', [CountryDocumentController::class, 'destroySupport'])->name('country-documents.support.destroy');
        
        // Country Levies (Wharfage, etc.)
        Route::resource('country-levies', CountryLevyController::class);
    });
});
