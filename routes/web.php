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
use App\Http\Controllers\Admin\TariffDatabaseController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\TradeContactController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\ShippingDocumentController;
use App\Http\Controllers\Agent\AgentDashboardController;
use App\Http\Controllers\Agent\AgentDeclarationController;
use App\Http\Controllers\Agent\AgentClientController;
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

// Public classification API for landing page demo
Route::post('/api/public-classify', [ClassificationController::class, 'publicClassify'])->name('api.public-classify');

// Waitlist signup
Route::post('/waitlist/signup', [App\Http\Controllers\WaitlistController::class, 'signup'])->name('waitlist.signup');
Route::get('/waitlist/thank-you/{signup}', [App\Http\Controllers\WaitlistController::class, 'thankYou'])->name('waitlist.thank-you');
Route::post('/waitlist/{signup}/feedback', [App\Http\Controllers\WaitlistController::class, 'storeFeedback'])->name('waitlist.feedback');

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
        
        // Background classification status routes
        Route::get('invoices/{invoice}/classification-status', [InvoiceController::class, 'classificationStatus'])->name('invoices.classification_status');
        Route::post('invoices/{invoice}/start-classification', [InvoiceController::class, 'startClassification'])->name('invoices.start_classification');
        Route::get('invoices/{invoice}/classification-progress', [InvoiceController::class, 'classificationProgress'])->name('invoices.classification_progress');
        Route::get('invoices/{invoice}/assign-codes-results', [InvoiceController::class, 'assignCodesResults'])->name('invoices.assign_codes_results');
        Route::post('invoices/{invoice}/retry-classification', [InvoiceController::class, 'retryClassification'])->name('invoices.retry_classification');
        
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

    // Web Portal Submission
    Route::prefix('declaration-forms/{declaration}/web-submit')->name('web-submission.')->group(function () {
        Route::get('/', [App\Http\Controllers\WebSubmissionController::class, 'index'])->name('index');
        Route::get('/preview/{target}', [App\Http\Controllers\WebSubmissionController::class, 'preview'])->name('preview');
        Route::post('/submit/{target}', [App\Http\Controllers\WebSubmissionController::class, 'submit'])->name('submit');
        Route::get('/result/{submission}', [App\Http\Controllers\WebSubmissionController::class, 'result'])->name('result');
        Route::get('/history', [App\Http\Controllers\WebSubmissionController::class, 'history'])->name('history');
    });
    Route::post('web-submission/{submission}/retry', [App\Http\Controllers\WebSubmissionController::class, 'retry'])->name('web-submission.retry');
    Route::get('api/web-submission/targets', [App\Http\Controllers\WebSubmissionController::class, 'getTargetsForCountry'])->name('api.web-submission.targets');

    // FTP Submission (CAPS T12)
    Route::prefix('declaration-forms/{declaration}/ftp-submit')->name('ftp-submission.')->group(function () {
        Route::get('/', [App\Http\Controllers\FtpSubmissionController::class, 'index'])->name('index');
        Route::get('/preview', [App\Http\Controllers\FtpSubmissionController::class, 'preview'])->name('preview');
        Route::post('/submit', [App\Http\Controllers\FtpSubmissionController::class, 'submit'])->name('submit');
        Route::get('/download', [App\Http\Controllers\FtpSubmissionController::class, 'download'])->name('download');
        Route::get('/result/{submission}', [App\Http\Controllers\FtpSubmissionController::class, 'result'])->name('result');
        Route::get('/history', [App\Http\Controllers\FtpSubmissionController::class, 'history'])->name('history');
    });
    Route::post('ftp-submission/{submission}/retry', [App\Http\Controllers\FtpSubmissionController::class, 'retry'])->name('ftp-submission.retry');

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
        // Help Center
        Route::get('/help-center', [App\Http\Controllers\HelpCenterController::class, 'index'])->name('help-center');
        
        Route::get('/classification-rules', [App\Http\Controllers\ClassificationRuleController::class, 'index'])->name('classification-rules');
        Route::post('/classification-rules', [App\Http\Controllers\ClassificationRuleController::class, 'store'])->name('classification-rules.store');
        Route::put('/classification-rules/{rule}', [App\Http\Controllers\ClassificationRuleController::class, 'update'])->name('classification-rules.update');
        Route::delete('/classification-rules/{rule}', [App\Http\Controllers\ClassificationRuleController::class, 'destroy'])->name('classification-rules.destroy');
        Route::post('/classification-rules/{rule}/toggle', [App\Http\Controllers\ClassificationRuleController::class, 'toggle'])->name('classification-rules.toggle');
        Route::get('/classification-rules/search-codes', [App\Http\Controllers\ClassificationRuleController::class, 'searchCodes'])->name('classification-rules.search-codes');
        Route::post('/classification-rules/test', [App\Http\Controllers\ClassificationRuleController::class, 'testRule'])->name('classification-rules.test');
        
        // Submission Credentials (FTP and Web portal)
        Route::get('/submission-credentials', [App\Http\Controllers\OrganizationCredentialController::class, 'index'])->name('submission-credentials');
        Route::post('/submission-credentials/test-connection', [App\Http\Controllers\OrganizationCredentialController::class, 'testUnsavedConnection'])->name('submission-credentials.test-connection');
        Route::post('/submission-credentials', [App\Http\Controllers\OrganizationCredentialController::class, 'store'])->name('submission-credentials.store');
        Route::put('/submission-credentials/{credential}', [App\Http\Controllers\OrganizationCredentialController::class, 'update'])->name('submission-credentials.update');
        Route::delete('/submission-credentials/{credential}', [App\Http\Controllers\OrganizationCredentialController::class, 'destroy'])->name('submission-credentials.destroy');
        Route::post('/submission-credentials/{credential}/test', [App\Http\Controllers\OrganizationCredentialController::class, 'test'])->name('submission-credentials.test');
        Route::get('/submission-credentials/targets', [App\Http\Controllers\OrganizationCredentialController::class, 'getTargetsForCountry'])->name('submission-credentials.targets');
    });
    
    // API-style routes for AJAX requests
    Route::get('/api/customs-codes/search', [InvoiceController::class, 'searchCustomsCodes'])->name('api.customs-codes.search');
    Route::get('/api/classification-memory/search', [InvoiceController::class, 'searchClassificationMemory'])->name('api.classification-memory.search');
    
    // Admin routes (admin role only)
    Route::middleware(['admin'])->prefix('admin')->name('admin.')->group(function () {
        // User Management
        Route::resource('users', UserManagementController::class);
        Route::post('users/{user}/assign-client', [UserManagementController::class, 'assignClient'])->name('users.assign-client');
        Route::delete('users/{user}/clients/{organization}', [UserManagementController::class, 'removeClient'])->name('users.remove-client');
        Route::patch('users/{user}/clients/{organization}/toggle', [UserManagementController::class, 'toggleClientStatus'])->name('users.toggle-client');
        
        // Waitlist Signups
        Route::get('waitlist', [App\Http\Controllers\Admin\WaitlistController::class, 'index'])->name('waitlist.index');
        Route::get('waitlist/export', [App\Http\Controllers\Admin\WaitlistController::class, 'export'])->name('waitlist.export');
        Route::get('waitlist/{signup}', [App\Http\Controllers\Admin\WaitlistController::class, 'show'])->name('waitlist.show');
        Route::delete('waitlist/{signup}', [App\Http\Controllers\Admin\WaitlistController::class, 'destroy'])->name('waitlist.destroy');
        
        // Classification Logs
        Route::get('classification-logs', [App\Http\Controllers\Admin\ClassificationLogController::class, 'index'])->name('classification-logs.index');
        Route::get('classification-logs/export', [App\Http\Controllers\Admin\ClassificationLogController::class, 'export'])->name('classification-logs.export');
        
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
        
        // Tariff Database Viewer
        Route::prefix('tariff-database')->name('tariff-database.')->group(function () {
            Route::get('/', [TariffDatabaseController::class, 'index'])->name('index');
            Route::get('/codes', [TariffDatabaseController::class, 'codes'])->name('codes');
            Route::get('/notes', [TariffDatabaseController::class, 'notes'])->name('notes');
            Route::get('/structure', [TariffDatabaseController::class, 'structure'])->name('structure');
            Route::get('/exclusions', [TariffDatabaseController::class, 'exclusions'])->name('exclusions');
        });

        // Site Analytics
        Route::prefix('analytics')->name('analytics.')->group(function () {
            Route::get('/', [AnalyticsController::class, 'index'])->name('index');
            Route::get('/export', [AnalyticsController::class, 'export'])->name('export');
        });

        // Web Form Targets (Portal Automation)
        Route::prefix('web-form-targets')->name('web-form-targets.')->group(function () {
            Route::get('/', [App\Http\Controllers\Admin\WebFormTargetController::class, 'index'])->name('index');
            Route::get('/create', [App\Http\Controllers\Admin\WebFormTargetController::class, 'create'])->name('create');
            Route::post('/', [App\Http\Controllers\Admin\WebFormTargetController::class, 'store'])->name('store');
            Route::get('/{webFormTarget}', [App\Http\Controllers\Admin\WebFormTargetController::class, 'show'])->name('show');
            Route::get('/{webFormTarget}/edit', [App\Http\Controllers\Admin\WebFormTargetController::class, 'edit'])->name('edit');
            Route::put('/{webFormTarget}', [App\Http\Controllers\Admin\WebFormTargetController::class, 'update'])->name('update');
            Route::delete('/{webFormTarget}', [App\Http\Controllers\Admin\WebFormTargetController::class, 'destroy'])->name('destroy');
            Route::post('/{webFormTarget}/test', [App\Http\Controllers\Admin\WebFormTargetController::class, 'testConnection'])->name('test');
            Route::patch('/{webFormTarget}/toggle', [App\Http\Controllers\Admin\WebFormTargetController::class, 'toggleActive'])->name('toggle-active');
            Route::get('/{webFormTarget}/submissions', [App\Http\Controllers\Admin\WebFormTargetController::class, 'submissions'])->name('submissions');
            Route::get('/{webFormTarget}/submissions/{submission}', [App\Http\Controllers\Admin\WebFormTargetController::class, 'showSubmission'])->name('submissions.show');

            // Pages
            Route::get('/{webFormTarget}/pages/create', [App\Http\Controllers\Admin\WebFormTargetController::class, 'createPage'])->name('pages.create');
            Route::post('/{webFormTarget}/pages', [App\Http\Controllers\Admin\WebFormTargetController::class, 'storePage'])->name('pages.store');
            Route::get('/{webFormTarget}/pages/{page}', [App\Http\Controllers\Admin\WebFormTargetController::class, 'showPage'])->name('pages.show');
            Route::get('/{webFormTarget}/pages/{page}/edit', [App\Http\Controllers\Admin\WebFormTargetController::class, 'editPage'])->name('pages.edit');
            Route::put('/{webFormTarget}/pages/{page}', [App\Http\Controllers\Admin\WebFormTargetController::class, 'updatePage'])->name('pages.update');
            Route::delete('/{webFormTarget}/pages/{page}', [App\Http\Controllers\Admin\WebFormTargetController::class, 'destroyPage'])->name('pages.destroy');

            // Field Mappings
            Route::get('/{webFormTarget}/pages/{page}/mappings/create', [App\Http\Controllers\Admin\WebFormTargetController::class, 'createMapping'])->name('mappings.create');
            Route::post('/{webFormTarget}/pages/{page}/mappings', [App\Http\Controllers\Admin\WebFormTargetController::class, 'storeMapping'])->name('mappings.store');
            Route::get('/{webFormTarget}/pages/{page}/mappings/{mapping}/edit', [App\Http\Controllers\Admin\WebFormTargetController::class, 'editMapping'])->name('mappings.edit');
            Route::put('/{webFormTarget}/pages/{page}/mappings/{mapping}', [App\Http\Controllers\Admin\WebFormTargetController::class, 'updateMapping'])->name('mappings.update');
            Route::delete('/{webFormTarget}/pages/{page}/mappings/{mapping}', [App\Http\Controllers\Admin\WebFormTargetController::class, 'destroyMapping'])->name('mappings.destroy');
        });

        // FTP Submission Testing (Admin)
        Route::prefix('ftp-test')->name('ftp-test.')->group(function () {
            Route::get('/', [App\Http\Controllers\Admin\FtpTestController::class, 'index'])->name('index');
            Route::post('/test-connection', [App\Http\Controllers\Admin\FtpTestController::class, 'testConnection'])->name('test-connection');
            Route::post('/preview-t12', [App\Http\Controllers\Admin\FtpTestController::class, 'previewT12'])->name('preview-t12');
            Route::get('/download/{declaration}', [App\Http\Controllers\Admin\FtpTestController::class, 'downloadT12'])->name('download');
            Route::post('/submit', [App\Http\Controllers\Admin\FtpTestController::class, 'submit'])->name('submit');
            Route::get('/credentials', [App\Http\Controllers\Admin\FtpTestController::class, 'getCredentials'])->name('credentials');
        });
    });

    // Agent routes (agent role only)
    Route::middleware(['agent'])->prefix('agent')->name('agent.')->group(function () {
        // Dashboard
        Route::get('/', [AgentDashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard', [AgentDashboardController::class, 'index'])->name('dashboard.index');
        
        // Client context switching
        Route::get('/switch-client/{organization}', [AgentDashboardController::class, 'switchClient'])->name('switch-client');
        Route::get('/clear-client', [AgentDashboardController::class, 'clearClientContext'])->name('clear-client');
        
        // Clients list
        Route::get('/clients', [AgentClientController::class, 'index'])->name('clients.index');
        Route::get('/clients/{organization}', [AgentClientController::class, 'show'])->name('clients.show');
        
        // Declarations
        Route::get('/declarations', [AgentDeclarationController::class, 'index'])->name('declarations.index');
        Route::get('/declarations/{declaration}', [AgentDeclarationController::class, 'show'])->name('declarations.show');
        Route::post('/declarations/{declaration}/mark-ready', [AgentDeclarationController::class, 'markReady'])->name('declarations.mark-ready');
        Route::get('/declarations/{declaration}/submit', [AgentDeclarationController::class, 'showSubmitForm'])->name('declarations.submit-form');
        Route::post('/declarations/{declaration}/submit', [AgentDeclarationController::class, 'submit'])->name('declarations.submit');
        
        // Attachments
        Route::post('/declarations/{declaration}/attachments', [AgentDeclarationController::class, 'uploadAttachment'])->name('declarations.upload-attachment');
        Route::delete('/attachments/{attachment}', [AgentDeclarationController::class, 'deleteAttachment'])->name('attachments.delete');
        Route::get('/attachments/{attachment}/download', [AgentDeclarationController::class, 'downloadAttachment'])->name('attachments.download');
    });
});

// =============================================================================
// Test Routes - Simulated External Form (for Playwright automation testing)
// =============================================================================
Route::prefix('test')->name('test.')->group(function () {
    Route::get('/external-form', [App\Http\Controllers\TestExternalFormController::class, 'index'])->name('external-form');
    Route::post('/external-form/login', [App\Http\Controllers\TestExternalFormController::class, 'login'])->name('external-form.login');
    Route::post('/external-form/submit', [App\Http\Controllers\TestExternalFormController::class, 'submit'])->name('external-form.submit');
    Route::get('/external-form/logout', [App\Http\Controllers\TestExternalFormController::class, 'logout'])->name('external-form.logout');
    Route::get('/external-form/check', [App\Http\Controllers\TestExternalFormController::class, 'checkSubmission'])->name('external-form.check');
});
