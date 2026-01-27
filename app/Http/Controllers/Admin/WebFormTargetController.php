<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WebFormTarget;
use App\Models\WebFormPage;
use App\Models\WebFormFieldMapping;
use App\Models\WebFormSubmission;
use App\Models\Country;
use App\Services\Browser\PlaywrightService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebFormTargetController extends Controller
{
    protected PlaywrightService $playwright;

    public function __construct(PlaywrightService $playwright)
    {
        $this->playwright = $playwright;
    }

    /**
     * Display a listing of web form targets
     */
    public function index()
    {
        $targets = WebFormTarget::with(['country', 'pages'])
            ->withCount(['submissions', 'pages'])
            ->latest()
            ->paginate(15);

        $stats = [
            'total_targets' => WebFormTarget::count(),
            'active_targets' => WebFormTarget::active()->count(),
            'total_submissions' => WebFormSubmission::count(),
            'successful_submissions' => WebFormSubmission::successful()->count(),
        ];

        return view('admin.web-form-targets.index', compact('targets', 'stats'));
    }

    /**
     * Show the form for creating a new target
     */
    public function create()
    {
        $countries = Country::orderBy('name')->get();
        
        return view('admin.web-form-targets.create', compact('countries'));
    }

    /**
     * Store a newly created target
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'country_id' => 'required|exists:countries,id',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:web_form_targets,code',
            'base_url' => 'required|url|max:500',
            'login_url' => 'required|string|max:500',
            'auth_type' => 'required|in:form,oauth,api_key,none',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'username_field' => 'nullable|string|max:255',
            'password_field' => 'nullable|string|max:255',
            'submit_selector' => 'nullable|string|max:255',
            'requires_ai' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        // Generate code if not provided
        if (empty($validated['code'])) {
            $validated['code'] = Str::slug($validated['name']) . '_' . strtolower($request->country_id);
        }

        // Build credentials array
        $credentials = null;
        if ($validated['auth_type'] === 'form' && ($validated['username'] ?? null)) {
            $credentials = [
                'username' => $validated['username'],
                'password' => $validated['password'] ?? '',
                'username_field' => $validated['username_field'] ?? 'input[name="username"]',
                'password_field' => $validated['password_field'] ?? 'input[name="password"]',
                'submit_selector' => $validated['submit_selector'] ?? 'button[type="submit"]',
            ];
        }

        $target = WebFormTarget::create([
            'country_id' => $validated['country_id'],
            'name' => $validated['name'],
            'code' => $validated['code'],
            'base_url' => rtrim($validated['base_url'], '/'),
            'login_url' => $validated['login_url'],
            'auth_type' => $validated['auth_type'],
            'credentials' => $credentials,
            'requires_ai' => $validated['requires_ai'] ?? true,
            'notes' => $validated['notes'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->route('admin.web-form-targets.show', $target)
            ->with('success', 'Web form target created successfully!');
    }

    /**
     * Display the specified target
     */
    public function show(WebFormTarget $webFormTarget)
    {
        $webFormTarget->load(['country', 'pages.fieldMappings', 'submissions' => function ($query) {
            $query->latest()->limit(10);
        }]);

        $stats = [
            'total_submissions' => $webFormTarget->submissions()->count(),
            'successful_submissions' => $webFormTarget->submissions()->successful()->count(),
            'failed_submissions' => $webFormTarget->submissions()->failed()->count(),
            'total_fields' => $webFormTarget->getAllFieldMappings()->count(),
        ];

        return view('admin.web-form-targets.show', compact('webFormTarget', 'stats'));
    }

    /**
     * Show the form for editing the target
     */
    public function edit(WebFormTarget $webFormTarget)
    {
        $countries = Country::orderBy('name')->get();
        $credentials = $webFormTarget->decrypted_credentials;

        return view('admin.web-form-targets.edit', compact('webFormTarget', 'countries', 'credentials'));
    }

    /**
     * Update the specified target
     */
    public function update(Request $request, WebFormTarget $webFormTarget)
    {
        $validated = $request->validate([
            'country_id' => 'required|exists:countries,id',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:web_form_targets,code,' . $webFormTarget->id,
            'base_url' => 'required|url|max:500',
            'login_url' => 'required|string|max:500',
            'auth_type' => 'required|in:form,oauth,api_key,none',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'username_field' => 'nullable|string|max:255',
            'password_field' => 'nullable|string|max:255',
            'submit_selector' => 'nullable|string|max:255',
            'requires_ai' => 'boolean',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        // Build credentials array
        $credentials = null;
        if ($validated['auth_type'] === 'form') {
            $existingCreds = $webFormTarget->decrypted_credentials ?? [];
            $credentials = [
                'username' => $validated['username'] ?? $existingCreds['username'] ?? '',
                'password' => $validated['password'] ?? $existingCreds['password'] ?? '',
                'username_field' => $validated['username_field'] ?? $existingCreds['username_field'] ?? 'input[name="username"]',
                'password_field' => $validated['password_field'] ?? $existingCreds['password_field'] ?? 'input[name="password"]',
                'submit_selector' => $validated['submit_selector'] ?? $existingCreds['submit_selector'] ?? 'button[type="submit"]',
            ];
        }

        $webFormTarget->update([
            'country_id' => $validated['country_id'],
            'name' => $validated['name'],
            'code' => $validated['code'] ?? $webFormTarget->code,
            'base_url' => rtrim($validated['base_url'], '/'),
            'login_url' => $validated['login_url'],
            'auth_type' => $validated['auth_type'],
            'credentials' => $credentials,
            'requires_ai' => $validated['requires_ai'] ?? true,
            'is_active' => $validated['is_active'] ?? true,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('admin.web-form-targets.show', $webFormTarget)
            ->with('success', 'Web form target updated successfully!');
    }

    /**
     * Remove the specified target
     */
    public function destroy(WebFormTarget $webFormTarget)
    {
        $name = $webFormTarget->name;
        $webFormTarget->delete();

        return redirect()->route('admin.web-form-targets.index')
            ->with('success', "Web form target '{$name}' has been deleted.");
    }

    /**
     * Test connection to the target
     */
    public function testConnection(WebFormTarget $webFormTarget)
    {
        $result = $this->playwright->withAI(false)->testConnection(
            $webFormTarget->full_login_url,
            $webFormTarget->getPlaywrightCredentials()
        );

        if ($result['success']) {
            $webFormTarget->markTested();
            return response()->json([
                'success' => true,
                'message' => 'Connection successful!',
                'logs' => $result['logs'] ?? [],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['error'] ?? 'Connection failed',
            'logs' => $result['logs'] ?? [],
        ], 400);
    }

    /**
     * Toggle active status
     */
    public function toggleActive(WebFormTarget $webFormTarget)
    {
        $webFormTarget->update(['is_active' => !$webFormTarget->is_active]);

        $status = $webFormTarget->is_active ? 'activated' : 'deactivated';
        return redirect()->back()->with('success', "Target has been {$status}.");
    }

    // ==========================================
    // Page Management
    // ==========================================

    /**
     * Show form to add a page to the target
     */
    public function createPage(WebFormTarget $webFormTarget)
    {
        return view('admin.web-form-targets.pages.create', compact('webFormTarget'));
    }

    /**
     * Store a new page
     */
    public function storePage(Request $request, WebFormTarget $webFormTarget)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'url_pattern' => 'required|string|max:500',
            'page_type' => 'required|in:login,form,confirmation,search,other',
            'sequence_order' => 'nullable|integer|min:1',
            'submit_selector' => 'nullable|string|max:255',
            'success_indicator' => 'nullable|string|max:500',
            'error_indicator' => 'nullable|string|max:500',
        ]);

        $maxOrder = $webFormTarget->pages()->max('sequence_order') ?? 0;

        $page = $webFormTarget->pages()->create([
            'name' => $validated['name'],
            'url_pattern' => $validated['url_pattern'],
            'page_type' => $validated['page_type'],
            'sequence_order' => $validated['sequence_order'] ?? ($maxOrder + 1),
            'submit_selector' => $validated['submit_selector'],
            'success_indicator' => $validated['success_indicator'],
            'error_indicator' => $validated['error_indicator'],
            'is_active' => true,
        ]);

        return redirect()->route('admin.web-form-targets.show', $webFormTarget)
            ->with('success', "Page '{$page->name}' added successfully!");
    }

    /**
     * Show page details and field mappings
     */
    public function showPage(WebFormTarget $webFormTarget, WebFormPage $page)
    {
        $page->load('fieldMappings.dropdownValues');

        return view('admin.web-form-targets.pages.show', compact('webFormTarget', 'page'));
    }

    /**
     * Edit page
     */
    public function editPage(WebFormTarget $webFormTarget, WebFormPage $page)
    {
        return view('admin.web-form-targets.pages.edit', compact('webFormTarget', 'page'));
    }

    /**
     * Update page
     */
    public function updatePage(Request $request, WebFormTarget $webFormTarget, WebFormPage $page)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'url_pattern' => 'required|string|max:500',
            'page_type' => 'required|in:login,form,confirmation,search,other',
            'sequence_order' => 'nullable|integer|min:1',
            'submit_selector' => 'nullable|string|max:255',
            'success_indicator' => 'nullable|string|max:500',
            'error_indicator' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]);

        $page->update($validated);

        return redirect()->route('admin.web-form-targets.pages.show', [$webFormTarget, $page])
            ->with('success', 'Page updated successfully!');
    }

    /**
     * Delete page
     */
    public function destroyPage(WebFormTarget $webFormTarget, WebFormPage $page)
    {
        $name = $page->name;
        $page->delete();

        return redirect()->route('admin.web-form-targets.show', $webFormTarget)
            ->with('success', "Page '{$name}' has been deleted.");
    }

    // ==========================================
    // Field Mapping Management
    // ==========================================

    /**
     * Show form to add a field mapping
     */
    public function createMapping(WebFormTarget $webFormTarget, WebFormPage $page)
    {
        $localFields = $this->getAvailableLocalFields();

        return view('admin.web-form-targets.mappings.create', compact('webFormTarget', 'page', 'localFields'));
    }

    /**
     * Store a field mapping
     */
    public function storeMapping(Request $request, WebFormTarget $webFormTarget, WebFormPage $page)
    {
        $validated = $request->validate([
            'local_field' => 'nullable|string|max:255',
            'local_table' => 'nullable|string|max:100',
            'local_column' => 'nullable|string|max:100',
            'local_relation' => 'nullable|string|max:100',
            'web_field_label' => 'required|string|max:255',
            'web_field_name' => 'nullable|string|max:255',
            'web_field_id' => 'nullable|string|max:255',
            'web_field_selectors' => 'required|string', // Comma-separated
            'field_type' => 'required|in:text,select,checkbox,radio,date,textarea,hidden,number',
            'default_value' => 'nullable|string|max:500',
            'static_value' => 'nullable|string|max:500',
            'is_required' => 'boolean',
            'max_length' => 'nullable|integer|min:1',
            'tab_order' => 'nullable|integer|min:1',
            'section' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        // Parse selectors
        $selectors = array_map('trim', explode(',', $validated['web_field_selectors']));

        $maxOrder = $page->fieldMappings()->max('tab_order') ?? 0;

        $mapping = $page->fieldMappings()->create([
            'local_field' => $validated['local_field'],
            'local_table' => $validated['local_table'],
            'local_column' => $validated['local_column'],
            'local_relation' => $validated['local_relation'],
            'web_field_label' => $validated['web_field_label'],
            'web_field_name' => $validated['web_field_name'],
            'web_field_id' => $validated['web_field_id'],
            'web_field_selectors' => $selectors,
            'field_type' => $validated['field_type'],
            'default_value' => $validated['default_value'],
            'static_value' => $validated['static_value'],
            'is_required' => $validated['is_required'] ?? false,
            'max_length' => $validated['max_length'],
            'tab_order' => $validated['tab_order'] ?? ($maxOrder + 1),
            'section' => $validated['section'],
            'notes' => $validated['notes'],
            'is_active' => true,
        ]);

        // Update target's last_mapped_at
        $webFormTarget->markMapped();

        return redirect()->route('admin.web-form-targets.pages.show', [$webFormTarget, $page])
            ->with('success', "Field mapping '{$mapping->web_field_label}' added successfully!");
    }

    /**
     * Edit field mapping
     */
    public function editMapping(WebFormTarget $webFormTarget, WebFormPage $page, WebFormFieldMapping $mapping)
    {
        $localFields = $this->getAvailableLocalFields();

        return view('admin.web-form-targets.mappings.edit', compact('webFormTarget', 'page', 'mapping', 'localFields'));
    }

    /**
     * Update field mapping
     */
    public function updateMapping(Request $request, WebFormTarget $webFormTarget, WebFormPage $page, WebFormFieldMapping $mapping)
    {
        $validated = $request->validate([
            'local_field' => 'nullable|string|max:255',
            'local_table' => 'nullable|string|max:100',
            'local_column' => 'nullable|string|max:100',
            'local_relation' => 'nullable|string|max:100',
            'web_field_label' => 'required|string|max:255',
            'web_field_name' => 'nullable|string|max:255',
            'web_field_id' => 'nullable|string|max:255',
            'web_field_selectors' => 'required|string',
            'field_type' => 'required|in:text,select,checkbox,radio,date,textarea,hidden,number',
            'default_value' => 'nullable|string|max:500',
            'static_value' => 'nullable|string|max:500',
            'is_required' => 'boolean',
            'max_length' => 'nullable|integer|min:1',
            'tab_order' => 'nullable|integer|min:1',
            'section' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        // Parse selectors
        $selectors = array_map('trim', explode(',', $validated['web_field_selectors']));
        $validated['web_field_selectors'] = $selectors;

        $mapping->update($validated);

        return redirect()->route('admin.web-form-targets.pages.show', [$webFormTarget, $page])
            ->with('success', 'Field mapping updated successfully!');
    }

    /**
     * Delete field mapping
     */
    public function destroyMapping(WebFormTarget $webFormTarget, WebFormPage $page, WebFormFieldMapping $mapping)
    {
        $label = $mapping->web_field_label;
        $mapping->delete();

        return redirect()->route('admin.web-form-targets.pages.show', [$webFormTarget, $page])
            ->with('success', "Field mapping '{$label}' has been deleted.");
    }

    /**
     * Get available local fields for mapping
     */
    protected function getAvailableLocalFields(): array
    {
        return [
            'Declaration' => [
                'declaration.form_number' => 'Form Number',
                'declaration.declaration_date' => 'Declaration Date',
                'declaration.fob_value' => 'FOB Value',
                'declaration.freight_total' => 'Freight Total',
                'declaration.insurance_total' => 'Insurance Total',
                'declaration.cif_value' => 'CIF Value',
                'declaration.customs_duty_total' => 'Customs Duty Total',
                'declaration.wharfage_total' => 'Wharfage Total',
                'declaration.total_duty' => 'Total Duty',
                'declaration.vessel_name' => 'Vessel Name',
                'declaration.carrier_name' => 'Carrier Name',
                'declaration.bill_of_lading_number' => 'Bill of Lading Number',
                'declaration.awb_number' => 'AWB Number',
                'declaration.manifest_number' => 'Manifest Number',
                'declaration.port_of_loading' => 'Port of Loading',
                'declaration.port_of_arrival' => 'Port of Arrival',
                'declaration.arrival_date' => 'Arrival Date',
                'declaration.total_packages' => 'Total Packages',
                'declaration.package_type' => 'Package Type',
                'declaration.gross_weight_kg' => 'Gross Weight (KG)',
                'declaration.net_weight_kg' => 'Net Weight (KG)',
                'declaration.country_of_origin' => 'Country of Origin',
                'declaration.cpc_code' => 'CPC Code',
                'declaration.currency' => 'Currency',
            ],
            'Shipment' => [
                'shipment.bill_of_lading_number' => 'B/L Number',
                'shipment.awb_number' => 'AWB Number',
                'shipment.manifest_number' => 'Manifest Number',
                'shipment.vessel_name' => 'Vessel Name',
                'shipment.carrier_name' => 'Carrier Name',
                'shipment.voyage_number' => 'Voyage Number',
                'shipment.container_id' => 'Container ID',
                'shipment.port_of_loading' => 'Port of Loading',
                'shipment.port_of_discharge' => 'Port of Discharge',
                'shipment.final_destination' => 'Final Destination',
                'shipment.estimated_arrival_date' => 'Estimated Arrival Date',
                'shipment.actual_arrival_date' => 'Actual Arrival Date',
                'shipment.total_packages' => 'Total Packages',
                'shipment.gross_weight_kg' => 'Gross Weight (KG)',
                'shipment.fob_total' => 'FOB Total',
                'shipment.freight_total' => 'Freight Total',
                'shipment.insurance_total' => 'Insurance Total',
                'shipment.cif_total' => 'CIF Total',
            ],
            'Shipper' => [
                'shipper.company_name' => 'Company Name',
                'shipper.contact_name' => 'Contact Name',
                'shipper.address_line_1' => 'Address Line 1',
                'shipper.address_line_2' => 'Address Line 2',
                'shipper.city' => 'City',
                'shipper.state_province' => 'State/Province',
                'shipper.postal_code' => 'Postal Code',
                'shipper.country' => 'Country',
                'shipper.phone' => 'Phone',
                'shipper.email' => 'Email',
                'shipper.tax_id' => 'Tax ID',
                'shipper.customs_registration_id' => 'Customs Registration ID',
            ],
            'Consignee' => [
                'consignee.company_name' => 'Company Name',
                'consignee.contact_name' => 'Contact Name',
                'consignee.address_line_1' => 'Address Line 1',
                'consignee.address_line_2' => 'Address Line 2',
                'consignee.city' => 'City',
                'consignee.state_province' => 'State/Province',
                'consignee.postal_code' => 'Postal Code',
                'consignee.country' => 'Country',
                'consignee.phone' => 'Phone',
                'consignee.email' => 'Email',
                'consignee.tax_id' => 'Tax ID',
                'consignee.customs_registration_id' => 'Customs Registration ID',
            ],
            'Invoice' => [
                'invoice.invoice_number' => 'Invoice Number',
                'invoice.invoice_date' => 'Invoice Date',
                'invoice.total_amount' => 'Total Amount',
            ],
            'Country' => [
                'country.name' => 'Country Name',
                'country.code' => 'Country Code',
            ],
        ];
    }

    // ==========================================
    // Submission History
    // ==========================================

    /**
     * View submissions for a target
     */
    public function submissions(WebFormTarget $webFormTarget)
    {
        $submissions = $webFormTarget->submissions()
            ->with(['declaration', 'user'])
            ->latest()
            ->paginate(20);

        return view('admin.web-form-targets.submissions', compact('webFormTarget', 'submissions'));
    }

    /**
     * View a specific submission
     */
    public function showSubmission(WebFormTarget $webFormTarget, WebFormSubmission $submission)
    {
        $submission->load(['declaration', 'user', 'target']);

        return view('admin.web-form-targets.submission-detail', compact('webFormTarget', 'submission'));
    }
}
