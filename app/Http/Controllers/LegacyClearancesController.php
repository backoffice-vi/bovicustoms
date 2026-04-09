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

        $recentLegacyShipments = Shipment::where('source_type', 'legacy')
            ->with(['country', 'invoices', 'declarationForms'])
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        $invoiceItemResults = collect();
        $declarationItemResults = collect();
        $searchMatches = collect();

        if ($q !== '' || $hs !== '') {
            $words = $q !== '' ? preg_split('/\s+/', $q) : [];
            // Basic stemming: strip trailing 's' so "puffs" matches "PUFF"
            $stems = array_filter(array_map(fn($w) => rtrim(trim($w), 's'), $words), fn($w) => strlen($w) >= 2);

            if (!empty($stems)) {
                $invoiceItemResults = InvoiceItem::query()
                    ->with('invoice')
                    ->whereHas('invoice', fn($qry) => $qry->where('source_type', 'legacy'))
                    ->where(function ($query) use ($stems) {
                        foreach ($stems as $stem) {
                            $query->where('description', 'like', '%' . $stem . '%');
                        }
                    })
                    ->orderBy('created_at', 'desc')
                    ->take(50)
                    ->get();
            }

            $declarationItemResults = DeclarationFormItem::query()
                ->with('declarationForm')
                ->whereHas('declarationForm', fn($qry) => $qry->where('source_type', 'legacy'))
                ->when(!empty($stems), function ($query) use ($stems) {
                    $query->where(function ($sub) use ($stems) {
                        foreach ($stems as $stem) {
                            $sub->where('description', 'like', '%' . $stem . '%');
                        }
                    });
                })
                ->when($hs !== '', fn ($qq) => $qq->where('hs_code', 'like', '%' . $hs . '%'))
                ->orderBy('created_at', 'desc')
                ->take(50)
                ->get();

            // Compute relevance % for declaration items based on word overlap with search query
            if ($q !== '' && $declarationItemResults->isNotEmpty()) {
                $queryWords = array_map('strtolower', $stems);
                $declarationItemResults->each(function ($item) use ($queryWords) {
                    $desc = strtolower($item->description);
                    $descWords = preg_split('/[\s,_\-\/]+/', $desc);
                    $descWords = array_filter($descWords, fn($w) => strlen($w) >= 2);

                    $matchedQueryWords = 0;
                    foreach ($queryWords as $qw) {
                        if (str_contains($desc, $qw)) {
                            $matchedQueryWords++;
                        }
                    }

                    $matchedDescWords = 0;
                    foreach ($descWords as $dw) {
                        foreach ($queryWords as $qw) {
                            if (str_contains($dw, $qw) || str_contains($qw, $dw)) {
                                $matchedDescWords++;
                                break;
                            }
                        }
                    }

                    $totalDescWords = max(count($descWords), 1);
                    $queryScore = count($queryWords) > 0 ? ($matchedQueryWords / count($queryWords)) : 0;
                    $descScore = $matchedDescWords / $totalDescWords;

                    // Weighted: 60% how many query words matched, 40% how much of the description matched
                    $item->relevance = (int) round(($queryScore * 60) + ($descScore * 40));
                });

                $declarationItemResults = $declarationItemResults->sortByDesc('relevance')->values();
            }

            // Build matches: for each invoice item, find its matched declaration item
            if ($invoiceItemResults->isNotEmpty()) {
                $searchMatches = InvoiceDeclarationMatch::with('declarationFormItem')
                    ->whereIn('invoice_item_id', $invoiceItemResults->pluck('id'))
                    ->get()
                    ->keyBy('invoice_item_id');
            }
        }

        return view('legacy-clearances.index', compact(
            'countries',
            'recentLegacyShipments',
            'q',
            'hs',
            'invoiceItemResults',
            'declarationItemResults',
            'searchMatches'
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
            'declarationForms.declarationItems',
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
