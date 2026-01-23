<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\DeclarationForm;
use App\Models\DeclarationFormItem;
use App\Models\CustomsCode;
use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\InvoiceDocumentExtractor;
use App\Services\ItemClassifier;
use Illuminate\Support\Facades\Log;

class InvoiceController extends Controller
{
    protected InvoiceDocumentExtractor $invoiceExtractor;
    protected ItemClassifier $itemClassifier;

    public function __construct(InvoiceDocumentExtractor $invoiceExtractor, ItemClassifier $itemClassifier)
    {
        $this->invoiceExtractor = $invoiceExtractor;
        $this->itemClassifier = $itemClassifier;
    }

    public function index()
    {
        $invoices = Invoice::with('country')
            ->latest()
            ->paginate(10);

        return view('invoices.index', compact('invoices'));
    }

    public function create()
    {
        $countries = Country::active()->orderBy('name')->get();
        $user = auth()->user();
        
        // Check usage limits
        $monthStart = now()->startOfMonth();
        if ($user->organization_id) {
            $used = $user->organization->invoices()->where('created_at', '>=', $monthStart)->count();
            $limit = $user->organization->invoice_limit;
        } else {
            $used = $user->invoices()->where('created_at', '>=', $monthStart)->count();
            $limit = 10; // Free tier
        }
        
        if ($limit && $used >= $limit) {
            return redirect()->route('subscription.index')
                ->with('error', 'You have reached your monthly invoice limit. Please upgrade your plan.');
        }
        
        return view('invoices.create', compact('countries', 'used', 'limit'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'invoice_file' => 'required|file|mimes:pdf,jpg,jpeg,png,tiff,xls,xlsx|max:10240',
            'country_id' => 'required|exists:countries,id',
        ]);

        $file = $request->file('invoice_file');
        
        if (!$file->isValid()) {
            return back()->with('error', 'Failed to upload invoice file.');
        }

        try {
            // Extract invoice data using AI-powered extractor
            $extractedData = $this->invoiceExtractor->extract($file);
            
            // Check for extraction errors
            if (isset($extractedData['extraction_meta']['error'])) {
                return back()->with('error', $extractedData['extraction_meta']['error']);
            }
            
            // Check if any items were extracted
            if (empty($extractedData['items'])) {
                return back()->with('error', 'No line items could be extracted from the invoice. Please try a different file format or ensure the invoice contains readable item data.');
            }

            // Store the file
            $path = $file->store('invoices');

            // Store the extracted data, path, and country in the session for review
            session([
                'extracted_invoice_data' => $extractedData,
                'invoice_file_path' => $path,
                'invoice_country_id' => $request->country_id,
            ]);

            return redirect()->route('invoices.review');
            
        } catch (\Exception $e) {
            Log::error('Invoice extraction failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return back()->with('error', 'Failed to extract invoice data: ' . $e->getMessage());
        }
    }

    public function review()
    {
        $extractedData = session('extracted_invoice_data');
        if (!$extractedData) {
            return redirect()->route('invoices.create')->with('error', 'No invoice data found. Please upload an invoice first.');
        }

        $countryId = session('invoice_country_id');
        $country = Country::find($countryId);

        return view('invoices.review', compact('extractedData', 'country'));
    }

    public function confirm(Request $request)
    {
        $validatedData = $request->validate([
            'invoice_number' => 'nullable|string|max:100',
            'invoice_date' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:500',
            'items.*.quantity' => 'nullable|numeric|min:0',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.sku' => 'nullable|string|max:100',
            'items.*.item_number' => 'nullable|string|max:100',
        ]);

        $countryId = session('invoice_country_id');
        
        if (!$countryId) {
            return redirect()->route('invoices.create')->with('error', 'Session expired. Please upload the invoice again.');
        }

        // Classify each item using the AI classifier
        $itemsWithClassifications = [];
        
        foreach ($validatedData['items'] as $index => $item) {
            $description = $item['description'];
            
            // Run classification
            $classificationResult = $this->itemClassifier->classify($description, $countryId);
            
            // Find historical precedents for comparison
            $precedents = $this->findPrecedentsForItem($description, $countryId);
            
            $itemsWithClassifications[] = [
                // Original item data
                'description' => $description,
                'quantity' => $item['quantity'] ?? null,
                'unit_price' => $item['unit_price'] ?? null,
                'sku' => $item['sku'] ?? null,
                'item_number' => $item['item_number'] ?? null,
                'line_number' => $index + 1,
                
                // Classification result
                'classification' => $classificationResult,
                
                // Historical precedents
                'precedents' => $precedents,
                
                // Determine if there's a conflict
                'has_conflict' => $this->hasConflict($classificationResult, $precedents),
            ];
        }

        // Store invoice header info
        $invoiceHeader = [
            'invoice_number' => $validatedData['invoice_number'] ?? session('extracted_invoice_data.invoice_number'),
            'invoice_date' => $validatedData['invoice_date'] ?? session('extracted_invoice_data.invoice_date'),
            'total_amount' => session('extracted_invoice_data.total_amount'),
            'currency' => session('extracted_invoice_data.currency'),
        ];

        // Store the classified items in the session
        session([
            'items_with_codes' => $itemsWithClassifications,
            'invoice_header' => $invoiceHeader,
        ]);

        return redirect()->route('invoices.assign_codes');
    }

    public function assignCodes()
    {
        $itemsWithCodes = session('items_with_codes');
        if (!$itemsWithCodes) {
            return redirect()->route('invoices.create')->with('error', 'No invoice data found. Please upload an invoice first.');
        }

        $invoiceHeader = session('invoice_header', []);
        $countryId = session('invoice_country_id');
        $country = Country::find($countryId);

        return view('invoices.assign_codes', compact('itemsWithCodes', 'invoiceHeader', 'country'));
    }

    public function finalize(Request $request)
    {
        $validatedData = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'nullable|numeric',
            'items.*.unit_price' => 'nullable|numeric',
            'items.*.customs_code' => 'required|string',
            'items.*.sku' => 'nullable|string',
            'items.*.item_number' => 'nullable|string',
            'items.*.line_number' => 'nullable|integer',
        ]);

