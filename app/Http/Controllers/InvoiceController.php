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
        // Verify the user owns this invoice
        $user = auth()->user();
        if ($invoice->user_id !== $user->id && $invoice->organization_id !== $user->organization_id) {
            abort(403, 'Unauthorized access to this invoice.');
        }

        $countryId = session('invoice_country_id') ?? $invoice->country_id;
        $country = Country::find($countryId);

        return view('invoices.classification_status', compact('invoice', 'country'));
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
     */
    public function startClassification(Invoice $invoice): JsonResponse
    {
        // Verify the user owns this invoice
        $user = auth()->user();
        if ($invoice->user_id !== $user->id && $invoice->organization_id !== $user->organization_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Get pending classification data from session
        $pending = session('pending_classification');
        
        if (!$pending || $pending['invoice_id'] !== $invoice->id) {
            // Check if already classified
            $cacheKey = "invoice_classification_{$invoice->id}";
            $status = Cache::get($cacheKey);
            if ($status && $status['status'] === 'completed') {
                return response()->json(['status' => 'already_completed']);
            }
            return response()->json(['error' => 'No pending classification found'], 400);
        }

        // Clear from session
        session()->forget('pending_classification');

        // Set initial status in cache
        $cacheKey = "invoice_classification_{$invoice->id}";
        Cache::put($cacheKey, [
            'status' => 'processing',
            'progress' => 0,
            'message' => 'Starting classification...',
            'started_at' => now()->toIso8601String(),
        ], now()->addHours(2));

        // Check if using sync queue
        $isSync = config('queue.default') === 'sync';
        
        if (!$isSync) {
            // Ensure queue worker is running (only for non-sync queues)
            EnsureQueueWorker::ensureRunning(timeout: 900, memory: 512);
        }

        // Dispatch the job
        ClassifyInvoiceItems::dispatch(
            $invoice,
            $user,
            $pending['items'],
            $pending['country_id'],
            $pending['invoice_header']
        );

        Log::info('Classification started via AJAX', [
            'invoice_id' => $invoice->id,
            'item_count' => count($pending['items']),
            'queue_driver' => config('queue.default'),
        ]);

        // With sync queue, job already completed
        if ($isSync) {
            return response()->json(['status' => 'completed_sync']);
        }

        return response()->json(['status' => 'started']);
    }

    /**
     * Show the classification results after background processing completes.
     */
    public function assignCodesResults(Invoice $invoice)
    {
        // Verify the user owns this invoice
        $user = auth()->user();
        if ($invoice->user_id !== $user->id && $invoice->organization_id !== $user->organization_id) {
            abort(403, 'Unauthorized access to this invoice.');
        }

        $cacheKey = "invoice_classification_{$invoice->id}";
        $classificationData = Cache::get($cacheKey);

        if (!$classificationData || $classificationData['status'] !== 'completed') {
            return redirect()->route('invoices.classification_status', ['invoice' => $invoice->id])
                ->with('info', 'Classification is still in progress. Please wait.');
        }

        $itemsWithCodes = $classificationData['items_with_codes'];
        $invoiceHeader = $classificationData['invoice_header'];
        $countryId = $classificationData['country_id'];
        $country = Country::find($countryId);

        // Store in session for the finalize step
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
                // Update the existing invoice
                $invoice->update([
                    'status' => 'processed',
                    'processed' => true,
                    'items' => $validatedData['items'],
                    'total_amount' => $totalAmount ?: $invoice->total_amount,
                ]);

                // Clear any cached classification data
                Cache::forget("invoice_classification_{$invoice->id}");
            }
        }

        // If no existing invoice, create a new one
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

        // Delete existing invoice items if updating
        InvoiceItem::where('invoice_id', $invoice->id)->delete();

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
                'duty_rate' => $item['duty_rate'] ?? null,
                'customs_code_description' => $item['customs_code_description'] ?? null,
            ]);
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
}
