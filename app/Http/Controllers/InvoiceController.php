<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use App\Services\InvoiceExtractor;
use App\Services\CustomsCodeMatcher;

class InvoiceController extends Controller
{
    protected $invoiceExtractor;
    protected $customsCodeMatcher;

    public function __construct(InvoiceExtractor $invoiceExtractor, CustomsCodeMatcher $customsCodeMatcher)
    {
        $this->invoiceExtractor = $invoiceExtractor;
        $this->customsCodeMatcher = $customsCodeMatcher;
    }

    public function create()
    {
        $countries = \App\Models\Country::active()->orderBy('name')->get();
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
            'invoice_file' => 'required|file|mimes:pdf,jpg,jpeg,png,tiff|max:10240',
            'country_id' => 'required|exists:countries,id',
        ]);

        if ($request->file('invoice_file')->isValid()) {
            $path = $request->file('invoice_file')->store('invoices');
            
            // Extract invoice data
            $extractedData = $this->invoiceExtractor->extract($path);

            // Store the extracted data and country in the session for review
            session([
                'extracted_invoice_data' => $extractedData,
                'invoice_country_id' => $request->country_id,
            ]);

            return redirect()->route('invoices.review');
        }

        return back()->with('error', 'Failed to upload invoice');
    }

    public function review()
    {
        $extractedData = session('extracted_invoice_data');
        if (!$extractedData) {
            return redirect()->route('invoices.create')->with('error', 'No invoice data found. Please upload an invoice first.');
        }

        return view('invoices.review', compact('extractedData'));
    }

    public function confirm(Request $request)
    {
        $validatedData = $request->validate([
            'items' => 'required|array',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|numeric',
            'items.*.unit_price' => 'required|numeric',
        ]);

        $countryId = session('invoice_country_id');

        // Match customs codes for the selected country
        $itemsWithCodes = $this->customsCodeMatcher->matchCodes($validatedData['items'], $countryId);

        // Store the matched items in the session
        session(['items_with_codes' => $itemsWithCodes]);

        return redirect()->route('invoices.assign_codes');
    }

    public function assignCodes()
    {
        $itemsWithCodes = session('items_with_codes');
        if (!$itemsWithCodes) {
            return redirect()->route('invoices.create')->with('error', 'No invoice data found. Please upload an invoice first.');
        }

        return view('invoices.assign_codes', compact('itemsWithCodes'));
    }

    public function finalize(Request $request)
    {
        $validatedData = $request->validate([
            'items' => 'required|array',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|numeric',
            'items.*.unit_price' => 'required|numeric',
            'items.*.customs_code' => 'required|string',
        ]);

        $user = auth()->user();
        $countryId = session('invoice_country_id');

        $invoice = Invoice::create([
            'organization_id' => $user->organization_id,
            'country_id' => $countryId,
            'user_id' => $user->id,
            'invoice_number' => 'INV-' . now()->format('Ymd') . '-' . rand(1000, 9999),
            'invoice_date' => now(),
            'total_amount' => collect($validatedData['items'])->sum(function ($item) {
                return $item['quantity'] * $item['unit_price'];
            }),
            'status' => 'processed',
            'processed' => true,
            'items' => $validatedData['items'],
        ]);

        // Clear the session data
        session()->forget(['extracted_invoice_data', 'items_with_codes', 'invoice_country_id']);

        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice processed successfully');
    }

    public function show(Invoice $invoice)
    {
        return view('invoices.show', compact('invoice'));
    }
}
