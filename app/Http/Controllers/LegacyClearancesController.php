<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\DeclarationForm;
use App\Models\DeclarationFormItem;
use App\Models\Invoice;
use App\Models\InvoiceDeclarationMatch;
use App\Models\InvoiceItem;
use App\Models\Shipment;
use App\Services\HistoricalImportService;
use Illuminate\Http\Request;

class LegacyClearancesController extends Controller
{
    public function __construct(protected HistoricalImportService $importer)
    {
    }

    public function index(Request $request)
    {
        $countries = Country::active()->orderBy('name')->get();

        $q = trim((string) $request->get('q', ''));
        $hs = trim((string) $request->get('hs_code', ''));

        // Get recent legacy shipments (imported clearances)
        $recentLegacyShipments = Shipment::where('source_type', 'legacy')
            ->with(['country', 'invoices', 'declarationForms'])
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        $invoiceItemResults = collect();
        $declarationItemResults = collect();

        if ($q !== '' || $hs !== '') {
            if ($q !== '') {
                $invoiceItemResults = InvoiceItem::query()
                    ->whereHas('invoice', fn($qry) => $qry->where('source_type', 'legacy'))
                    ->where('description', 'like', '%' . $q . '%')
                    ->orderBy('created_at', 'desc')
                    ->take(25)
                    ->get();
            }

            $declarationItemResults = DeclarationFormItem::query()
                ->whereHas('declarationForm', fn($qry) => $qry->where('source_type', 'legacy'))
                ->when($q !== '', fn ($qq) => $qq->where('description', 'like', '%' . $q . '%'))
                ->when($hs !== '', fn ($qq) => $qq->where('hs_code', 'like', '%' . $hs . '%'))
                ->orderBy('created_at', 'desc')
                ->take(25)
                ->get();
        }

        return view('legacy-clearances.index', compact(
            'countries',
            'recentLegacyShipments',
            'q',
            'hs',
            'invoiceItemResults',
            'declarationItemResults'
        ));
    }

    public function uploadClearance(Request $request)
    {
        $validated = $request->validate([
            'country_id' => 'required|exists:countries,id',
            'invoice_files' => 'required|array|min:1',
            'invoice_files.*' => 'file|max:20480', // 20MB each
            'shipping_document' => 'nullable|file|max:20480',
            'declaration_file' => 'required|file|max:20480',
        ]);

        $user = $request->user();
        
        $result = $this->importer->importLegacyClearance(
            $user,
            (int) $validated['country_id'],
            $request->file('invoice_files'),
            $request->file('shipping_document'),
            $request->file('declaration_file')
        );

        if (!$result['success']) {
            return back()->withInput()->with('error', $result['error'] ?? 'Legacy clearance import failed');
        }

        return redirect()->route('legacy-clearances.shipments.show', $result['shipment'])
            ->with('success', 'Legacy clearance imported successfully. ' . ($result['items_matched'] ?? 0) . ' items matched.');
    }

    public function showShipment(Shipment $shipment)
    {
        $shipment->load([
            'country',
            'invoices.invoiceItems',
            'shippingDocuments',
            'declarationForms.declarationFormItems',
        ]);

        // Get all invoice items for matching display
        $invoiceItems = $shipment->invoices->flatMap(fn($inv) => $inv->invoiceItems);
        
        // Get matches for these invoice items
        $matches = InvoiceDeclarationMatch::with('declarationFormItem')
            ->whereIn('invoice_item_id', $invoiceItems->pluck('id')->all())
            ->get()
            ->keyBy('invoice_item_id');

        return view('legacy-clearances.shipment-show', compact('shipment', 'invoiceItems', 'matches'));
    }

    public function showInvoice(Invoice $invoice)
    {
        $items = InvoiceItem::where('invoice_id', $invoice->id)->orderBy('line_number')->get();

        $matches = InvoiceDeclarationMatch::with('declarationFormItem')
            ->whereIn('invoice_item_id', $items->pluck('id')->all())
            ->get()
            ->keyBy('invoice_item_id');

        return view('legacy-clearances.invoice-show', compact('invoice', 'items', 'matches'));
    }

    public function showDeclaration(DeclarationForm $declarationForm)
    {
        $items = DeclarationFormItem::where('declaration_form_id', $declarationForm->id)
            ->orderBy('line_number')
            ->get();

        return view('legacy-clearances.declaration-show', compact('declarationForm', 'items'));
    }
}
