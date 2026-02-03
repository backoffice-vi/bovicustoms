@extends('layouts.app')

@section('title', 'Help Center')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 mb-1">
                <i class="fas fa-question-circle text-primary me-2"></i>Help Center
            </h1>
            <p class="text-muted mb-0">
                Learn how to use BoVi Customs and understand the complete application workflow
            </p>
        </div>
    </div>

    <!-- Application Overview -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>What is BoVi Customs?</h5>
                </div>
                <div class="card-body">
                    <p class="mb-0">
                        BoVi Customs is an AI-powered customs automation platform that streamlines the process of 
                        creating, managing, and submitting import/export declarations. The system automatically 
                        classifies goods using HS codes, calculates duties and taxes, and can submit declarations 
                        directly to customs authorities.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Complete Workflow -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-success">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-route me-2"></i>Complete Application Workflow</h5>
                </div>
                <div class="card-body">
                    <div class="workflow-steps">
                        <!-- Step 1: Upload Invoice -->
                        <div class="workflow-step mb-4">
                            <div class="d-flex align-items-start">
                                <div class="step-number bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="min-width: 40px; height: 40px; font-weight: bold;">
                                    1
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="mb-2">
                                        <i class="fas fa-upload me-2 text-primary"></i>Upload Invoice
                                    </h5>
                                    <p class="text-muted mb-2">
                                        Start by uploading a commercial invoice (PDF, Excel, or CSV) through the 
                                        <strong>Invoices → Upload</strong> menu.
                                    </p>
                                    <ul class="small text-muted">
                                        <li>The system extracts invoice data using AI</li>
                                        <li>Line items are automatically parsed</li>
                                        <li>Select your destination country</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: AI Classification -->
                        <div class="workflow-step mb-4">
                            <div class="d-flex align-items-start">
                                <div class="step-number bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="min-width: 40px; height: 40px; font-weight: bold;">
                                    2
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="mb-2">
                                        <i class="fas fa-robot me-2 text-primary"></i>AI Classification
                                    </h5>
                                    <p class="text-muted mb-2">
                                        The AI automatically classifies each item with the correct HS code.
                                    </p>
                                    <ul class="small text-muted">
                                        <li>Click "Assign Codes" on your invoice</li>
                                        <li>AI analyzes item descriptions and assigns HS codes</li>
                                        <li>Review and adjust classifications if needed</li>
                                        <li>Use <strong>Classify → Classification Rules</strong> to customize classifications</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Step 3: Create Shipment -->
                        <div class="workflow-step mb-4">
                            <div class="d-flex align-items-start">
                                <div class="step-number bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="min-width: 40px; height: 40px; font-weight: bold;">
                                    3
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="mb-2">
                                        <i class="fas fa-ship me-2 text-primary"></i>Create Shipment
                                    </h5>
                                    <p class="text-muted mb-2">
                                        Group invoices together into a shipment through <strong>Declarations → Shipments</strong>.
                                    </p>
                                    <ul class="small text-muted">
                                        <li>Create a new shipment and select destination country</li>
                                        <li>Add one or more invoices to the shipment</li>
                                        <li>Upload shipping documents (Bill of Lading, packing list, etc.)</li>
                                        <li>Select or create trade contacts (shipper, consignee)</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Step 4: Generate Declaration -->
                        <div class="workflow-step mb-4">
                            <div class="d-flex align-items-start">
                                <div class="step-number bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="min-width: 40px; height: 40px; font-weight: bold;">
                                    4
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="mb-2">
                                        <i class="fas fa-file-alt me-2 text-primary"></i>Generate Declaration
                                    </h5>
                                    <p class="text-muted mb-2">
                                        Create the official customs declaration form from your shipment.
                                    </p>
                                    <ul class="small text-muted">
                                        <li>Click "Generate Declaration" from the shipment page</li>
                                        <li>System consolidates all invoice items</li>
                                        <li>Duties and taxes are automatically calculated</li>
                                        <li>Declaration is saved in <strong>Declarations → Declarations</strong></li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Step 5: Fill Declaration Form -->
                        <div class="workflow-step mb-4">
                            <div class="d-flex align-items-start">
                                <div class="step-number bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="min-width: 40px; height: 40px; font-weight: bold;">
                                    5
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="mb-2">
                                        <i class="fas fa-edit me-2 text-primary"></i>Fill Declaration Form
                                    </h5>
                                    <p class="text-muted mb-2">
                                        Map your data to the official customs form template.
                                    </p>
                                    <ul class="small text-muted">
                                        <li>Select the official form template for your destination country</li>
                                        <li>AI automatically maps data to form fields</li>
                                        <li>Select trade contacts (shipper, consignee, broker, bank)</li>
                                        <li>Review and adjust field mappings as needed</li>
                                        <li>Preview the filled PDF form</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Step 6: Submit to Customs -->
                        <div class="workflow-step mb-4">
                            <div class="d-flex align-items-start">
                                <div class="step-number bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="min-width: 40px; height: 40px; font-weight: bold;">
                                    6
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="mb-2">
                                        <i class="fas fa-paper-plane me-2 text-success"></i>Submit to Customs
                                    </h5>
                                    <p class="text-muted mb-2">
                                        Submit your declaration directly to the customs authority.
                                    </p>
                                    <ul class="small text-muted">
                                        <li><strong>Web Portal Submission:</strong> Automated browser submission to customs web portals</li>
                                        <li><strong>FTP Submission:</strong> Direct file transfer for CAPS T12 format (BVI)</li>
                                        <li><strong>PDF Download:</strong> Download and submit manually</li>
                                        <li>Set up submission credentials in your user menu → <strong>Submission Credentials</strong></li>
                                        <li>Track submission status and view results</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Key Features -->
    <div class="row mb-4">
        <div class="col-md-6 mb-3">
            <div class="card h-100 border-info">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-star me-2"></i>Key Features</h5>
                </div>
                <div class="card-body">
                    <ul class="mb-0">
                        <li class="mb-2">
                            <strong>Trade Contacts:</strong> Save reusable shipper, consignee, and broker information
                        </li>
                        <li class="mb-2">
                            <strong>Classification Rules:</strong> Customize how specific items are classified
                        </li>
                        <li class="mb-2">
                            <strong>Legacy Clearances:</strong> Import historical clearance data for reference
                        </li>
                        <li class="mb-2">
                            <strong>Multiple Submission Methods:</strong> Web portal automation, FTP, or PDF download
                        </li>
                        <li class="mb-2">
                            <strong>Multi-Country Support:</strong> Work with different countries and their requirements
                        </li>
                        <li class="mb-0">
                            <strong>AI-Powered:</strong> Intelligent data extraction and classification
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-3">
            <div class="card h-100 border-warning">
                <div class="card-header bg-warning">
                    <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Pro Tips</h5>
                </div>
                <div class="card-body">
                    <ul class="mb-0">
                        <li class="mb-2">
                            <strong>Use Trade Contacts:</strong> Save frequently used contacts for faster declaration creation
                        </li>
                        <li class="mb-2">
                            <strong>Set Default Contacts:</strong> Mark your most common shipper/consignee as default
                        </li>
                        <li class="mb-2">
                            <strong>Classification Memory:</strong> The system remembers previous classifications for similar items
                        </li>
                        <li class="mb-2">
                            <strong>Bulk Operations:</strong> Add multiple invoices to a single shipment
                        </li>
                        <li class="mb-2">
                            <strong>Review Before Submitting:</strong> Always preview your declaration before final submission
                        </li>
                        <li class="mb-0">
                            <strong>Submission Credentials:</strong> Set up your customs portal credentials once and reuse them
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-link me-2"></i>Quick Links</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <a href="{{ route('invoices.create') }}" class="btn btn-outline-primary w-100">
                                <i class="fas fa-upload me-2"></i>Upload Invoice
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="{{ route('shipments.index') }}" class="btn btn-outline-primary w-100">
                                <i class="fas fa-ship me-2"></i>View Shipments
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="{{ route('trade-contacts.index') }}" class="btn btn-outline-primary w-100">
                                <i class="fas fa-address-book me-2"></i>Trade Contacts
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="{{ route('settings.classification-rules') }}" class="btn btn-outline-primary w-100">
                                <i class="fas fa-cogs me-2"></i>Classification Rules
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .workflow-step {
        position: relative;
        padding-left: 0;
    }
    
    .workflow-step:not(:last-child)::after {
        content: '';
        position: absolute;
        left: 19px;
        top: 50px;
        width: 2px;
        height: calc(100% - 30px);
        background: #dee2e6;
    }
    
    .step-number {
        font-size: 18px;
        flex-shrink: 0;
    }
</style>
@endpush
@endsection
