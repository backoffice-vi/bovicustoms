<?php

namespace App\Http\Controllers;

use App\Models\DeclarationForm;
use App\Models\WebFormTarget;
use App\Models\WebFormSubmission;
use App\Services\WebFormSubmission\WebFormSubmitterService;
use App\Services\WebFormSubmission\WebFormDataMapper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebSubmissionController extends Controller
{
    protected WebFormSubmitterService $submitter;
    protected WebFormDataMapper $mapper;

    public function __construct(WebFormSubmitterService $submitter, WebFormDataMapper $mapper)
    {
        $this->submitter = $submitter;
        $this->mapper = $mapper;
    }

    /**
     * Show available targets and submission options for a declaration
     */
    public function index(DeclarationForm $declaration)
    {
        $declaration->load(['country', 'invoice', 'shipment']);

        // Get active targets for this country
        $targets = WebFormTarget::active()
            ->where('country_id', $declaration->country_id)
            ->with('pages')
            ->get();

        // Get past submissions for this declaration
        $submissions = WebFormSubmission::where('declaration_form_id', $declaration->id)
            ->with('target')
            ->latest()
            ->get();

        return view('web-submission.index', compact('declaration', 'targets', 'submissions'));
    }

    /**
     * Preview mapping before submission
     */
    public function preview(DeclarationForm $declaration, WebFormTarget $target)
    {
        // Verify target is for the right country
        if ($target->country_id !== $declaration->country_id) {
            return redirect()->back()->with('error', 'Invalid target for this declaration.');
        }

        $declaration->load([
            'country', 
            'declarationItems',
            'invoice.invoiceItems', 
            'shipment.shipperContact',
            'shipment.consigneeContact',
            'shipperContact',
            'consigneeContact',
            'filledForms',
        ]);

        // Load target with pages and field mappings
        $target->load(['pages.fieldMappings']);

        // For CAPS targets, use AI-assisted CAPS mapping
        if ($this->isCapsTarget($target)) {
            $capsData = $this->mapper->buildCapsSubmissionData($declaration, $target, true);
            
            // Convert CAPS data to preview format
            $preview = $this->buildCapsPreview($target, $capsData);
            $validation = $this->validateCapsData($capsData);
            
            return view('web-submission.preview', compact('declaration', 'target', 'preview', 'validation', 'capsData'));
        }

        // Preview the mapping (non-CAPS)
        $preview = $this->mapper->previewMapping($declaration, $target);
        $validation = $this->mapper->validateMapping($declaration, $target);

        return view('web-submission.preview', compact('declaration', 'target', 'preview', 'validation'));
    }

    /**
     * Build preview from CAPS AI-assisted data
     */
    protected function buildCapsPreview(WebFormTarget $target, array $capsData): array
    {
        $headerData = $capsData['headerData'] ?? [];
        $items = $capsData['items'] ?? [];
        
        $pages = [];
        
        // Header page
        $headerFields = [];
        $headerLabels = [
            'td_type' => 'TD Type',
            'trader_reference' => 'Trader Reference',
            'supplier_name' => 'Supplier Name',
            'supplier_street' => 'Supplier Street',
            'supplier_city' => 'Supplier City',
            'supplier_country' => 'Supplier Country',
            'carrier_id' => 'Carrier ID',
            'port_of_arrival' => 'Port of Arrival',
            'arrival_date' => 'Arrival Date',
            'manifest_number' => 'Manifest Number',
            'packages_count' => 'Total Packages',
            'bill_of_lading' => 'Bill of Lading / AWB',
            'city_of_shipment' => 'City of Shipment',
            'country_of_shipment' => 'Country of Shipment',
            'total_freight' => 'Total Freight',
            'total_insurance' => 'Total Insurance',
        ];
        
        foreach ($headerLabels as $key => $label) {
            $value = $headerData[$key] ?? null;
            $headerFields[] = [
                'label' => $label,
                'type' => 'text',
                'value' => $value,
                'source' => 'AI-assisted CAPS mapping',
                'required' => in_array($key, ['supplier_name', 'arrival_date', 'bill_of_lading']),
                'has_value' => !empty($value),
                'section' => 'Header',
            ];
        }
        
        $pages[] = [
            'page_name' => 'Header Information',
            'page_type' => 'header',
            'url' => $target->base_url,
            'fields' => $headerFields,
        ];
        
        // Items page(s)
        foreach ($items as $index => $item) {
            $itemFields = [];
            $itemLabels = [
                'cpc' => 'CPC Code',
                'tariff_number' => 'Tariff No.',
                'country_of_origin' => 'Country of Origin',
                'packages_number' => 'No. of Packages',
                'packages_type' => 'Package Type',
                'description' => 'Description',
                'net_weight' => 'Net Weight',
                'quantity' => 'Quantity',
                'units' => 'Units',
                'fob_value' => 'F.O.B. Value',
                'currency' => 'Currency',
                'freight_amount' => 'Freight Amount',
                'insurance_amount' => 'Insurance Amount',
                'cif_value' => 'Value for Tax 1 (CIF)',
            ];
            
            foreach ($itemLabels as $key => $label) {
                $value = $item[$key] ?? null;
                $itemFields[] = [
                    'label' => $label,
                    'type' => 'text',
                    'value' => $value,
                    'source' => 'AI-assisted CAPS mapping',
                    'required' => in_array($key, ['tariff_number', 'description', 'quantity', 'fob_value']),
                    'has_value' => !empty($value),
                    'section' => 'Item ' . ($index + 1),
                ];
            }
            
            $pages[] = [
                'page_name' => 'Item ' . ($index + 1),
                'page_type' => 'item',
                'url' => $target->base_url,
                'fields' => $itemFields,
            ];
        }
        
        // Calculate summary
        $totalFields = 0;
        $filledFields = 0;
        $unmappedRequired = [];
        
        foreach ($pages as $page) {
            foreach ($page['fields'] as $field) {
                $totalFields++;
                if ($field['has_value']) {
                    $filledFields++;
                } elseif ($field['required']) {
                    $unmappedRequired[] = ['field' => $field['label'], 'local_field' => 'caps_ai'];
                }
            }
        }
        
        return [
            'target_name' => $target->name,
            'target_url' => $target->base_url,
            'pages' => $pages,
            'summary' => [
                'total_fields' => $totalFields,
                'filled_fields' => $filledFields,
                'unmapped_required' => $unmappedRequired,
                'ready_to_submit' => empty($unmappedRequired),
            ],
            'ai_assisted' => $capsData['ai_assisted'] ?? false,
            'ai_notes' => $capsData['ai_notes'] ?? null,
        ];
    }
    
    /**
     * Validate CAPS data for required fields
     */
    protected function validateCapsData(array $capsData): array
    {
        $errors = [];
        $warnings = [];
        
        $headerData = $capsData['headerData'] ?? [];
        $items = $capsData['items'] ?? [];
        
        // Check credentials
        $creds = $capsData['credentials'] ?? [];
        if (empty($creds['username'])) {
            $errors[] = 'CAPS username not configured in target settings';
        }
        if (empty($creds['password'])) {
            $errors[] = 'CAPS password not configured in target settings';
        }
        
        // Check required header fields
        $requiredHeader = ['supplier_name', 'arrival_date', 'bill_of_lading'];
        foreach ($requiredHeader as $field) {
            if (empty($headerData[$field])) {
                $warnings[] = "Missing header field: {$field}";
            }
        }
        
        // Check items
        if (empty($items)) {
            $errors[] = 'No items found in declaration';
        } else {
            foreach ($items as $i => $item) {
                $itemNum = $i + 1;
                if (empty($item['tariff_number'])) {
                    $warnings[] = "Item {$itemNum}: Missing tariff number";
                }
                if (empty($item['description'])) {
                    $warnings[] = "Item {$itemNum}: Missing description";
                }
                if (empty($item['fob_value']) || $item['fob_value'] == '0.00') {
                    $warnings[] = "Item {$itemNum}: Missing or zero FOB value";
                }
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Submit the declaration to the external portal
     */
    public function submit(Request $request, DeclarationForm $declaration, WebFormTarget $target)
    {
        // Verify target is for the right country
        if ($target->country_id !== $declaration->country_id) {
            return redirect()->back()->with('error', 'Invalid target for this declaration.');
        }

        // Validate mapping first
        $validation = $this->mapper->validateMapping($declaration, $target);
        if (!$validation['valid'] && !$request->has('force')) {
            return redirect()->back()->with('error', 'Mapping validation failed: ' . implode(', ', $validation['errors']));
        }

        try {
            // Determine action: 'save' (default, safer) or 'submit' (full submission)
            $action = $request->input('action', 'save');
            
            // Check if this is a CAPS target
            if ($this->isCapsTarget($target)) {
                $useAI = $request->boolean('use_ai', true); // AI enabled by default for CAPS
                $submission = $this->submitter->submitToCaps($declaration, $target, $action, $useAI);
            } else {
                $useAI = $request->boolean('use_ai', $target->requires_ai);
                $submission = $this->submitter->submit($declaration, $target, $useAI);
            }

            if ($submission->is_successful) {
                $message = $action === 'save' 
                    ? 'Declaration saved to portal! TD: ' . $submission->external_reference
                    : 'Declaration submitted successfully! Reference: ' . $submission->external_reference;
                    
                return redirect()->route('web-submission.result', [
                    'declaration' => $declaration,
                    'submission' => $submission,
                ])->with('success', $message);
            } else {
                return redirect()->route('web-submission.result', [
                    'declaration' => $declaration,
                    'submission' => $submission,
                ])->with('error', 'Submission failed: ' . $submission->error_message);
            }

        } catch (\Exception $e) {
            Log::error('Web submission failed', [
                'declaration_id' => $declaration->id,
                'target_id' => $target->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Submission failed: ' . $e->getMessage());
        }
    }

    /**
     * Check if target is CAPS
     */
    protected function isCapsTarget(WebFormTarget $target): bool
    {
        return str_contains(strtolower($target->base_url ?? ''), 'caps.gov.vg') ||
               str_contains(strtolower($target->name ?? ''), 'caps');
    }

    /**
     * Show submission result
     */
    public function result(DeclarationForm $declaration, WebFormSubmission $submission)
    {
        $submission->load(['target', 'user']);
        $declaration->load(['country', 'invoice']);

        return view('web-submission.result', compact('declaration', 'submission'));
    }

    /**
     * View all submissions for a declaration
     */
    public function history(DeclarationForm $declaration)
    {
        $submissions = WebFormSubmission::where('declaration_form_id', $declaration->id)
            ->with(['target', 'user'])
            ->latest()
            ->paginate(10);

        return view('web-submission.history', compact('declaration', 'submissions'));
    }

    /**
     * Retry a failed submission
     */
    public function retry(WebFormSubmission $submission)
    {
        if (!$submission->can_retry) {
            return redirect()->back()->with('error', 'This submission cannot be retried.');
        }

        try {
            $newSubmission = $this->submitter->retry($submission);

            return redirect()->route('web-submission.result', [
                'declaration' => $newSubmission->declaration_form_id,
                'submission' => $newSubmission,
            ])->with('info', 'Retry submission created.');

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Retry failed: ' . $e->getMessage());
        }
    }

    /**
     * Get available targets for a country (AJAX)
     */
    public function getTargetsForCountry(Request $request)
    {
        $countryId = $request->input('country_id');
        
        $targets = WebFormTarget::active()
            ->where('country_id', $countryId)
            ->get(['id', 'name', 'code', 'base_url']);

        return response()->json($targets);
    }
}
