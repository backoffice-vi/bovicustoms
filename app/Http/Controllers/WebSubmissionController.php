<?php

namespace App\Http\Controllers;

use App\Models\DeclarationForm;
use App\Models\WebFormTarget;
use App\Models\WebFormSubmission;
use App\Services\WebFormSubmission\WebFormSubmitterService;
use App\Services\WebFormSubmission\WebFormDataMapper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebSubmissionController extends Controller
{
    protected WebFormSubmitterService $submitter;
    protected WebFormDataMapper $mapper;

    public function __construct(WebFormSubmitterService $submitter, WebFormDataMapper $mapper)
    {
        $this->submitter = $submitter;
        $this->mapper = $mapper;
    }

    /**
     * Show available targets and submission options for a declaration
     */
    public function index(DeclarationForm $declaration)
    {
        $declaration->load(['country', 'invoice', 'shipment']);

        // Get active targets for this country
        $targets = WebFormTarget::active()
            ->where('country_id', $declaration->country_id)
            ->with('pages')
            ->get();

        // Get past submissions for this declaration
        $submissions = WebFormSubmission::where('declaration_form_id', $declaration->id)
            ->with('target')
            ->latest()
            ->get();

        return view('web-submission.index', compact('declaration', 'targets', 'submissions'));
    }

    /**
     * Preview mapping before submission
     */
    public function preview(DeclarationForm $declaration, WebFormTarget $target)
    {
        // Verify target is for the right country
        if ($target->country_id !== $declaration->country_id) {
            return redirect()->back()->with('error', 'Invalid target for this declaration.');
        }

        $declaration->load([
            'country', 
            'items',
            'invoice.invoiceItems', 
            'shipment.shipperContact',
            'shipment.consigneeContact',
            'shipperContact',
            'consigneeContact',
        ]);

        // Load target with pages and field mappings
        $target->load(['pages.fieldMappings']);

        // Preview the mapping
        $preview = $this->mapper->previewMapping($declaration, $target);
        $validation = $this->mapper->validateMapping($declaration, $target);

        return view('web-submission.preview', compact('declaration', 'target', 'preview', 'validation'));
    }

    /**
     * Submit the declaration to the external portal
     */
    public function submit(Request $request, DeclarationForm $declaration, WebFormTarget $target)
    {
        // Verify target is for the right country
        if ($target->country_id !== $declaration->country_id) {
            return redirect()->back()->with('error', 'Invalid target for this declaration.');
        }

        // Validate mapping first
        $validation = $this->mapper->validateMapping($declaration, $target);
        if (!$validation['valid'] && !$request->has('force')) {
            return redirect()->back()->with('error', 'Mapping validation failed: ' . implode(', ', $validation['errors']));
        }

        try {
            $useAI = $request->boolean('use_ai', $target->requires_ai);
            
            $submission = $this->submitter->submit($declaration, $target, $useAI);

            if ($submission->is_successful) {
                return redirect()->route('web-submission.result', [
                    'declaration' => $declaration,
                    'submission' => $submission,
                ])->with('success', 'Declaration submitted successfully! Reference: ' . $submission->external_reference);
            } else {
                return redirect()->route('web-submission.result', [
                    'declaration' => $declaration,
                    'submission' => $submission,
                ])->with('error', 'Submission failed: ' . $submission->error_message);
            }

        } catch (\Exception $e) {
            Log::error('Web submission failed', [
                'declaration_id' => $declaration->id,
                'target_id' => $target->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Submission failed: ' . $e->getMessage());
        }
    }

    /**
     * Show submission result
     */
    public function result(DeclarationForm $declaration, WebFormSubmission $submission)
    {
        $submission->load(['target', 'user']);
        $declaration->load(['country', 'invoice']);

        return view('web-submission.result', compact('declaration', 'submission'));
    }

    /**
     * View all submissions for a declaration
     */
    public function history(DeclarationForm $declaration)
    {
        $submissions = WebFormSubmission::where('declaration_form_id', $declaration->id)
            ->with(['target', 'user'])
            ->latest()
            ->paginate(10);

        return view('web-submission.history', compact('declaration', 'submissions'));
    }

    /**
     * Retry a failed submission
     */
    public function retry(WebFormSubmission $submission)
    {
        if (!$submission->can_retry) {
            return redirect()->back()->with('error', 'This submission cannot be retried.');
        }

        try {
            $newSubmission = $this->submitter->retry($submission);

            return redirect()->route('web-submission.result', [
                'declaration' => $newSubmission->declaration_form_id,
                'submission' => $newSubmission,
            ])->with('info', 'Retry submission created.');

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Retry failed: ' . $e->getMessage());
        }
    }

    /**
     * Get available targets for a country (AJAX)
     */
    public function getTargetsForCountry(Request $request)
    {
        $countryId = $request->input('country_id');
        
        $targets = WebFormTarget::active()
            ->where('country_id', $countryId)
            ->get(['id', 'name', 'code', 'base_url']);

        return response()->json($targets);
    }
}
