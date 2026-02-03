<?php

namespace App\Http\Controllers;

use App\Models\DeclarationForm;
use App\Models\OrganizationSubmissionCredential;
use App\Models\WebFormSubmission;
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

        // Validate declaration data
        $validation = $this->ftpService->validate($declaration);

        // Generate preview
        try {
            $preview = $this->ftpService->preview($declaration, $credentials);
        } catch (\Exception $e) {
            Log::error('T12 preview generation failed', [
                'declaration_id' => $declaration->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to generate T12 preview: ' . $e->getMessage());
        }

        return view('ftp-submission.preview', compact(
            'declaration',
            'country',
            'credentials',
            'preview',
            'validation'
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

        // Validate first
        $validation = $this->ftpService->validate($declaration);
        
        if (!$validation['valid'] && !$request->has('force')) {
            return redirect()->back()
                ->with('error', 'Validation failed: ' . implode(', ', $validation['errors']));
        }

        try {
            $submission = $this->ftpService->submit($declaration, $credentials);

            if ($submission->is_successful) {
                return redirect()->route('ftp-submission.result', [
                    'declaration' => $declaration,
                    'submission' => $submission,
                ])->with('success', 'Declaration submitted via FTP! Reference: ' . $submission->external_reference);
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
}
