<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\DeclarationForm;
use App\Models\OrganizationSubmissionCredential;
use App\Models\WebFormSubmission;
use App\Services\FtpSubmission\CapsAttachmentUploader;
use App\Services\FtpSubmission\CapsT12Generator;
use App\Services\FtpSubmission\FtpSubmissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FtpTestController extends Controller
{
    protected FtpSubmissionService $ftpService;
    protected CapsT12Generator $t12Generator;

    public function __construct(FtpSubmissionService $ftpService, CapsT12Generator $t12Generator)
    {
        $this->ftpService = $ftpService;
        $this->t12Generator = $t12Generator;
    }

    /**
     * Show the FTP testing page
     */
    public function index()
    {
        // Get countries with FTP enabled
        $countries = Country::where('ftp_enabled', true)
            ->orderBy('name')
            ->get();

        // Get all organization credentials for reference
        $orgCredentials = OrganizationSubmissionCredential::with(['organization', 'country'])
            ->where('credential_type', 'ftp')
            ->orderBy('organization_id')
            ->get();

        // Get recent declarations for testing (using DeclarationForm which has the actual declaration data)
        $declarations = DeclarationForm::withoutGlobalScopes()
            ->with(['country', 'organization', 'invoice', 'consigneeContact', 'shipment.consigneeContact'])
            ->whereHas('country', function ($q) {
                $q->where('ftp_enabled', true);
            })
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('admin.ftp-test.index', compact('countries', 'orgCredentials', 'declarations'));
    }

    /**
     * Test FTP connection with provided credentials
     */
    public function testConnection(Request $request)
    {
        $validated = $request->validate([
            'country_id' => 'required|exists:countries,id',
            'username' => 'required|string',
            'password' => 'required|string',
            'trader_id' => 'required|string|max:10',
        ]);

        $country = Country::findOrFail($validated['country_id']);

        if (!$country->isFtpEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'FTP is not enabled for this country.',
            ]);
        }

        // Create a temporary credential object for testing
        $tempCredential = new OrganizationSubmissionCredential([
            'credential_type' => 'ftp',
            'trader_id' => $validated['trader_id'],
            'credentials' => [
                'username' => $validated['username'],
                'password' => $validated['password'],
                'trader_id' => $validated['trader_id'],
            ],
        ]);

        try {
            $result = $this->ftpService->testConnection($country, $tempCredential);
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Admin FTP test failed', [
                'country_id' => $country->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Preview T12 file for a declaration
     */
    public function previewT12(Request $request)
    {
        $validated = $request->validate([
            'declaration_id' => 'required|exists:declaration_forms,id',
            'trader_id' => 'required|string|max:10',
            'declarant_name' => 'nullable|string|max:30',
        ]);

        $declaration = DeclarationForm::withoutGlobalScopes()
            ->with([
                'declarationItems' => fn($q) => $q->withoutGlobalScopes(),
                'invoice.invoiceItems' => fn($q) => $q->withoutGlobalScopes(),
                'country',
                'organization',
                'shipment.shipperContact',
                'shipment.consigneeContact',
            ])->findOrFail($validated['declaration_id']);

        // Create temporary credential for preview
        $tempCredential = new OrganizationSubmissionCredential([
            'credential_type' => 'ftp',
            'trader_id' => $validated['trader_id'],
            'credentials' => [
                'trader_id' => $validated['trader_id'],
                'declarant_name' => $validated['declarant_name'] ?? '',
                'username' => '',
                'password' => '',
            ],
        ]);

        try {
            $preview = $this->t12Generator->preview($declaration, $tempCredential);
            
            return response()->json([
                'success' => true,
                'preview' => $preview,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate T12 preview: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Download T12 file for a declaration
     */
    public function downloadT12(Request $request, DeclarationForm $declaration)
    {
        $traderId = $request->input('trader_id', '000000');
        $declarantName = $request->input('declarant_name', '');

        // Create temporary credential for download
        $tempCredential = new OrganizationSubmissionCredential([
            'credential_type' => 'ftp',
            'trader_id' => $traderId,
            'credentials' => [
                'trader_id' => $traderId,
                'declarant_name' => $declarantName,
                'username' => '',
                'password' => '',
            ],
        ]);

        try {
            // The generator handles its own relationship loading with proper scope bypassing
            $result = $this->t12Generator->generate($declaration, $tempCredential);

            return response($result['content'])
                ->header('Content-Type', 'text/plain')
                ->header('Content-Disposition', 'attachment; filename="' . $result['filename'] . '"');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to generate T12 file: ' . $e->getMessage());
        }
    }

    /**
     * Submit declaration via FTP (admin test)
     */
    public function submit(Request $request)
    {
        $validated = $request->validate([
            'declaration_id' => 'required|exists:declaration_forms,id',
            'country_id' => 'required|exists:countries,id',
            'username' => 'required|string',
            'password' => 'required|string',
            'trader_id' => 'required|string|max:10',
            'declarant_name' => 'nullable|string|max:30',
        ]);

        $declaration = DeclarationForm::withoutGlobalScopes()
            ->with([
                'declarationItems' => fn($q) => $q->withoutGlobalScopes(),
                'invoice.invoiceItems' => fn($q) => $q->withoutGlobalScopes(),
                'country',
                'organization',
                'shipment.shipperContact',
                'shipment.consigneeContact',
            ])->findOrFail($validated['declaration_id']);

        $country = Country::findOrFail($validated['country_id']);

        if (!$country->isFtpEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'FTP is not enabled for this country.',
            ]);
        }

        // Create a temporary credential object
        $tempCredential = new OrganizationSubmissionCredential([
            'credential_type' => 'ftp',
            'trader_id' => $validated['trader_id'],
            'credentials' => [
                'username' => $validated['username'],
                'password' => $validated['password'],
                'trader_id' => $validated['trader_id'],
                'declarant_name' => $validated['declarant_name'] ?? '',
            ],
        ]);

        try {
            $submission = $this->ftpService->submit($declaration, $tempCredential);

            return response()->json([
                'success' => $submission->is_successful,
                'message' => $submission->is_successful ? 'FTP submission successful!' : ('FTP submission failed: ' . $submission->error_message),
                'filename' => $submission->external_reference,
                'submission_id' => $submission->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Admin FTP submission failed', [
                'declaration_id' => $declaration->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Submission failed: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Get organization credentials for a country (AJAX)
     */
    public function getCredentials(Request $request)
    {
        $countryId = $request->input('country_id');

        $credentials = OrganizationSubmissionCredential::with('organization')
            ->where('country_id', $countryId)
            ->where('credential_type', 'ftp')
            ->where('is_active', true)
            ->get()
            ->map(function ($cred) {
                return [
                    'id' => $cred->id,
                    'organization_name' => $cred->organization->name ?? 'Unknown',
                    'trader_id' => $cred->trader_id,
                    'display_name' => $cred->display_name,
                    'last_tested_at' => $cred->last_tested_at?->format('Y-m-d H:i'),
                ];
            });

        return response()->json(['credentials' => $credentials]);
    }

    /**
     * Upload attachments for an existing submission (admin test).
     */
    public function uploadAttachments(WebFormSubmission $submission)
    {
        $declaration = $submission->declaration;
        if (!$declaration) {
            return response()->json(['success' => false, 'message' => 'Declaration not found'], 404);
        }

        $declaration->load(['country', 'organization']);
        $organization = $declaration->organization;

        if (!$organization) {
            return response()->json(['success' => false, 'message' => 'Organization not found'], 404);
        }

        $credentials = $organization->getFtpCredentials($declaration->country_id);

        if (!$credentials || !$credentials->hasCompleteFtpCredentials()) {
            return response()->json(['success' => false, 'message' => 'FTP credentials are missing or incomplete']);
        }

        try {
            $result = $this->ftpService->uploadAttachmentsOnly($submission, $declaration, $credentials);
            return response()->json([
                'success' => true,
                'uploaded' => $result['uploaded'],
                'skipped' => $result['skipped'],
                'failed' => $result['failed'],
                'message' => sprintf(
                    'Attachment upload complete: %d uploaded, %d skipped, %d failed.',
                    $result['uploaded'],
                    $result['skipped'],
                    $result['failed']
                ),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Manually poll attachment response (admin test).
     */
    public function checkAttachmentStatus(WebFormSubmission $submission, CapsAttachmentUploader $uploader)
    {
        try {
            $changed = $uploader->checkSubmissionStatus($submission);
            return response()->json([
                'success' => true,
                'updated' => $changed,
                'message' => $changed
                    ? 'CAPS response received — statuses updated.'
                    : 'No CAPS response file found yet.',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
