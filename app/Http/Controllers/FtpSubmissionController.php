<?php

namespace App\Http\Controllers;

use App\Models\DeclarationForm;
use App\Models\OrganizationSubmissionCredential;
use App\Models\WebFormSubmission;
use App\Services\FtpSubmission\CapsErrorAgent;
use App\Services\FtpSubmission\CapsResponseParser;
use App\Services\FtpSubmission\FixAndResubmitService;
use App\Services\FtpSubmission\FtpSubmissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FtpSubmissionController extends Controller
{
    protected FtpSubmissionService $ftpService;

    public function __construct(FtpSubmissionService $ftpService)
    {
        $this->ftpService = $ftpService;
    }

    /**
     * Show FTP submission options for a declaration
     */
    public function index(DeclarationForm $declaration)
    {
        $declaration->load(['country', 'organization', 'invoice', 'shipment', 'consigneeContact', 'shipment.consigneeContact']);

        $country = $declaration->country;
        
        if (!$country || !$country->isFtpEnabled()) {
            return redirect()->back()
                ->with('error', 'FTP submission is not available for this country.');
        }

        // Get organization's FTP credentials
        $organization = $declaration->organization ?? auth()->user()->organization;
        $credentials = null;
        
        if ($organization) {
            $credentials = $organization->getFtpCredentials($country->id);
        }

        // Get consignee's Trader ID
        $consignee = $declaration->consigneeContact ?? $declaration->shipment?->consigneeContact;
        $consigneeTraderId = $consignee?->customs_registration_id;

        // Get past FTP submissions for this declaration
        $submissions = WebFormSubmission::ftp()
            ->forDeclaration($declaration->id)
            ->with('user')
            ->latest()
            ->get();

        return view('ftp-submission.index', compact(
            'declaration',
            'country',
            'credentials',
            'submissions',
            'consignee',
            'consigneeTraderId'
        ));
    }

    /**
     * Preview the T12 file before submission
     */
    public function preview(DeclarationForm $declaration)
    {
        $declaration->load([
            'country',
            'organization',
            'declarationItems',
            'invoice.invoiceItems',
            'shipment.shipperContact',
            'shipment.consigneeContact',
            'shipperContact',
            'consigneeContact',
        ]);

        $country = $declaration->country;
        
        if (!$country || !$country->isFtpEnabled()) {
            return redirect()->back()
                ->with('error', 'FTP submission is not available for this country.');
        }

        // Get organization's FTP credentials
        $organization = $declaration->organization ?? auth()->user()->organization;
        
        if (!$organization) {
            return redirect()->route('settings.submission-credentials')
                ->with('error', 'Please configure your FTP credentials first.');
        }

        $credentials = $organization->getFtpCredentials($country->id);
        
        if (!$credentials || !$credentials->hasCompleteFtpCredentials()) {
            return redirect()->route('settings.submission-credentials')
                ->with('error', 'Please configure your FTP credentials for ' . $country->name . ' before submitting.');
        }

        // Generate preview
        try {
            $preview = $this->ftpService->preview($declaration, $credentials);
            $validation = $this->ftpService->validatePreview($preview, $declaration);
        } catch (\Exception $e) {
            Log::error('T12 preview generation failed', [
                'declaration_id' => $declaration->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to generate T12 preview: ' . $e->getMessage());
        }

        $availableAttachments = app(\App\Services\Documents\DeclarationAttachmentGatherer::class)
            ->gather($declaration, includeMissing: true);

        return view('ftp-submission.preview', compact(
            'declaration',
            'country',
            'credentials',
            'preview',
            'validation',
            'availableAttachments'
        ));
    }

    /**
     * Submit via FTP
     */
    public function submit(Request $request, DeclarationForm $declaration)
    {
        $declaration->load(['country', 'organization']);

        $country = $declaration->country;
        
        if (!$country || !$country->isFtpEnabled()) {
            return redirect()->back()
                ->with('error', 'FTP submission is not available for this country.');
        }

        // Get organization's FTP credentials
        $organization = $declaration->organization ?? auth()->user()->organization;
        
        if (!$organization) {
            return redirect()->route('settings.submission-credentials')
                ->with('error', 'Please configure your FTP credentials first.');
        }

        $credentials = $organization->getFtpCredentials($country->id);
        
        if (!$credentials || !$credentials->hasCompleteFtpCredentials()) {
            return redirect()->route('settings.submission-credentials')
                ->with('error', 'Please configure your FTP credentials before submitting.');
        }

        // Validate basic declaration data first. The FTP service also validates
        // the generated T12 payload before uploading it to CAPS.
        $validation = $this->ftpService->validate($declaration);
        
        if (!$validation['valid'] && !$request->has('force')) {
            return redirect()->back()
                ->with('error', 'Validation failed: ' . implode(', ', $validation['errors']));
        }

        $autoAttach = $request->boolean('auto_attach', true);

        try {
            $submission = $this->ftpService->submit($declaration, $credentials, true, $autoAttach);

            if ($submission->is_successful) {
                $message = 'Declaration submitted via FTP! Reference: ' . $submission->external_reference;
                if ($autoAttach) {
                    $submission->loadMissing('ftpAttachments');
                    $message .= '. Attachments uploaded: ' . $submission->ftpAttachments->whereIn('status', [
                        \App\Models\FtpSubmissionAttachment::STATUS_UPLOADED,
                        \App\Models\FtpSubmissionAttachment::STATUS_CONFIRMED,
                    ])->count() . '.';
                }

                return redirect()->route('ftp-submission.result', [
                    'declaration' => $declaration,
                    'submission' => $submission,
                ])->with('success', $message);
            } else {
                return redirect()->route('ftp-submission.result', [
                    'declaration' => $declaration,
                    'submission' => $submission,
                ])->with('error', 'FTP submission failed: ' . $submission->error_message);
            }

        } catch (\Exception $e) {
            Log::error('FTP submission failed', [
                'declaration_id' => $declaration->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'FTP submission failed: ' . $e->getMessage());
        }
    }

    /**
     * Show submission result
     */
    public function result(DeclarationForm $declaration, WebFormSubmission $submission)
    {
        $declaration->load(['country', 'organization']);
        $submission->load('user');

        return view('ftp-submission.result', compact('declaration', 'submission'));
    }

    /**
     * Download the generated T12 file
     */
    public function download(DeclarationForm $declaration)
    {
        $declaration->load(['country', 'organization']);

        $country = $declaration->country;
        
        if (!$country || !$country->isFtpEnabled()) {
            return redirect()->back()
                ->with('error', 'FTP submission is not available for this country.');
        }

        // Get organization's FTP credentials
        $organization = $declaration->organization ?? auth()->user()->organization;
        
        if (!$organization) {
            return redirect()->back()
                ->with('error', 'Organization not found.');
        }

        $credentials = $organization->getFtpCredentials($country->id);
        
        if (!$credentials || !$credentials->hasCompleteFtpCredentials()) {
            return redirect()->route('settings.submission-credentials')
                ->with('error', 'Please configure your FTP credentials first.');
        }

        try {
            $t12Data = $this->ftpService->generateOnly($declaration, $credentials);

            return response($t12Data['content'])
                ->header('Content-Type', 'text/plain')
                ->header('Content-Disposition', 'attachment; filename="' . $t12Data['filename'] . '"');

        } catch (\Exception $e) {
            Log::error('T12 download failed', [
                'declaration_id' => $declaration->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to generate T12 file: ' . $e->getMessage());
        }
    }

    /**
     * View submission history
     */
    public function history(DeclarationForm $declaration)
    {
        $declaration->load(['country', 'organization']);

        $submissions = WebFormSubmission::ftp()
            ->forDeclaration($declaration->id)
            ->with('user')
            ->latest()
            ->paginate(10);

        return view('ftp-submission.history', compact('declaration', 'submissions'));
    }

    /**
     * Retry a failed submission
     */
    public function retry(WebFormSubmission $submission)
    {
        if (!$submission->is_ftp) {
            return redirect()->back()
                ->with('error', 'This is not an FTP submission.');
        }

        if (!$submission->can_retry) {
            return redirect()->back()
                ->with('error', 'This submission cannot be retried.');
        }

        $declaration = $submission->declaration;
        $declaration->load(['country', 'organization']);

        $organization = $declaration->organization ?? auth()->user()->organization;
        
        if (!$organization) {
            return redirect()->back()
                ->with('error', 'Organization not found.');
        }

        $credentials = $organization->getFtpCredentials($declaration->country_id);
        
        if (!$credentials) {
            return redirect()->route('settings.submission-credentials')
                ->with('error', 'FTP credentials not found.');
        }

        try {
            $newSubmission = $this->ftpService->submit($declaration, $credentials);

            return redirect()->route('ftp-submission.result', [
                'declaration' => $declaration,
                'submission' => $newSubmission,
            ])->with('info', 'Retry submission completed.');

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Retry failed: ' . $e->getMessage());
        }
    }

    /**
     * Manually upload attachments for an existing FTP submission.
     * Used by the "Upload Attachments via FTP" button.
     */
    public function uploadAttachments(WebFormSubmission $submission)
    {
        if (!$submission->is_ftp) {
            return redirect()->back()->with('error', 'This is not an FTP submission.');
        }

        $declaration = $submission->declaration;
        if (!$declaration) {
            return redirect()->back()->with('error', 'Declaration not found.');
        }

        $declaration->load(['country', 'organization']);
        $organization = $declaration->organization ?? auth()->user()->organization;

        if (!$organization) {
            return redirect()->back()->with('error', 'Organization not found.');
        }

        $credentials = $organization->getFtpCredentials($declaration->country_id);

        if (!$credentials || !$credentials->hasCompleteFtpCredentials()) {
            return redirect()->route('settings.submission-credentials')
                ->with('error', 'Please configure FTP credentials before uploading attachments.');
        }

        try {
            $result = $this->ftpService->uploadAttachmentsOnly($submission, $declaration, $credentials);

            $msg = sprintf(
                'Attachment upload complete: %d uploaded, %d skipped (already on server), %d failed.',
                $result['uploaded'],
                $result['skipped'],
                $result['failed']
            );

            return redirect()->back()->with(
                $result['failed'] > 0 ? 'warning' : 'success',
                $msg
            );
        } catch (\Throwable $e) {
            Log::error('FTP attachment upload failed', [
                'submission_id' => $submission->id,
                'error' => $e->getMessage(),
            ]);
            return redirect()->back()->with('error', 'Attachment upload failed: ' . $e->getMessage());
        }
    }

    /**
     * Manually poll for the CAPS attachment response file.
     * Used by the "Check Status Now" button.
     */
    public function checkAttachmentStatus(WebFormSubmission $submission)
    {
        if (!$submission->is_ftp) {
            return redirect()->back()->with('error', 'This is not an FTP submission.');
        }

        try {
            $uploader = app(\App\Services\FtpSubmission\CapsAttachmentUploader::class);
            $changed = $uploader->checkSubmissionStatus($submission);

            if ($changed) {
                return redirect()->back()->with('success', 'CAPS response received — attachment statuses updated.');
            }

            return redirect()->back()->with('info', 'No CAPS response file found yet. The system polls automatically every 15 minutes.');
        } catch (\Throwable $e) {
            Log::error('FTP attachment status check failed', [
                'submission_id' => $submission->id,
                'error' => $e->getMessage(),
            ]);
            return redirect()->back()->with('error', 'Status check failed: ' . $e->getMessage());
        }
    }

    /**
     * Manually mark an FTP submission as rejected by CAPS by uploading the
     * query report PDF (or .TXT response file). Parses it via Claude into
     * structured per-line errors and saves them on the submission.
     *
     * Workflow:
     *   1. Broker receives the CAPS query email + PDF.
     *   2. Broker visits the submission result page and uploads the PDF here.
     *   3. The system runs CapsResponseParser, persists errors, and flips
     *      caps_response_status. The Fix and Resubmit UI then becomes active.
     */
    public function markRejected(
        Request $request,
        WebFormSubmission $submission,
        CapsResponseParser $parser
    ) {
        if (!$submission->is_ftp) {
            return redirect()->back()->with('error', 'This is not an FTP submission.');
        }

        $request->validate([
            'caps_response_file' => ['required', 'file', 'mimes:pdf,txt', 'max:20480'],
        ], [
            'caps_response_file.mimes' => 'CAPS reports must be PDF or TXT files.',
        ]);

        $file = $request->file('caps_response_file');

        // Persist the file synchronously — that's the only fast step. The
        // actual Claude-based parse can take 1–5 minutes for large query
        // reports, so we hand it off to a background job and redirect
        // immediately so the broker never sees a stuck page.
        try {
            $storedPath = $parser->storeUploadedFile($file, $submission->id);
        } catch (\Throwable $e) {
            Log::error('CAPS response upload failed', [
                'submission_id' => $submission->id,
                'error' => $e->getMessage(),
            ]);
            return redirect()->back()
                ->with('error', 'Could not save the CAPS report: ' . $e->getMessage());
        }

        $submission->markCapsParsing(
            WebFormSubmission::CAPS_SOURCE_MANUAL_UPLOAD,
            $storedPath
        );

        // dispatchAfterResponse flushes the redirect to the browser first,
        // then runs the parser in the same PHP process. With a real queue
        // worker (production) this would fan out to a separate worker.
        \App\Jobs\ParseCapsResponse::dispatchAfterResponse(
            $submission->id,
            $storedPath,
            WebFormSubmission::CAPS_SOURCE_MANUAL_UPLOAD,
        );

        return redirect()->route('ftp-submission.result', [
            'declaration' => $submission->declaration_form_id,
            'submission' => $submission->id,
        ])->with(
            'success',
            'CAPS report uploaded. Parsing is running in the background — this page will refresh automatically every 20 seconds while we extract the errors. Large reports can take 2–5 minutes.'
        );
    }

    /**
     * Manually re-trigger the CAPS response parser for a submission whose
     * upload is stuck in "parsing" or whose previous parse failed. Useful in
     * dev where dispatchAfterResponse is the only "background" we have.
     */
    public function reparse(WebFormSubmission $submission)
    {
        if (!$submission->caps_response_file_path) {
            return redirect()->back()
                ->with('error', 'No CAPS report file is attached to this submission.');
        }

        $submission->markCapsParsing(
            $submission->caps_response_source ?? WebFormSubmission::CAPS_SOURCE_MANUAL_UPLOAD,
            $submission->caps_response_file_path
        );

        \App\Jobs\ParseCapsResponse::dispatchAfterResponse(
            $submission->id,
            $submission->caps_response_file_path,
            $submission->caps_response_source ?? WebFormSubmission::CAPS_SOURCE_MANUAL_UPLOAD,
        );

        return redirect()->route('ftp-submission.result', [
            'declaration' => $submission->declaration_form_id,
            'submission' => $submission->id,
        ])->with('success', 'Re-parsing the CAPS report. The page will refresh while it runs.');
    }

    /**
     * Show the Fix and Resubmit page for a CAPS-rejected submission. Builds
     * AI suggestions per affected line item.
     */
    public function showFixPage(WebFormSubmission $submission, CapsErrorAgent $agent)
    {
        if (!$submission->is_ftp) {
            return redirect()->back()->with('error', 'This is not an FTP submission.');
        }

        if ($submission->is_caps_parsing) {
            return redirect()->route('ftp-submission.result', [
                'declaration' => $submission->declaration_form_id,
                'submission' => $submission->id,
            ])->with('info', 'CAPS report is still being parsed. We\'ll show the fix page once the errors are extracted.');
        }

        if (!$submission->caps_rejected) {
            return redirect()->route('ftp-submission.result', [
                'declaration' => $submission->declaration_form_id,
                'submission' => $submission->id,
            ])->with('info', 'This submission has not been rejected by CAPS.');
        }

        $declaration = $submission->declaration;
        $declaration->loadMissing(['country', 'declarationItems']);

        $parsed = $submission->caps_response_errors ?? [];
        $suggestions = $agent->suggestFixes($declaration, $parsed);

        return view('ftp-submission.fix', [
            'submission' => $submission,
            'declaration' => $declaration,
            'parsed' => $parsed,
            'suggestions' => $suggestions,
        ]);
    }

    /**
     * Apply the broker-confirmed fixes and resubmit the declaration.
     */
    public function applyFixAndResubmit(
        Request $request,
        WebFormSubmission $submission,
        FixAndResubmitService $service
    ) {
        if (!$submission->is_ftp) {
            return redirect()->back()->with('error', 'This is not an FTP submission.');
        }

        if (!$submission->caps_rejected) {
            return redirect()->back()->with('error', 'Only CAPS-rejected submissions can be resubmitted via this flow.');
        }

        $validated = $request->validate([
            'fixes' => ['required', 'array', 'min:1'],
            'fixes.*.declaration_form_item_id' => ['nullable', 'integer'],
            'fixes.*.invoice_item_id' => ['nullable', 'integer'],
            'fixes.*.new_code' => ['required', 'string', 'max:20'],
            'auto_attach' => ['nullable', 'boolean'],
        ]);

        $declaration = $submission->declaration;
        $declaration->loadMissing(['country', 'organization', 'shipment.invoices.invoiceItems']);

        $organization = $declaration->organization ?? auth()->user()->organization;
        $credentials = $organization?->getFtpCredentials($declaration->country_id);

        if (!$credentials || !$credentials->hasCompleteFtpCredentials()) {
            return redirect()->route('settings.submission-credentials')
                ->with('error', 'FTP credentials are not configured for this country.');
        }

        $autoAttach = (bool) ($validated['auto_attach'] ?? true);

        try {
            $newSubmission = $service->apply(
                $submission,
                $declaration,
                $credentials,
                $validated['fixes'],
                $autoAttach
            );
        } catch (\Throwable $e) {
            Log::error('Fix and resubmit failed', [
                'submission_id' => $submission->id,
                'error' => $e->getMessage(),
            ]);
            return redirect()->back()
                ->withInput()
                ->with('error', 'Resubmission failed: ' . $e->getMessage());
        }

        return redirect()->route('ftp-submission.result', [
            'declaration' => $declaration->id,
            'submission' => $newSubmission->id,
        ])->with('success', 'Fixes applied and declaration resubmitted as ' . $newSubmission->external_reference . '.');
    }

    /**
     * Submit an amendment T12 (filename pattern XXXXXXDDMMYYYYA.SSS) for a
     * declaration whose original submission was already accepted by CAPS.
     * Used when the broker needs to correct a declaration after acceptance.
     */
    public function submitAmendment(WebFormSubmission $submission)
    {
        if (!$submission->is_ftp) {
            return redirect()->back()->with('error', 'This is not an FTP submission.');
        }

        if (!$submission->caps_accepted) {
            return redirect()->back()
                ->with('error', 'Amendments can only be filed after CAPS has accepted the original submission.');
        }

        $declaration = $submission->declaration;
        $declaration->loadMissing(['country', 'organization']);

        $organization = $declaration->organization ?? auth()->user()->organization;
        $credentials = $organization?->getFtpCredentials($declaration->country_id);

        if (!$credentials || !$credentials->hasCompleteFtpCredentials()) {
            return redirect()->route('settings.submission-credentials')
                ->with('error', 'FTP credentials are not configured for this country.');
        }

        try {
            $newSubmission = $this->ftpService->submit(
                $declaration,
                $credentials,
                true,   // saveLocally
                true,   // autoAttach
                true    // isAmendment
            );

            $newSubmission->update([
                'parent_submission_id' => $submission->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Amendment submission failed', [
                'submission_id' => $submission->id,
                'error' => $e->getMessage(),
            ]);
            return redirect()->back()
                ->with('error', 'Amendment submission failed: ' . $e->getMessage());
        }

        return redirect()->route('ftp-submission.result', [
            'declaration' => $declaration->id,
            'submission' => $newSubmission->id,
        ])->with('success', 'Amendment ' . $newSubmission->external_reference . ' submitted successfully.');
    }
}