        $user = auth()->user();
        $countryId = session('invoice_country_id');
        $invoiceHeader = session('invoice_header', []);
        $filePath = session('invoice_file_path');
        $extractedData = session('extracted_invoice_data', []);

        if (!$countryId) {
            return redirect()->route('invoices.create')->with('error', 'Session expired. Please upload the invoice again.');
        }

        // Calculate total amount
        $totalAmount = collect($validatedData['items'])->sum(function ($item) {
            $qty = $item['quantity'] ?? 1;
            $price = $item['unit_price'] ?? 0;
            return $qty * $price;
        });

        // Create the invoice
        $invoice = Invoice::create([
            'organization_id' => $user->organization_id,
            'country_id' => $countryId,
            'user_id' => $user->id,
            'invoice_number' => $invoiceHeader['invoice_number'] ?? ('INV-' . now()->format('Ymd') . '-' . rand(1000, 9999)),
            'invoice_date' => $invoiceHeader['invoice_date'] ?? now(),
            'total_amount' => $totalAmount ?: ($invoiceHeader['total_amount'] ?? 0),
            'status' => 'processed',
            'processed' => true,
            'items' => $validatedData['items'],
            'source_type' => 'uploaded',
            'source_file_path' => $filePath,
            'extracted_text' => $extractedData['extracted_text'] ?? null,
            'extraction_meta' => $extractedData['extraction_meta'] ?? null,
        ]);

