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
use App\Jobs\ClassifyInvoiceItems;
use App\Console\Commands\EnsureQueueWorker;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

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
        
        if ($user->isAdmin()) {
            $used = 0;
            $limit = null;
        } elseif ($user->organization_id) {
            $monthStart = now()->startOfMonth();
            $used = $user->organization->invoices()->where('created_at', '>=', $monthStart)->count();
            $limit = $user->organization->invoice_limit;
        } else {
            $monthStart = now()->startOfMonth();
            $used = $user->invoices()->where('created_at', '>=', $monthStart)->count();
            $limit = 10;
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
        $isAjax = $request->ajax() || $request->wantsJson();
        
        if (!$file->isValid()) {
            $msg = 'Failed to upload invoice file.';
            return $isAjax
                ? response()->json(['error' => $msg], 422)
                : back()->with('error', $msg);
        }

        try {
            $extractedData = $this->invoiceExtractor->extract($file);
            
            if (isset($extractedData['extraction_meta']['error'])) {
                $msg = $extractedData['extraction_meta']['error'];
                return $isAjax
                    ? response()->json(['error' => $msg], 422)
                    : back()->with('error', $msg);
            }
            
            if (empty($extractedData['items'])) {
                $msg = 'No line items could be extracted from the invoice. Please try a different file format or ensure the invoice contains readable item data.';
                return $isAjax
                    ? response()->json(['error' => $msg], 422)
                    : back()->with('error', $msg);
            }

            $path = $file->store('invoices');

            session([
                'extracted_invoice_data' => $extractedData,
                'invoice_file_path' => $path,
                'invoice_country_id' => $request->country_id,
            ]);

            $reviewUrl = route('invoices.review');
            return $isAjax
                ? response()->json(['redirect' => $reviewUrl])
                : redirect($reviewUrl);
            
        } catch (\Exception $e) {
            Log::error('Invoice extraction failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            $msg = 'Failed to extract invoice data. Please try again or use a different file format.';
            return $isAjax
                ? response()->json(['error' => $msg], 500)
                : back()->with('error', $msg);
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
        $filePath = session('invoice_file_path');
        $extractedData = session('extracted_invoice_data', []);
        
        if (!$countryId) {
            return redirect()->route('invoices.create')->with('error', 'Session expired. Please upload the invoice again.');
        }

        $user = auth()->user();

        // Store invoice header info
        $invoiceHeader = [
            'invoice_number' => $validatedData['invoice_number'] ?? session('extracted_invoice_data.invoice_number'),
            'invoice_date' => $validatedData['invoice_date'] ?? session('extracted_invoice_data.invoice_date'),
            'total_amount' => session('extracted_invoice_data.total_amount'),
            'currency' => session('extracted_invoice_data.currency'),
        ];

        // Calculate total amount
        $totalAmount = collect($validatedData['items'])->sum(function ($item) {
            $qty = $item['quantity'] ?? 1;
            $price = $item['unit_price'] ?? 0;
            return $qty * $price;
        });

        // Generate invoice number
        $invoiceNumber = $invoiceHeader['invoice_number'] ?? ('INV-' . now()->format('Ymd') . '-' . rand(1000, 9999));

        // Check if an invoice with this number already exists for this user/organization
        $existingInvoice = Invoice::withoutGlobalScopes()
            ->where('invoice_number', $invoiceNumber)
            ->where(function ($query) use ($user) {
                if ($user->organization_id) {
                    $query->where('organization_id', $user->organization_id);
                } else {
                    $query->where('user_id', $user->id);
                }
            })
            ->first();

        if ($existingInvoice) {
            // If the existing invoice is still in classifying or draft status, update it
            if (in_array($existingInvoice->status, ['classifying', 'draft', 'pending'])) {
                $existingInvoice->update([
                    'country_id' => $countryId,
                    'invoice_date' => $invoiceHeader['invoice_date'] ?? now(),
                    'total_amount' => $totalAmount ?: ($invoiceHeader['total_amount'] ?? 0),
                    'status' => 'classifying',
                    'processed' => false,
                    'items' => $validatedData['items'],
                    'source_file_path' => $filePath,
                    'extracted_text' => $extractedData['extracted_text'] ?? null,
                    'extraction_meta' => $extractedData['extraction_meta'] ?? null,
                ]);
                $invoice = $existingInvoice;
            } else {
                // Invoice already processed, create a new one with a suffix
                $suffix = 1;
                $baseNumber = $invoiceNumber;
                while (Invoice::withoutGlobalScopes()
                    ->where('invoice_number', $invoiceNumber)
                    ->where(function ($query) use ($user) {
                        if ($user->organization_id) {
                            $query->where('organization_id', $user->organization_id);
                        } else {
                            $query->where('user_id', $user->id);
                        }
                    })
                    ->exists()) {
                    $invoiceNumber = $baseNumber . '-' . $suffix;
                    $suffix++;
                }
                
                $invoice = Invoice::create([
                    'organization_id' => $user->organization_id,
                    'country_id' => $countryId,
                    'user_id' => $user->id,
                    'invoice_number' => $invoiceNumber,
                    'invoice_date' => $invoiceHeader['invoice_date'] ?? now(),
                    'total_amount' => $totalAmount ?: ($invoiceHeader['total_amount'] ?? 0),
                    'status' => 'classifying',
                    'processed' => false,
                    'items' => $validatedData['items'],
                    'source_type' => 'uploaded',
                    'source_file_path' => $filePath,
                    'extracted_text' => $extractedData['extracted_text'] ?? null,
                    'extraction_meta' => $extractedData['extraction_meta'] ?? null,
                ]);
            }
        } else {
            // Create a new invoice
            $invoice = Invoice::create([
                'organization_id' => $user->organization_id,
                'country_id' => $countryId,
                'user_id' => $user->id,
                'invoice_number' => $invoiceNumber,
                'invoice_date' => $invoiceHeader['invoice_date'] ?? now(),
                'total_amount' => $totalAmount ?: ($invoiceHeader['total_amount'] ?? 0),
                'status' => 'classifying',
                'processed' => false,
                'items' => $validatedData['items'],
                'source_type' => 'uploaded',
                'source_file_path' => $filePath,
                'extracted_text' => $extractedData['extracted_text'] ?? null,
                'extraction_meta' => $extractedData['extraction_meta'] ?? null,
            ]);
        }

        // Store the invoice ID in session for later retrieval
        session([
            'classifying_invoice_id' => $invoice->id,
            'invoice_country_id' => $countryId,
            'invoice_header' => $invoiceHeader,
        ]);

        // Initialize the classification status in cache
        Cache::put("invoice_classification_{$invoice->id}", [
            'status' => 'queued',
            'progress' => 0,
            'message' => 'Classification job queued...',
            'started_at' => now()->toIso8601String(),
        ], now()->addHours(2));

        // Store classification data in session for the AJAX trigger
        session([
            'pending_classification' => [
                'invoice_id' => $invoice->id,
                'items' => $validatedData['items'],
                'country_id' => $countryId,
                'invoice_header' => $invoiceHeader,
            ]
        ]);

        Log::info('Invoice ready for classification', [
            'invoice_id' => $invoice->id,
            'item_count' => count($validatedData['items']),
        ]);

        // Always show status page first - classification triggered via AJAX
        return redirect()->route('invoices.classification_status', ['invoice' => $invoice->id]);
    }

    /**
     * Show the classification status page with progress updates.
     */
    public function classificationStatus(Invoice $invoice)
    {
        $user = auth()->user();
        if ($invoice->user_id !== $user->id && $invoice->organization_id !== $user->organization_id) {
            abort(403, 'Unauthorized access to this invoice.');
        }

        $countryId = session('invoice_country_id') ?? $invoice->country_id;
        $country = Country::find($countryId);

        // Determine the actual number of items being classified (not total invoice items)
        $pending = session('pending_classification');
        $classifyingItemCount = $pending ? count($pending['items']) : null;
        $totalItemCount = count($invoice->items ?? []);

        // If no pending data, check if InvoiceItem records exist to infer
        if (!$classifyingItemCount) {
            $unclassifiedCount = InvoiceItem::withoutGlobalScopes()
                ->where('invoice_id', $invoice->id)
                ->whereNull('customs_code')
                ->count();
            $classifyingItemCount = $unclassifiedCount > 0 ? $unclassifiedCount : $totalItemCount;
        }

        return view('invoices.classification_status', compact(
            'invoice', 'country', 'classifyingItemCount', 'totalItemCount'
        ));
    }

    /**
     * API endpoint to get classification progress.
     */
    public function classificationProgress(Invoice $invoice): JsonResponse
    {
        // Verify the user owns this invoice
        $user = auth()->user();
        if ($invoice->user_id !== $user->id && $invoice->organization_id !== $user->organization_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $cacheKey = "invoice_classification_{$invoice->id}";
        $status = Cache::get($cacheKey);

        if (!$status) {
            return response()->json([
                'status' => 'unknown',
                'progress' => 0,
                'message' => 'Classification status not found. It may have expired.',
            ]);
        }

        return response()->json($status);
    }

    /**
     * AJAX endpoint to start classification (allows showing loading page first).
     * Always spawns a background artisan process so the browser can poll for progress.
     */
    public function startClassification(Invoice $invoice): JsonResponse
    {
        $user = auth()->user();
        if ($invoice->user_id !== $user->id && $invoice->organization_id !== $user->organization_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Always check cache first to prevent re-triggering a completed/running classification
        $cacheKey = "invoice_classification_{$invoice->id}";
        $existingStatus = Cache::get($cacheKey);
        if ($existingStatus && $existingStatus['status'] === 'completed') {
            session()->forget('pending_classification');
            return response()->json(['status' => 'already_completed']);
        }
        if ($existingStatus && $existingStatus['status'] === 'processing') {
            return response()->json(['status' => 'started']);
        }

        $pending = session('pending_classification');
        if (!$pending || $pending['invoice_id'] !== $invoice->id) {
            return response()->json(['error' => 'No pending classification found'], 400);
        }

        session()->forget('pending_classification');

        $cacheKey = "invoice_classification_{$invoice->id}";
        Cache::put($cacheKey, [
            'status' => 'processing',
            'progress' => 0,
            'message' => 'Starting classification...',
            'started_at' => now()->toIso8601String(),
        ], now()->addHours(2));

        // Store the classification payload in cache so the artisan command can read it
        Cache::put("pending_classification_{$invoice->id}", $pending, now()->addHours(2));

        // Spawn background artisan process — returns immediately so the browser can poll
        $php = PHP_BINARY ?: 'php';
        $artisan = base_path('artisan');
        $cmd = "\"{$php}\" \"{$artisan}\" classify:invoice {$invoice->id} {$user->id} {$pending['country_id']}";

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            pclose(popen('start "" /B ' . $cmd . ' > NUL 2>&1', 'r'));
        } else {
            pclose(popen("{$cmd} > /dev/null 2>&1 &", 'r'));
        }

        Log::info('Classification started via background process', [
            'invoice_id' => $invoice->id,
            'item_count' => count($pending['items']),
            'command' => $cmd,
        ]);

        return response()->json(['status' => 'started']);
    }

    /**
     * Show the classification results after background processing completes.
     */
    public function assignCodesResults(Invoice $invoice)
    {
        $user = auth()->user();
        if ($invoice->user_id !== $user->id && $invoice->organization_id !== $user->organization_id) {
            abort(403, 'Unauthorized access to this invoice.');
        }

        $cacheKey = "invoice_classification_{$invoice->id}";
        $classificationData = Cache::get($cacheKey);

        if ($classificationData && $classificationData['status'] === 'completed') {
            $itemsWithCodes = $classificationData['items_with_codes'];
            $invoiceHeader = $classificationData['invoice_header'];
            $countryId = $classificationData['country_id'];
        } else {
            // Cache expired — rebuild from database records (codes already auto-saved)
            $dbItems = InvoiceItem::withoutGlobalScopes()
                ->where('invoice_id', $invoice->id)
                ->orderBy('line_number')
                ->get();

            if ($dbItems->isEmpty()) {
                return redirect()->route('invoices.show', $invoice)
                    ->with('info', 'No classification data available. View the invoice directly.');
            }

            $countryId = $invoice->country_id;
            $itemsWithCodes = $dbItems->map(fn($item) => [
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'sku' => $item->sku,
                'item_number' => $item->item_number,
                'line_number' => $item->line_number,
                'classification' => [
                    'success' => !empty($item->customs_code),
                    'code' => $item->customs_code,
                    'description' => $item->customs_code_description,
                    'duty_rate' => $item->duty_rate,
                    'confidence' => !empty($item->customs_code) ? 75 : 0,
                    'explanation' => 'Classification from auto-save (cache expired)',
                    'alternatives' => [],
                ],
                'precedents' => $this->findPrecedentsForItem($item->description, $countryId),
                'has_conflict' => false,
            ])->toArray();

            $invoiceHeader = [
                'invoice_number' => $invoice->invoice_number,
                'invoice_date' => $invoice->invoice_date,
                'total_amount' => $invoice->total_amount,
            ];
        }

        $country = Country::find($countryId);

        session([
            'items_with_codes' => $itemsWithCodes,
            'invoice_header' => $invoiceHeader,
            'invoice_country_id' => $countryId,
            'classifying_invoice_id' => $invoice->id,
        ]);

        return view('invoices.assign_codes', compact('itemsWithCodes', 'invoiceHeader', 'country', 'invoice'));
    }

    public function assignCodes()
    {
        // Check if there's an invoice being classified in the background
        $invoiceId = session('classifying_invoice_id');
        if ($invoiceId) {
            $invoice = Invoice::find($invoiceId);
            if ($invoice && $invoice->status === 'classifying') {
                return redirect()->route('invoices.classification_status', ['invoice' => $invoice->id]);
            }
        }

        $itemsWithCodes = session('items_with_codes');
        if (!$itemsWithCodes) {
            return redirect()->route('invoices.create')->with('error', 'No invoice data found. Please upload an invoice first.');
        }

        $invoiceHeader = session('invoice_header', []);
        $countryId = session('invoice_country_id');
        $country = Country::find($countryId);
        $invoice = $invoiceId ? Invoice::find($invoiceId) : null;

        return view('invoices.assign_codes', compact('itemsWithCodes', 'invoiceHeader', 'country', 'invoice'));
    }

    public function finalize(Request $request)
    {
        $validatedData = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'nullable|numeric',
            'items.*.unit_price' => 'nullable|numeric',
            'items.*.customs_code' => 'required|string',
            'items.*.duty_rate' => 'nullable|numeric',
            'items.*.customs_code_description' => 'nullable|string',
            'items.*.sku' => 'nullable|string',
            'items.*.item_number' => 'nullable|string',
            'items.*.line_number' => 'nullable|integer',
        ]);

        $user = auth()->user();
        $countryId = session('invoice_country_id');
        $invoiceHeader = session('invoice_header', []);
        $existingInvoiceId = session('classifying_invoice_id');

        if (!$countryId) {
            return redirect()->route('invoices.create')->with('error', 'Session expired. Please upload the invoice again.');
        }

        // Calculate total amount
        $totalAmount = collect($validatedData['items'])->sum(function ($item) {
            $qty = $item['quantity'] ?? 1;
            $price = $item['unit_price'] ?? 0;
            return $qty * $price;
        });

        // Check if we have an existing invoice from background processing
        if ($existingInvoiceId) {
            $invoice = Invoice::find($existingInvoiceId);
            if ($invoice) {
                $invoice->update([
                    'status' => 'processed',
                    'processed' => true,
                    'items' => $validatedData['items'],
                    'total_amount' => $totalAmount ?: $invoice->total_amount,
                ]);

                Cache::forget("invoice_classification_{$invoice->id}");
            }
        }

        if (!isset($invoice) || !$invoice) {
            $filePath = session('invoice_file_path');
            $extractedData = session('extracted_invoice_data', []);

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
        }

        // Update existing InvoiceItem records with finalized codes from the review page
        $existingItems = InvoiceItem::where('invoice_id', $invoice->id)
            ->get()
            ->keyBy('line_number');

        foreach ($validatedData['items'] as $index => $item) {
            $lineNumber = $item['line_number'] ?? ($index + 1);
            $existing = $existingItems->get($lineNumber);

            if ($existing) {
                $existing->update([
                    'customs_code' => $item['customs_code'],
                    'duty_rate' => $item['duty_rate'] ?? null,
                    'customs_code_description' => $item['customs_code_description'] ?? null,
                ]);
            } else {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'organization_id' => $user->organization_id,
                    'user_id' => $user->id,
                    'country_id' => $countryId,
                    'line_number' => $lineNumber,
                    'sku' => $item['sku'] ?? null,
                    'item_number' => $item['item_number'] ?? null,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'] ?? null,
                    'unit_price' => $item['unit_price'] ?? null,
                    'line_total' => ($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0),
                    'currency' => $invoiceHeader['currency'] ?? 'USD',
                    'customs_code' => $item['customs_code'],
                    'duty_rate' => $item['duty_rate'] ?? null,
                    'customs_code_description' => $item['customs_code_description'] ?? null,
                ]);
            }
        }

        // Delete any existing declaration forms for this invoice (if re-processing)
        DeclarationForm::where('invoice_id', $invoice->id)->delete();
        DeclarationFormItem::where('invoice_id', $invoice->id)->delete();

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
            'source_file_path' => $invoice->source_file_path,
            'extracted_text' => $invoice->extracted_text,
            'extraction_meta' => $invoice->extraction_meta,
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
                'hs_description' => $item['customs_code_description'] ?? null,
            ]);
        }

        // Clear the session data
        session()->forget([
            'extracted_invoice_data',
            'items_with_codes',
            'invoice_country_id',
            'invoice_header',
            'invoice_file_path',
            'classifying_invoice_id',
        ]);

        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice processed successfully with ' . count($validatedData['items']) . ' items. Declaration form generated.');
    }

    public function show(Invoice $invoice)
    {
        $items = InvoiceItem::where('invoice_id', $invoice->id)
            ->orderBy('line_number')
            ->get();

        // Enrich items with official HS code descriptions where missing
        if ($invoice->country_id && $items->isNotEmpty()) {
            $codes = $items->pluck('customs_code')->filter()->unique()->values()->toArray();
            if ($codes) {
                $codeDescriptions = CustomsCode::where('country_id', $invoice->country_id)
                    ->whereIn('code', $codes)
                    ->pluck('description', 'code');

                foreach ($items as $item) {
                    if ($item->customs_code && empty($item->customs_code_description) && isset($codeDescriptions[$item->customs_code])) {
                        $item->customs_code_description = $codeDescriptions[$item->customs_code];
                    }
                }
            }
        }

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
     * Retry classification for an invoice that failed or is stuck
     */
    public function retryClassification(Invoice $invoice)
    {
        $user = auth()->user();
        if ($invoice->user_id !== $user->id && $invoice->organization_id !== $user->organization_id) {
            abort(403, 'Unauthorized access to this invoice.');
        }

        $retryAll = request()->boolean('retry_all', false);

        // Build item list from InvoiceItem records (preferred) or invoice JSON
        $invoiceItems = InvoiceItem::withoutGlobalScopes()
            ->where('invoice_id', $invoice->id)
            ->orderBy('line_number')
            ->get();

        if ($invoiceItems->isNotEmpty()) {
            if ($retryAll) {
                $items = $invoiceItems->map(fn($item) => [
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'sku' => $item->sku,
                    'item_number' => $item->item_number,
                    'line_number' => $item->line_number,
                ])->toArray();
            } else {
                $items = $invoiceItems
                    ->filter(fn($item) => empty($item->customs_code))
                    ->map(fn($item) => [
                        'description' => $item->description,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'sku' => $item->sku,
                        'item_number' => $item->item_number,
                        'line_number' => $item->line_number,
                    ])->values()->toArray();
            }
        } else {
            $items = $invoice->items;
            if (empty($items)) {
                return redirect()->route('invoices.show', $invoice)
                    ->with('error', 'No items found to classify. Please re-upload the invoice.');
            }
        }

        if (empty($items)) {
            return redirect()->route('invoices.show', $invoice)
                ->with('info', 'All items already have HS codes assigned.');
        }

        $invoice->update(['status' => 'classifying']);

        Cache::forget("invoice_classification_{$invoice->id}");
        Cache::put("invoice_classification_{$invoice->id}", [
            'status' => 'queued',
            'progress' => 0,
            'message' => 'Classification job queued for retry...',
            'started_at' => now()->toIso8601String(),
        ], now()->addHours(2));

        session([
            'pending_classification' => [
                'invoice_id' => $invoice->id,
                'items' => $items,
                'country_id' => $invoice->country_id,
                'invoice_header' => [
                    'invoice_number' => $invoice->invoice_number,
                    'invoice_date' => $invoice->invoice_date,
                    'total_amount' => $invoice->total_amount,
                ],
                'retry_only_failed' => !$retryAll,
            ],
            'classifying_invoice_id' => $invoice->id,
            'invoice_country_id' => $invoice->country_id,
        ]);

        Log::info('Invoice classification retry requested', [
            'invoice_id' => $invoice->id,
            'item_count' => count($items),
            'retry_all' => $retryAll,
        ]);

        return redirect()->route('invoices.classification_status', ['invoice' => $invoice->id])
            ->with('info', 'Retrying classification for ' . count($items) . ' items...');
    }

    /**
     * Accept partial classification and mark invoice as processed
     */
    public function acceptClassification(Invoice $invoice)
    {
        $user = auth()->user();
        if ($invoice->user_id !== $user->id && $invoice->organization_id !== $user->organization_id) {
            abort(403, 'Unauthorized access to this invoice.');
        }

        $invoice->update(['status' => 'processed']);
        Cache::forget("invoice_classification_{$invoice->id}");

        return redirect()->route('invoices.show', $invoice)
            ->with('success', 'Invoice accepted. You can manually assign HS codes to remaining items.');
    }

    /**
     * AJAX: Update a single InvoiceItem's HS code
     */
    public function updateItemCode(InvoiceItem $invoiceItem): JsonResponse
    {
        $user = auth()->user();
        $invoice = Invoice::withoutGlobalScopes()->find($invoiceItem->invoice_id);

        if (!$invoice || ($invoice->user_id !== $user->id && $invoice->organization_id !== $user->organization_id)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $data = request()->validate([
            'customs_code' => 'required|string|max:20',
            'customs_code_description' => 'nullable|string|max:500',
        ]);

        $invoiceItem->update([
            'customs_code' => $data['customs_code'],
            'customs_code_description' => $data['customs_code_description'] ?? null,
        ]);

        // Also update the invoice JSON items array
        $items = is_array($invoice->items) ? $invoice->items : json_decode($invoice->items ?? '[]', true);
        foreach ($items as &$jsonItem) {
            if (($jsonItem['line_number'] ?? null) == $invoiceItem->line_number
                || ($jsonItem['description'] ?? '') === $invoiceItem->description) {
                $jsonItem['customs_code'] = $data['customs_code'];
                $jsonItem['customs_code_description'] = $data['customs_code_description'] ?? null;
                break;
            }
        }
        $invoice->update(['items' => $items]);

        // Check if all items now have codes
        $remaining = InvoiceItem::withoutGlobalScopes()
            ->where('invoice_id', $invoice->id)
            ->whereNull('customs_code')
            ->count();

        if ($remaining === 0 && $invoice->status === 'partially_classified') {
            $invoice->update(['status' => 'classified']);
        }

        return response()->json([
            'success' => true,
            'customs_code' => $data['customs_code'],
            'remaining_unclassified' => $remaining,
        ]);
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

    /**
     * Search classification memory for previously classified items
     * This helps reuse prior classifications for similar/identical products
     */
    public function searchClassificationMemory(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:200',
            'country_id' => 'nullable|integer|exists:countries,id',
        ]);

        $query = strtolower(trim($request->input('q')));
        $countryId = $request->input('country_id');

        // Search in invoice_items for previously classified items
        $itemsQuery = InvoiceItem::query()
            ->whereNotNull('customs_code')
            ->where('customs_code', '!=', '');

        // Filter by country if provided
        if ($countryId) {
            $itemsQuery->where('country_id', $countryId);
        }

        // Search by description (case-insensitive)
        $itemsQuery->whereRaw('LOWER(description) LIKE ?', ["%{$query}%"]);

        // Get recent items, grouped by description to avoid duplicates
        $items = $itemsQuery
            ->select('description', 'customs_code', 'duty_rate', 'customs_code_description')
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        // Group by description and customs_code to find unique classifications
        $grouped = $items->groupBy(function ($item) {
            return strtolower($item->description) . '|' . $item->customs_code;
        });

        // Score and sort by relevance
        $results = [];
        foreach ($grouped as $key => $group) {
            $item = $group->first();
            $desc = strtolower($item->description);
            
            // Calculate relevance score
            $score = 0;
            
            // Exact match gets highest score
            if ($desc === $query) {
                $score = 100;
            }
            // Starts with query
            elseif (str_starts_with($desc, $query)) {
                $score = 90;
            }
            // Contains query as whole word
            elseif (preg_match('/\b' . preg_quote($query, '/') . '\b/i', $desc)) {
                $score = 80;
            }
            // Contains query
            else {
                $score = 60;
            }
            
            // Boost if item has been classified multiple times (more reliable)
            $count = $group->count();
            if ($count > 1) {
                $score += min(10, $count);
            }

            $results[] = [
                'description' => $item->description,
                'customs_code' => $item->customs_code,
                'duty_rate' => $item->duty_rate,
                'customs_code_description' => $item->customs_code_description,
                'times_used' => $count,
                'score' => $score,
            ];
        }

        // Sort by score descending
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        // Take top 10 results
        $results = array_slice($results, 0, 10);

        return response()->json([
            'success' => true,
            'results' => $results,
        ]);
    }

    /**
     * AJAX: Get classification review data for a single invoice item.
     * Returns AI suggestions, historical precedents, customs code details, etc.
     */
    public function getItemClassificationReview(InvoiceItem $invoiceItem): JsonResponse
    {
        $user = auth()->user();
        $invoice = Invoice::withoutGlobalScopes()->find($invoiceItem->invoice_id);

        if (!$invoice || ($invoice->user_id !== $user->id && $invoice->organization_id !== $user->organization_id && !$user->isAdmin())) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $countryId = $invoice->country_id;

        // 1. Look up the official customs code record
        $customsCodeRecord = null;
        if ($invoiceItem->customs_code && $countryId) {
            $customsCodeRecord = CustomsCode::where('code', $invoiceItem->customs_code)
                ->where('country_id', $countryId)
                ->first();
        }

        // 2. Get AI classification from cache (if available)
        $aiClassification = null;
        $cacheKey = "invoice_classification_{$invoice->id}";
        $classificationData = Cache::get($cacheKey);

        if ($classificationData && ($classificationData['status'] ?? '') === 'completed') {
            $cachedItems = $classificationData['items_with_codes'] ?? [];
            foreach ($cachedItems as $cached) {
                if (($cached['line_number'] ?? null) == $invoiceItem->line_number
                    || ($cached['description'] ?? '') === $invoiceItem->description) {
                    $aiClassification = $cached['classification'] ?? null;
                    break;
                }
            }
        }

        // 3. Historical precedents
        $precedents = $this->findPrecedentsForItem(
            $invoiceItem->description,
            $countryId
        );

        // 4. Classification memory — similar items previously classified
        $memoryMatches = InvoiceItem::where('country_id', $countryId)
            ->whereNotNull('customs_code')
            ->where('id', '!=', $invoiceItem->id)
            ->where(function ($q) use ($invoiceItem) {
                $q->where('description', $invoiceItem->description);
                if ($invoiceItem->sku) {
                    $q->orWhere('sku', $invoiceItem->sku);
                }
            })
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get(['description', 'customs_code', 'customs_code_description', 'duty_rate', 'updated_at']);

        return response()->json([
            'success' => true,
            'item' => [
                'id' => $invoiceItem->id,
                'description' => $invoiceItem->description,
                'sku' => $invoiceItem->sku,
                'customs_code' => $invoiceItem->customs_code,
                'customs_code_description' => $invoiceItem->customs_code_description,
                'duty_rate' => $invoiceItem->duty_rate,
            ],
            'customs_code_record' => $customsCodeRecord ? [
                'code' => $customsCodeRecord->code,
                'description' => $customsCodeRecord->description,
                'duty_rate' => $customsCodeRecord->duty_rate,
                'duty_type' => $customsCodeRecord->duty_type,
                'unit_of_measurement' => $customsCodeRecord->unit_of_measurement,
                'formatted_duty' => $customsCodeRecord->formatted_duty,
                'chapter_number' => $customsCodeRecord->chapter_number,
                'code_level' => $customsCodeRecord->code_level,
                'notes' => $customsCodeRecord->notes,
            ] : null,
            'ai_classification' => $aiClassification,
            'precedents' => $precedents,
            'memory_matches' => $memoryMatches->map(fn($m) => [
                'description' => $m->description,
                'customs_code' => $m->customs_code,
                'customs_code_description' => $m->customs_code_description,
                'duty_rate' => $m->duty_rate,
                'date' => $m->updated_at->format('M d, Y'),
            ])->toArray(),
        ]);
    }
}
