<?php

namespace App\Http\Controllers;

use App\Models\DeclarationForm;
use App\Models\DeclarationFormItem;
use App\Models\CountryFormTemplate;
use App\Models\FilledDeclarationForm;
use App\Models\TradeContact;
use App\Services\FormFieldExtractor;
use App\Services\FormDataMapper;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class DeclarationFormController extends Controller
{
    protected FormFieldExtractor $fieldExtractor;
    protected FormDataMapper $dataMapper;

    public function __construct(FormFieldExtractor $fieldExtractor, FormDataMapper $dataMapper)
    {
        $this->fieldExtractor = $fieldExtractor;
        $this->dataMapper = $dataMapper;
    }

    /**
     * Display a listing of declaration forms
     */
    public function index()
    {
        $declarationForms = DeclarationForm::with(['country', 'invoice'])
            ->latest()
            ->paginate(10);

        return view('declaration-forms.index', compact('declarationForms'));
    }

    /**
     * Display the specified declaration form
     */
    public function show(DeclarationForm $declarationForm)
    {
        $items = DeclarationFormItem::where('declaration_form_id', $declarationForm->id)
            ->orderBy('line_number')
            ->get();

        // Get any filled forms for this declaration
        $filledForms = FilledDeclarationForm::where('declaration_form_id', $declarationForm->id)
            ->with('template')
            ->latest()
            ->get();

        // Count available templates for this country
        $availableTemplatesCount = CountryFormTemplate::active()
            ->where('country_id', $declarationForm->country_id)
            ->count();

        return view('declaration-forms.show', compact('declarationForm', 'items', 'filledForms', 'availableTemplatesCount'));
    }

    /**
     * Show available form templates for selection
     */
    public function selectTemplates(DeclarationForm $declarationForm)
    {
        $declarationForm->load(['country', 'invoice']);
        
        $templates = CountryFormTemplate::active()
            ->where('country_id', $declarationForm->country_id)
            ->orderBy('name')
            ->get();

        if ($templates->isEmpty()) {
            return redirect()->route('declaration-forms.show', $declarationForm)
                ->with('error', 'No form templates available for ' . ($declarationForm->country?->name ?? 'this country') . '. Please contact admin to upload templates.');
        }

        return view('declaration-forms.select-templates', compact('declarationForm', 'templates'));
    }

    /**
     * Analyze selected template(s) and start the fill process
     */
    public function analyzeTemplate(Request $request, DeclarationForm $declarationForm)
    {
        $request->validate([
            'template_id' => 'required|exists:country_form_templates,id',
        ]);

        $template = CountryFormTemplate::findOrFail($request->template_id);
        
        // Verify template belongs to the same country
        if ($template->country_id !== $declarationForm->country_id) {
            return redirect()->back()->with('error', 'Invalid template selection.');
        }

        // Extract fields from the template using AI
        $extractedFields = $this->fieldExtractor->extractFields($template);

        if (!$extractedFields['success']) {
            return redirect()->back()->with('error', 'Failed to analyze form template: ' . ($extractedFields['error'] ?? 'Unknown error'));
        }

        // Create a new filled declaration form record
        $user = auth()->user();
        $filledForm = FilledDeclarationForm::create([
            'declaration_form_id' => $declarationForm->id,
            'country_form_template_id' => $template->id,
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
            'extracted_fields' => $extractedFields['fields'],
            'status' => FilledDeclarationForm::STATUS_IN_PROGRESS,
        ]);

        return redirect()->route('declaration-forms.fill', [
            'declarationForm' => $declarationForm,
            'filledForm' => $filledForm,
        ]);
    }

    /**
     * Show the form filling wizard - auto-populates all available data for review
     */
    public function fillForm(DeclarationForm $declarationForm, FilledDeclarationForm $filledForm)
    {
        // Verify ownership
        if ($filledForm->declaration_form_id !== $declarationForm->id) {
            abort(404);
        }

        $declarationForm->load(['country', 'invoice.invoiceItems', 'declarationItems', 'shipment.shipperContact', 'shipment.consigneeContact', 'shipment.notifyPartyContact', 'shipperContact', 'consigneeContact']);
        $filledForm->load('template');

        // Get available trade contacts
        $shippers = TradeContact::shippers()->orderByDesc('is_default')->orderBy('company_name')->get();
        $consignees = TradeContact::consignees()->orderByDesc('is_default')->orderBy('company_name')->get();
        $brokers = TradeContact::brokers()->orderByDesc('is_default')->orderBy('company_name')->get();
        $banks = TradeContact::banks()->orderByDesc('is_default')->orderBy('company_name')->get();
        $notifyParties = TradeContact::notifyParties()->orderByDesc('is_default')->orderBy('company_name')->get();

        // Auto-select trade contacts from declaration or shipment
        $selectedShipperId = $declarationForm->shipper_contact_id 
            ?? $declarationForm->shipment?->shipper_contact_id
            ?? $shippers->where('is_default', true)->first()?->id
            ?? $shippers->first()?->id;
            
        $selectedConsigneeId = $declarationForm->consignee_contact_id 
            ?? $declarationForm->shipment?->consignee_contact_id
            ?? $consignees->where('is_default', true)->first()?->id
            ?? $consignees->first()?->id;
            
        $selectedNotifyPartyId = $declarationForm->shipment?->notify_party_contact_id
            ?? $notifyParties->first()?->id;
            
        $selectedBrokerId = $brokers->where('is_default', true)->first()?->id
            ?? $brokers->first()?->id;

        // Get required contact types for this form
        $requiredContactTypes = $this->fieldExtractor->getRequiredContactTypes($filledForm->extracted_fields ?? []);

        // Auto-map all data to fields using selected contacts
        $contacts = [];
        if ($selectedShipperId) {
            $contacts['shipper'] = TradeContact::find($selectedShipperId);
        }
        if ($selectedConsigneeId) {
            $contacts['consignee'] = TradeContact::find($selectedConsigneeId);
        }
        if ($selectedBrokerId) {
            $contacts['broker'] = TradeContact::find($selectedBrokerId);
        }
        if ($selectedNotifyPartyId) {
            $contacts['notify_party'] = TradeContact::find($selectedNotifyPartyId);
        }

        // Map data to fields automatically
        $mappingResult = $this->dataMapper->mapDataToFields(
            $filledForm->extracted_fields ?? [],
            $declarationForm,
            $contacts
        );

        // Group fields by section with their auto-filled values
        $fieldsBySection = $this->dataMapper->getFieldsBySection($mappingResult['mappings']);

        return view('declaration-forms.fill-form', compact(
            'declarationForm',
            'filledForm',
            'shippers',
            'consignees',
            'brokers',
            'banks',
            'notifyParties',
            'selectedShipperId',
            'selectedConsigneeId',
            'selectedNotifyPartyId',
            'selectedBrokerId',
            'requiredContactTypes',
            'fieldsBySection',
            'mappingResult'
        ));
    }

    /**
     * Process the filled form data
     */
    public function processFill(Request $request, DeclarationForm $declarationForm, FilledDeclarationForm $filledForm)
    {
        // Verify ownership
        if ($filledForm->declaration_form_id !== $declarationForm->id) {
            abort(404);
        }

        $validated = $request->validate([
            'shipper_contact_id' => 'nullable|exists:trade_contacts,id',
            'consignee_contact_id' => 'nullable|exists:trade_contacts,id',
            'broker_contact_id' => 'nullable|exists:trade_contacts,id',
            'bank_contact_id' => 'nullable|exists:trade_contacts,id',
            'notify_party_contact_id' => 'nullable|exists:trade_contacts,id',
            'output_format' => 'required|in:web,pdf_overlay,pdf_fillable,data_only',
            'fields' => 'nullable|array',
        ]);

        // Load selected contacts
        $contacts = [];
        if ($validated['shipper_contact_id'] ?? null) {
            $contacts['shipper'] = TradeContact::find($validated['shipper_contact_id']);
        }
        if ($validated['consignee_contact_id'] ?? null) {
            $contacts['consignee'] = TradeContact::find($validated['consignee_contact_id']);
        }
        if ($validated['broker_contact_id'] ?? null) {
            $contacts['broker'] = TradeContact::find($validated['broker_contact_id']);
        }
        if ($validated['bank_contact_id'] ?? null) {
            $contacts['bank'] = TradeContact::find($validated['bank_contact_id']);
        }
        if ($validated['notify_party_contact_id'] ?? null) {
            $contacts['notify_party'] = TradeContact::find($validated['notify_party_contact_id']);
        }

        // Map data to fields
        $declarationForm->load(['invoice.invoiceItems', 'declarationItems', 'country']);
        $mappingResult = $this->dataMapper->mapDataToFields(
            $filledForm->extracted_fields ?? [],
            $declarationForm,
            $contacts
        );

        // Merge with user-provided field values
        $fieldMappings = $mappingResult['mappings'];
        $userProvidedData = [];

        foreach ($validated['fields'] ?? [] as $fieldName => $value) {
            if (!empty($value)) {
                if (isset($fieldMappings[$fieldName])) {
                    $fieldMappings[$fieldName]['value'] = $value;
                    $fieldMappings[$fieldName]['is_auto_filled'] = false;
                }
                $userProvidedData[$fieldName] = $value;
            }
        }

        // Update the filled form
        $filledForm->update([
            'shipper_contact_id' => $validated['shipper_contact_id'] ?? null,
            'consignee_contact_id' => $validated['consignee_contact_id'] ?? null,
            'broker_contact_id' => $validated['broker_contact_id'] ?? null,
            'bank_contact_id' => $validated['bank_contact_id'] ?? null,
            'notify_party_contact_id' => $validated['notify_party_contact_id'] ?? null,
            'field_mappings' => $fieldMappings,
            'user_provided_data' => $userProvidedData,
            'output_format' => $validated['output_format'],
            'status' => FilledDeclarationForm::STATUS_COMPLETE,
        ]);

        return redirect()->route('declaration-forms.preview', [
            'declarationForm' => $declarationForm,
            'filledForm' => $filledForm,
        ])->with('success', 'Form data saved successfully!');
    }

    /**
     * Preview the filled form
     */
    public function preview(DeclarationForm $declarationForm, FilledDeclarationForm $filledForm)
    {
        // Verify ownership
        if ($filledForm->declaration_form_id !== $declarationForm->id) {
            abort(404);
        }

        $filledForm->load(['template', 'shipperContact', 'consigneeContact', 'brokerContact']);
        $declarationForm->load(['country', 'invoice']);

        // Group field mappings by section
        $fieldsBySection = $this->dataMapper->getFieldsBySection($filledForm->field_mappings ?? []);

        return view('declaration-forms.preview', compact(
            'declarationForm',
            'filledForm',
            'fieldsBySection'
        ));
    }

    /**
     * AJAX endpoint to get auto-mapped data based on selected contacts
     */
    public function getAutoMappedData(Request $request, DeclarationForm $declarationForm, FilledDeclarationForm $filledForm): JsonResponse
    {
        $request->validate([
            'shipper_contact_id' => 'nullable|exists:trade_contacts,id',
            'consignee_contact_id' => 'nullable|exists:trade_contacts,id',
            'broker_contact_id' => 'nullable|exists:trade_contacts,id',
            'bank_contact_id' => 'nullable|exists:trade_contacts,id',
            'notify_party_contact_id' => 'nullable|exists:trade_contacts,id',
        ]);

        // Load selected contacts
        $contacts = [];
        if ($request->shipper_contact_id) {
            $contacts['shipper'] = TradeContact::find($request->shipper_contact_id);
        }
        if ($request->consignee_contact_id) {
            $contacts['consignee'] = TradeContact::find($request->consignee_contact_id);
        }
        if ($request->broker_contact_id) {
            $contacts['broker'] = TradeContact::find($request->broker_contact_id);
        }
        if ($request->bank_contact_id) {
            $contacts['bank'] = TradeContact::find($request->bank_contact_id);
        }
        if ($request->notify_party_contact_id) {
            $contacts['notify_party'] = TradeContact::find($request->notify_party_contact_id);
        }

        // Map data
        $declarationForm->load(['invoice.invoiceItems', 'declarationItems', 'country']);
        $mappingResult = $this->dataMapper->mapDataToFields(
            $filledForm->extracted_fields ?? [],
            $declarationForm,
            $contacts
        );

        return response()->json([
            'success' => true,
            'mappings' => $mappingResult['mappings'],
            'auto_filled_count' => $mappingResult['auto_filled_count'],
            'total_fields' => $mappingResult['total_fields'],
        ]);
    }

    /**
     * Placeholder endpoint for legacy form generation
     */
    public function store(Request $request)
    {
        return back()->with('error', 'Please use the "Generate Official Forms" button to create declaration forms.');
    }
}
