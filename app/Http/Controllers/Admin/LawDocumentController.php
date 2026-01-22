<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LawDocument;
use App\Models\Country;
use App\Models\CustomsCodeHistory;
use App\Services\LawDocumentProcessor;
use App\Services\ItemClassifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LawDocumentController extends Controller
{
    /**
     * Display a listing of law documents
     */
    public function index(Request $request)
    {
        $query = LawDocument::with(['country', 'uploader']);
        
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('country_id') && $request->country_id) {
            $query->where('country_id', $request->country_id);
        }
        
        $documents = $query->orderBy('created_at', 'desc')->paginate(20);
        $countries = Country::active()->orderBy('name')->get();
        
        return view('admin.law-documents.index', compact('documents', 'countries'));
    }

    /**
     * Show the form for uploading a new document
     */
    public function create()
    {
        $countries = Country::active()->orderBy('name')->get();
        return view('admin.law-documents.create', compact('countries'));
    }

    /**
     * Store a newly uploaded document
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'document' => 'required|file|mimes:pdf,doc,docx,txt,xlsx,xls|max:51200', // 50MB max
            'country_id' => 'nullable|exists:countries,id',
        ]);

        $file = $request->file('document');
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $filename = Str::uuid() . '.' . $extension;
        
        // Store the file
        $path = $file->storeAs('law-documents', $filename);

        // Create the record
        $document = LawDocument::create([
            'filename' => $filename,
            'original_filename' => $originalName,
            'file_path' => $path,
            'file_type' => strtolower($extension),
            'file_size' => $file->getSize(),
            'country_id' => $validated['country_id'] ?? null,
            'status' => LawDocument::STATUS_PENDING,
            'uploaded_by' => auth()->id(),
        ]);

        return redirect()->route('admin.law-documents.show', $document)
            ->with('success', 'Document uploaded successfully. Click "Process Document" to extract categories.');
    }

    /**
     * Display the specified document
     */
    public function show(LawDocument $lawDocument)
    {
        $lawDocument->load(['country', 'uploader']);
        
        // Get history entries created from this document
        $historyEntries = CustomsCodeHistory::with('customsCode')
            ->where('law_document_id', $lawDocument->id)
            ->orderBy('created_at', 'desc')
            ->get();
        
        return view('admin.law-documents.show', compact('lawDocument', 'historyEntries'));
    }

    /**
     * Process the document with AI
     */
    public function process(LawDocument $lawDocument, LawDocumentProcessor $processor, ItemClassifier $classifier)
    {
        if ($lawDocument->isProcessing()) {
            return redirect()->route('admin.law-documents.show', $lawDocument)
                ->with('warning', 'Document is already being processed.');
        }

        $result = $processor->process($lawDocument);

        // Clear the classifier cache since codes may have changed
        $classifier->clearCache($lawDocument->country_id);

        if ($result['success']) {
            return redirect()->route('admin.law-documents.show', $lawDocument)
                ->with('success', "Document processed successfully. Created: {$result['created']}, Updated: {$result['updated']} customs codes.");
        }

        return redirect()->route('admin.law-documents.show', $lawDocument)
            ->with('error', "Processing failed: {$result['error']}");
    }

    /**
     * Remove the specified document
     */
    public function destroy(LawDocument $lawDocument)
    {
        // Delete the file
        if (Storage::exists($lawDocument->file_path)) {
            Storage::delete($lawDocument->file_path);
        }

        $lawDocument->delete();

        return redirect()->route('admin.law-documents.index')
            ->with('success', 'Document deleted successfully.');
    }

    /**
     * Download the document
     */
    public function download(LawDocument $lawDocument)
    {
        if (!Storage::exists($lawDocument->file_path)) {
            return redirect()->route('admin.law-documents.show', $lawDocument)
                ->with('error', 'File not found.');
        }

        return Storage::download($lawDocument->file_path, $lawDocument->original_filename);
    }

    /**
     * Reprocess the document
     */
    public function reprocess(LawDocument $lawDocument, LawDocumentProcessor $processor, ItemClassifier $classifier)
    {
        // Reset status to pending
        $lawDocument->update([
            'status' => LawDocument::STATUS_PENDING,
            'error_message' => null,
            'processed_at' => null,
        ]);

        return $this->process($lawDocument, $processor, $classifier);
    }
}