        // Create individual InvoiceItem records
        foreach ($validatedData['items'] as $index => $item) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'organization_id' => $user->organization_id,
                'user_id' => $user->id,
                'country_id' => $countryId,
                'line_number' => $item['line_number'] ?? ($index + 1),
                'sku' => $item['sku'] ?? null,
                'item_number' => $item['item_number'] ?? null,
                'description' => $item['description'],
                'quantity' => $item['quantity'] ?? null,
                'unit_price' => $item['unit_price'] ?? null,
                'line_total' => ($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0),
                'currency' => $invoiceHeader['currency'] ?? 'USD',
                'customs_code' => $item['customs_code'],
            ]);
        }

        // Create the Declaration Form
        $declarationForm = DeclarationForm::create([
            'organization_id' => $user->organization_id,
            'country_id' => $countryId,
            'invoice_id' => $invoice->id,
            'form_number' => 'DF-' . now()->format('Ymd') . '-' . str_pad($invoice->id, 4, '0', STR_PAD_LEFT),
            'declaration_date' => now(),
            'total_duty' => 0, // Will be calculated when duty rates are applied
            'items' => $validatedData['items'],
            'source_type' => 'invoice',
            'source_file_path' => $filePath,
            'extracted_text' => $extractedData['extracted_text'] ?? null,
            'extraction_meta' => $extractedData['extraction_meta'] ?? null,
        ]);

        // Create Declaration Form Items (for historical precedent tracking)
        foreach ($validatedData['items'] as $index => $item) {
            DeclarationFormItem::create([
                'declaration_form_id' => $declarationForm->id,
                'invoice_id' => $invoice->id,
                'organization_id' => $user->organization_id,
                'user_id' => $user->id,
                'country_id' => $countryId,
                'line_number' => $item['line_number'] ?? ($index + 1),
                'sku' => $item['sku'] ?? null,
                'item_number' => $item['item_number'] ?? null,
                'description' => $item['description'],
                'quantity' => $item['quantity'] ?? null,
                'unit_price' => $item['unit_price'] ?? null,
                'line_total' => ($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0),
                'currency' => $invoiceHeader['currency'] ?? 'USD',
                'hs_code' => $item['customs_code'],
                'hs_description' => null, // Can be populated from customs_codes table if needed
            ]);
        }

        // Clear the session data
        session()->forget([
            'extracted_invoice_data',
            'items_with_codes',
            'invoice_country_id',
            'invoice_header',
            'invoice_file_path',
        ]);

        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice processed successfully with ' . count($validatedData['items']) . ' items. Declaration form generated.');
    }

    public function show(Invoice $invoice)
    {
        // Load invoice items from the database
        $items = InvoiceItem::where('invoice_id', $invoice->id)
            ->orderBy('line_number')
            ->get();

        return view('invoices.show', compact('invoice', 'items'));
    }

    /**
     * Find historical precedents for an item description
     */
    protected function findPrecedentsForItem(string $description, int $countryId): array
    {
        $user = auth()->user();
        
        // Extract keywords from description
        $keywords = $this->extractKeywords($description);
        
        if (empty($keywords)) {
            return [];
        }

        // Search in declaration form items for similar descriptions
        $query = DeclarationFormItem::query()
            ->where('country_id', $countryId)
            ->whereNotNull('hs_code');

        // Apply tenant scope
        if ($user->organization_id) {
            $query->where('organization_id', $user->organization_id);
        } elseif ($user->is_individual) {
            $query->where('user_id', $user->id);
        }

        $candidates = $query->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        // Score and rank by keyword matches
        $scored = [];
        foreach ($candidates as $candidate) {
            $candidateDesc = strtolower((string) $candidate->description);
            $hits = 0;
            
            foreach ($keywords as $keyword) {
                if ($keyword && str_contains($candidateDesc, strtolower($keyword))) {
                    $hits++;
                }
            }

            if ($hits > 0) {
                $scored[] = [
                    'score' => $hits,
                    'hs_code' => (string) $candidate->hs_code,
                    'description' => (string) $candidate->description,
                    'hs_description' => $candidate->hs_description,
                    'created_at' => $candidate->created_at->format('Y-m-d'),
                ];
            }
        }

        // Sort by score descending
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        // Return top 5 unique codes
        $seen = [];
        $results = [];
        foreach ($scored as $item) {
            if (!isset($seen[$item['hs_code']])) {
                $seen[$item['hs_code']] = true;
                $results[] = $item;
                if (count($results) >= 5) {
                    break;
                }
            }
        }

        return $results;
    }

    /**
     * Extract keywords from a description
     */
    protected function extractKeywords(string $description): array
    {
        $stopWords = [
            'the', 'of', 'and', 'or', 'a', 'an', 'in', 'to', 'for', 'with',
            'as', 'at', 'by', 'on', 'is', 'are', 'was', 'were', 'be', 'been',
            'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would',
            'could', 'should', 'may', 'might', 'must', 'shall', 'can', 'need',
            'this', 'that', 'these', 'those', 'it', 'its', 'new', 'used', 'other'
        ];

        preg_match_all('/\b([a-z]{3,})\b/i', strtolower($description), $matches);
        $words = array_diff($matches[1] ?? [], $stopWords);
        $words = array_unique($words);

        return array_values(array_slice($words, 0, 10));
    }

    /**
     * Check if there's a conflict between AI classification and historical precedents
     */
    protected function hasConflict(array $classification, array $precedents): bool
    {
        if (!$classification['success'] || empty($precedents)) {
            return false;
        }

        $aiCode = $classification['code'] ?? null;
        if (!$aiCode) {
            return false;
        }

        // Check if the top precedent has a different code
        $topPrecedentCode = $precedents[0]['hs_code'] ?? null;
        
        if (!$topPrecedentCode) {
            return false;
        }

        // Compare first 6 digits (subheading level)
        $aiCodeClean = preg_replace('/[^0-9]/', '', $aiCode);
        $precedentClean = preg_replace('/[^0-9]/', '', $topPrecedentCode);

        return substr($aiCodeClean, 0, 6) !== substr($precedentClean, 0, 6);
    }

    /**
     * Search customs codes for manual override lookup
     */
    public function searchCustomsCodes(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:100',
            'country_id' => 'nullable|integer|exists:countries,id',
        ]);

        $query = $request->input('q');
        $countryId = $request->input('country_id');

        $codesQuery = CustomsCode::query();

        // Filter by country if provided
        if ($countryId) {
            $codesQuery->where('country_id', $countryId);
        }

        // Search by code or description
        $codesQuery->where(function ($q) use ($query) {
            $q->where('code', 'like', "%{$query}%")
              ->orWhere('description', 'like', "%{$query}%");
        });

        // Limit results and order by relevance
        $codes = $codesQuery
            ->orderByRaw("CASE WHEN code LIKE ? THEN 0 ELSE 1 END", ["{$query}%"])
            ->orderBy('code')
            ->limit(20)
            ->get(['id', 'code', 'description', 'duty_rate', 'unit_of_measurement']);

        return response()->json([
            'success' => true,
            'codes' => $codes->map(function ($code) {
                return [
                    'id' => $code->id,
                    'code' => $code->code,
                    'description' => $code->description,
                    'duty_rate' => $code->duty_rate,
                    'unit' => $code->unit_of_measurement,
                ];
            }),
        ]);
    }
}
