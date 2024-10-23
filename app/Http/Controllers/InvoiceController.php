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
        return view('invoices.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'invoice_file' => 'required|file|mimes:pdf,jpg,jpeg,png,tiff|max:10240',
        ]);

        if ($request->file('invoice_file')->isValid()) {
            $path = $request->file('invoice_file')->store('invoices');
            
            // Extract invoice data
            $extractedData = $this->invoiceExtractor->extract($path);

            // Store the extracted data in the session for review
            session(['extracted_invoice_data' => $extractedData]);

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

        // Match customs codes
        $itemsWithCodes = $this->customsCodeMatcher->matchCodes($validatedData['items']);

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

        $invoice = new Invoice([
            'user_id' => auth()->id(),
            'invoice_number' => 'INV-' . rand(1000, 9999),
            'invoice_date' => now(),
            'total_amount' => collect($validatedData['items'])->sum(function ($item) {
                return $item['quantity'] * $item['unit_price'];
            }),
            'status' => 'processed',
            'items' => json_encode($validatedData['items']),
        ]);

        $invoice->save();

        // Clear the session data
        session()->forget(['extracted_invoice_data', 'items_with_codes']);

        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice processed successfully');
    }

    public function show(Invoice $invoice)
    {
        return view('invoices.show', compact('invoice'));
    }
}
