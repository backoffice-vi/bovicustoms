<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\CountryFormTemplate;
use App\Models\CountrySupportDocument;
use App\Services\DocumentTextExtractor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CountryDocumentController extends Controller
{
    /**
     * Display the main index with both document types
     */
    public function index(Request $request)
    {
        $countries = Country::active()->orderBy('name')->get();
        $selectedCountryId = $request->get('country_id');
        $activeTab = $request->get('tab', 'templates');

        // Get templates query
        $templatesQuery = CountryFormTemplate::with(['country', 'uploader']);
        if ($selectedCountryId) {
            $templatesQuery->where('country_id', $selectedCountryId);
        }
        $templates = $templatesQuery->orderBy('created_at', 'desc')->paginate(15, ['*'], 'templates_page');

        // Get support documents query
        $supportQuery = CountrySupportDocument::with(['country', 'uploader']);
        if ($selectedCountryId) {
            $supportQuery->where('country_id', $selectedCountryId);
        }
        $supportDocuments = $supportQuery->orderBy('created_at', 'desc')->paginate(15, ['*'], 'support_page');

        return view('admin.country-documents.index', compact(
            'countries',
            'selectedCountryId',
            'activeTab',
            'templates',
            'supportDocuments'
        ));
    }

    // ==========================================
    // FORM TEMPLATES
    // ==========================================

    /**
     * Show the form for creating a new template
     */
    public function createTemplate()
    {
        $countries = Country::active()->orderBy('name')->get();
        $formTypes = CountryFormTemplate::getFormTypes();
        
        return view('admin.country-documents.templates.create', compact('countries', 'formTypes'));
    }

    /**
     * Store a newly uploaded template
     */
    public function storeTemplate(Request $request)
    {
        $validated = $request->validate([
            'country_id' => 'required|exists:countries,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'form_type' => 'required|in:' . implode(',', array_keys(CountryFormTemplate::getFormTypes())),
            'document' => 'required|file|mimes:pdf,doc,docx,xls,xlsx,txt|max:51200', // 50MB max
        ]);

        $file = $request->file('document');
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $filename = Str::uuid() . '.' . $extension;
        
        // Store the file
        $path = $file->storeAs('country-form-templates', $filename);

        // Create the record
        $template = CountryFormTemplate::create([
            'country_id' => $validated['country_id'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'filename' => $filename,
            'original_filename' => $originalName,
            'file_path' => $path,
            'file_type' => strtolower($extension),
            'file_size' => $file->getSize(),
            'form_type' => $validated['form_type'],
            'is_active' => true,
            'uploaded_by' => auth()->id(),
        ]);

        return redirect()->route('admin.country-documents.templates.show', $template)
            ->with('success', 'Form template uploaded successfully.');
    }

    /**
     * Display the specified template
     */
    public function showTemplate(CountryFormTemplate $template)
    {
        $template->load(['country', 'uploader']);
        
        return view('admin.country-documents.templates.show', compact('template'));
    }

    /**
     * Toggle template active status
     */
    public function toggleTemplate(CountryFormTemplate $template)
    {
        $template->update(['is_active' => !$template->is_active]);
        
        $status = $template->is_active ? 'activated' : 'deactivated';
        
        return redirect()->back()
            ->with('success', "Template has been {$status}.");
    }

    /**
     * Download the template file
     */
    public function downloadTemplate(CountryFormTemplate $template)
    {
        if (!Storage::exists($template->file_path)) {
            return redirect()->back()
                ->with('error', 'File not found.');
        }

        return Storage::download($template->file_path, $template->original_filename);
    }

    /**
     * Remove the specified template
     */
    public function destroyTemplate(CountryFormTemplate $template)
    {
        // Delete the file
        if (Storage::exists($template->file_path)) {
            Storage::delete($template->file_path);
        }

        $template->delete();

        return redirect()->route('admin.country-documents.index', ['tab' => 'templates'])
            ->with('success', 'Template deleted successfully.');
    }

    // ==========================================
    // SUPPORT DOCUMENTS
    // ==========================================

    /**
     * Show the form for creating a new support document
     */
    public function createSupport()
    {
        $countries = Country::active()->orderBy('name')->get();
        $documentTypes = CountrySupportDocument::getDocumentTypes();
        
        return view('admin.country-documents.support.create', compact('countries', 'documentTypes'));
    }

    /**
     * Store a newly uploaded support document
     */
    public function storeSupport(Request $request)
    {
        $validated = $request->validate([
            'country_id' => 'required|exists:countries,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'document_type' => 'required|in:' . implode(',', array_keys(CountrySupportDocument::getDocumentTypes())),
            'document' => 'required|file|mimes:pdf,doc,docx,xls,xlsx,txt|max:51200', // 50MB max
        ]);

        $file = $request->file('document');
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $filename = Str::uuid() . '.' . $extension;
        
        // Store the file
        $path = $file->storeAs('country-support-documents', $filename);

        // Create the record
        $document = CountrySupportDocument::create([
            'country_id' => $validated['country_id'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'filename' => $filename,
            'original_filename' => $originalName,
            'file_path' => $path,
            'file_type' => strtolower($extension),
            'file_size' => $file->getSize(),
            'document_type' => $validated['document_type'],
            'status' => CountrySupportDocument::STATUS_PENDING,
            'is_active' => true,
            'uploaded_by' => auth()->id(),
        ]);

        return redirect()->route('admin.country-documents.support.show', $document)
            ->with('success', 'Support document uploaded successfully. Click "Extract Text" to process the document for AI reference.');
    }

    /**
     * Display the specified support document
     */
    public function showSupport(CountrySupportDocument $document)
    {
        $document->load(['country', 'uploader']);
        
        return view('admin.country-documents.support.show', compact('document'));
    }

    /**
     * Extract text from the support document
     */
    public function extractText(CountrySupportDocument $document, DocumentTextExtractor $extractor)
    {
        if ($document->isProcessing()) {
            return redirect()->route('admin.country-documents.support.show', $document)
                ->with('warning', 'Document is already being processed.');
        }

        try {
            $document->markAsProcessing();
            
            // Extract text from the document
            $text = $extractor->extractText($document->getFullPath());
            
            if (empty($text)) {
                throw new \Exception('No text could be extracted from the document.');
            }
            
            $document->markAsCompleted($text);
            
            return redirect()->route('admin.country-documents.support.show', $document)
                ->with('success', 'Text extracted successfully. The AI can now reference this document.');
                
        } catch (\Exception $e) {
            $document->markAsFailed($e->getMessage());
            
            return redirect()->route('admin.country-documents.support.show', $document)
                ->with('error', 'Failed to extract text: ' . $e->getMessage());
        }
    }

    /**
     * Toggle support document active status
     */
    public function toggleSupport(CountrySupportDocument $document)
    {
        $document->update(['is_active' => !$document->is_active]);
        
        $status = $document->is_active ? 'activated' : 'deactivated';
        
        return redirect()->back()
            ->with('success', "Document has been {$status}.");
    }

    /**
     * Download the support document file
     */
    public function downloadSupport(CountrySupportDocument $document)
    {
        if (!Storage::exists($document->file_path)) {
            return redirect()->back()
                ->with('error', 'File not found.');
        }

        return Storage::download($document->file_path, $document->original_filename);
    }

    /**
     * Remove the specified support document
     */
    public function destroySupport(CountrySupportDocument $document)
    {
        // Delete the file
        if (Storage::exists($document->file_path)) {
            Storage::delete($document->file_path);
        }

        $document->delete();

        return redirect()->route('admin.country-documents.index', ['tab' => 'support'])
            ->with('success', 'Support document deleted successfully.');
    }
}
