<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LawDocument;
use App\Models\Country;
use App\Models\CustomsCodeHistory;
use App\Jobs\ProcessLawDocument;
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
     * Process the document with AI (dispatches background job)
     */
    public function process(LawDocument $lawDocument, ItemClassifier $classifier)
    {
        if ($lawDocument->isProcessing()) {
            return redirect()->route('admin.law-documents.show', $lawDocument)
                ->with('warning', 'Document is already being processed.');
        }

        // Mark as processing immediately
        $lawDocument->markAsProcessing();

        // Clear the classifier cache since codes may change
        $classifier->clearCache($lawDocument->country_id);

        // Dispatch the background job
        ProcessLawDocument::dispatch($lawDocument);

        return redirect()->route('admin.law-documents.show', $lawDocument)
            ->with('info', 'Document processing started in the background. This page will automatically update when complete.');
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
    public function reprocess(LawDocument $lawDocument, ItemClassifier $classifier)
    {
        // Reset status to pending
        $lawDocument->update([
            'status' => LawDocument::STATUS_PENDING,
            'error_message' => null,
            'processed_at' => null,
        ]);

        return $this->process($lawDocument, $classifier);
    }

    /**
     * Get document status (for AJAX polling)
     */
    public function status(LawDocument $lawDocument)
    {
        $lawDocument->load(['country']);
        
        $historyCount = CustomsCodeHistory::where('law_document_id', $lawDocument->id)->count();
        
        return response()->json([
            'id' => $lawDocument->id,
            'status' => $lawDocument->status,
            'status_label' => ucfirst($lawDocument->status),
            'status_class' => $this->getStatusClass($lawDocument->status),
            'error_message' => $lawDocument->error_message,
            'processed_at' => $lawDocument->processed_at?->format('M d, H:i'),
            'history_count' => $historyCount,
        ]);
    }

    /**
     * Get Bootstrap class for status badge
     */
    protected function getStatusClass(string $status): string
    {
        return match($status) {
            LawDocument::STATUS_PENDING => 'warning',
            LawDocument::STATUS_PROCESSING => 'info',
            LawDocument::STATUS_COMPLETED => 'success',
            LawDocument::STATUS_FAILED => 'danger',
            default => 'secondary',
        };
    }
}
