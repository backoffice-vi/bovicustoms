<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\Invoice;
use App\Models\Country;
use App\Models\TradeContact;
use App\Models\DeclarationForm;
use App\Models\DeclarationFormItem;
use App\Services\DutyCalculationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ShipmentController extends Controller
{
    protected DutyCalculationService $dutyCalculator;

    public function __construct(DutyCalculationService $dutyCalculator)
    {
        $this->dutyCalculator = $dutyCalculator;
    }

    /**
     * Display a listing of shipments
     */
    public function index()
    {
        $shipments = Shipment::with(['country', 'consigneeContact', 'shipperContact'])
            ->withCount(['invoices', 'shippingDocuments'])
            ->latest()
            ->paginate(15);

        return view('shipments.index', compact('shipments'));
    }

    /**
     * Show the form for creating a new shipment
     */
    public function create()
    {
        $countries = Country::active()->orderBy('name')->get();
        
        // Get invoices that are not yet assigned to a shipment
        $availableInvoices = Invoice::whereDoesntHave('shipments')
            ->with('country')
            ->where('status', 'processed')
            ->latest()
            ->get();

        // Get trade contacts
        $shippers = TradeContact::shippers()->orderBy('company_name')->get();
        $consignees = TradeContact::consignees()->orderBy('company_name')->get();

        return view('shipments.create', compact('countries', 'availableInvoices', 'shippers', 'consignees'));
    }

    /**
     * Store a newly created shipment
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'country_id' => 'required|exists:countries,id',
            'invoice_ids' => 'required|array|min:1',
            'invoice_ids.*' => 'exists:invoices,id',
            'shipper_contact_id' => 'nullable|exists:trade_contacts,id',
            'consignee_contact_id' => 'nullable|exists:trade_contacts,id',
            'insurance_method' => 'nullable|in:manual,percentage,document',
            'insurance_percentage' => 'nullable|numeric|min:0|max:100',
            'insurance_total' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        $user = auth()->user();

        // Get country's default insurance settings
        $country = Country::find($validated['country_id']);
        $insuranceDefaults = $country->getInsuranceDefaults();

        // Use country defaults if no insurance method specified
        $insuranceMethod = $validated['insurance_method'] ?? $insuranceDefaults['method'];
        $insurancePercentage = $validated['insurance_percentage'] ?? $insuranceDefaults['percentage'];

        // Create the shipment
        $shipment = Shipment::create([
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
            'country_id' => $validated['country_id'],
            'status' => Shipment::STATUS_DRAFT,
            'shipper_contact_id' => $validated['shipper_contact_id'] ?? null,
            'consignee_contact_id' => $validated['consignee_contact_id'] ?? null,
            'insurance_method' => $insuranceMethod,
            'insurance_percentage' => $insurancePercentage,
            'insurance_total' => $validated['insurance_total'] ?? 0,
            'notes' => $validated['notes'] ?? null,
        ]);

        // Attach invoices
        foreach ($validated['invoice_ids'] as $invoiceId) {
            $invoice = Invoice::find($invoiceId);
            if ($invoice) {
                $shipment->invoices()->attach($invoiceId, [
                    'invoice_fob' => $invoice->total_amount,
                ]);
            }
        }

        // Calculate totals
        $shipment->recalculateTotals();

        return redirect()->route('shipments.show', $shipment)
            ->with('success', 'Shipment created successfully. You can now upload shipping documents.');
    }

    /**
     * Display the specified shipment
     */
    public function show(Shipment $shipment)
    {
        $shipment->load([
            'country',
            'invoices.invoiceItems',
            'shippingDocuments',
            'shipperContact',
            'consigneeContact',
            'notifyPartyContact',
            'declarationForms',
        ]);

        // Calculate duties
        $calculation = null;
        if ($shipment->invoices->count() > 0 && $shipment->fob_total > 0) {
            $calculation = $this->dutyCalculator->calculateForShipment($shipment);
        }

        // Get contacts for selection
        $shippers = TradeContact::shippers()->orderBy('company_name')->get();
        $consignees = TradeContact::consignees()->orderBy('company_name')->get();
        $notifyParties = TradeContact::notifyParties()->orderBy('company_name')->get();

        return view('shipments.show', compact('shipment', 'calculation', 'shippers', 'consignees', 'notifyParties'));
    }

    /**
     * Update shipment details
     */
    public function update(Request $request, Shipment $shipment)
    {
        $validated = $request->validate([
            'shipper_contact_id' => 'nullable|exists:trade_contacts,id',
            'consignee_contact_id' => 'nullable|exists:trade_contacts,id',
            'notify_party_contact_id' => 'nullable|exists:trade_contacts,id',
            'freight_total' => 'nullable|numeric|min:0',
            'insurance_method' => 'nullable|in:manual,percentage,document',
            'insurance_percentage' => 'nullable|numeric|min:0|max:100',
            'insurance_total' => 'nullable|numeric|min:0',
            'manifest_number' => 'nullable|string|max:100',
            'bill_of_lading_number' => 'nullable|string|max:100',
            'carrier_name' => 'nullable|string|max:255',
            'vessel_name' => 'nullable|string|max:255',
            'port_of_loading' => 'nullable|string|max:255',
            'port_of_discharge' => 'nullable|string|max:255',
            'final_destination' => 'nullable|string|max:255',
            'estimated_arrival_date' => 'nullable|date',
            'total_packages' => 'nullable|integer|min:0',
            'package_type' => 'nullable|string|max:100',
            'gross_weight_kg' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        $shipment->update($validated);
        $shipment->recalculateTotals();

        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Shipment updated.']);
        }

        return redirect()->route('shipments.show', $shipment)
            ->with('success', 'Shipment updated successfully.');
    }

    /**
     * Add invoices to shipment
     */
    public function addInvoices(Request $request, Shipment $shipment)
    {
        $validated = $request->validate([
            'invoice_ids' => 'required|array|min:1',
            'invoice_ids.*' => 'exists:invoices,id',
        ]);

        foreach ($validated['invoice_ids'] as $invoiceId) {
            $invoice = Invoice::find($invoiceId);
            if ($invoice && !$shipment->invoices()->where('invoice_id', $invoiceId)->exists()) {
                $shipment->invoices()->attach($invoiceId, [
                    'invoice_fob' => $invoice->total_amount,
                ]);
            }
        }

        $shipment->recalculateTotals();

        return redirect()->route('shipments.show', $shipment)
            ->with('success', 'Invoices added to shipment.');
    }

    /**
     * Remove invoice from shipment
     */
    public function removeInvoice(Request $request, Shipment $shipment, Invoice $invoice)
    {
        $shipment->invoices()->detach($invoice->id);
        $shipment->recalculateTotals();

        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Invoice removed.']);
        }

        return redirect()->route('shipments.show', $shipment)
            ->with('success', 'Invoice removed from shipment.');
    }

    /**
     * Generate declaration from shipment
     */
    public function generateDeclaration(Request $request, Shipment $shipment)
    {
        if (!$shipment->canGenerateDeclaration()) {
            return redirect()->route('shipments.show', $shipment)
                ->with('error', 'Cannot generate declaration. Ensure shipment has invoices and FOB value.');
        }

        // Calculate duties
        $calculation = $this->dutyCalculator->calculateForShipment($shipment);

        $user = auth()->user();

        // Create declaration form
        $declaration = DeclarationForm::create([
            'organization_id' => $user->organization_id,
            'country_id' => $shipment->country_id,
            'shipment_id' => $shipment->id,
            'invoice_id' => $shipment->invoices->first()?->id, // Primary invoice
            'shipper_contact_id' => $shipment->shipper_contact_id,
            'consignee_contact_id' => $shipment->consignee_contact_id,
            'form_number' => 'DEC-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6)),
            'declaration_date' => now(),
            'total_duty' => 0, // Will be updated by applyToDeclaration
            'items' => [], // Required field, will be populated later
            'source_type' => 'shipment',
        ]);

        // Populate from shipment
        $declaration->populateFromShipment($shipment);

        // Apply duty calculations
        $this->dutyCalculator->applyToDeclaration($declaration, $calculation);

        // Create declaration items from all invoices
        $lineNumber = 1;
        foreach ($calculation['item_duties'] as $itemDuty) {
            DeclarationFormItem::create([
                'declaration_form_id' => $declaration->id,
                'invoice_id' => $itemDuty['invoice_id'],
                'organization_id' => $user->organization_id,
                'user_id' => $user->id,
                'country_id' => $shipment->country_id,
                'line_number' => $lineNumber++,
                'description' => $itemDuty['description'],
                'quantity' => $itemDuty['quantity'],
                'unit_price' => $itemDuty['unit_price'],
                'line_total' => $itemDuty['fob_value'],
                'hs_code' => $itemDuty['tariff_code'],
                'hs_description' => Str::limit($itemDuty['tariff_description'] ?? '', 250, '...'),
            ]);
        }

        // Update shipment status
        $shipment->updateStatus(Shipment::STATUS_DECLARATION_GENERATED);

        return redirect()->route('declaration-forms.show', $declaration)
            ->with('success', 'Declaration form generated successfully.');
    }

    /**
     * Delete a shipment
     */
    public function destroy(Shipment $shipment)
    {
        // Delete associated shipping documents files
        foreach ($shipment->shippingDocuments as $doc) {
            $doc->deleteFile();
        }

        $shipment->delete();

        return redirect()->route('shipments.index')
            ->with('success', 'Shipment deleted successfully.');
    }

    /**
     * API: Get available invoices for adding to shipment
     */
    public function getAvailableInvoices(Request $request): JsonResponse
    {
        $shipmentId = $request->input('shipment_id');
        
        $query = Invoice::where('status', 'processed');
        
        if ($shipmentId) {
            // Exclude invoices already in this shipment
            $query->whereDoesntHave('shipments', function ($q) use ($shipmentId) {
                $q->where('shipment_id', $shipmentId);
            });
        } else {
            // Exclude invoices in any shipment
            $query->whereDoesntHave('shipments');
        }

        $invoices = $query->latest()->get(['id', 'invoice_number', 'invoice_date', 'total_amount']);

        return response()->json([
            'success' => true,
            'invoices' => $invoices,
        ]);
    }

    /**
     * API: Recalculate and return duty calculation
     */
    public function recalculate(Shipment $shipment): JsonResponse
    {
        $calculation = $this->dutyCalculator->recalculateShipment($shipment);

        return response()->json([
            'success' => true,
            'calculation' => $this->dutyCalculator->formatForDisplay($calculation),
            'raw' => $calculation,
        ]);
    }
}
