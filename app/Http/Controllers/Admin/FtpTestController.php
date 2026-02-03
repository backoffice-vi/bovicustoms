<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\DeclarationForm;
use App\Models\OrganizationSubmissionCredential;
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

        // Create temporary credential for download
        $tempCredential = new OrganizationSubmissionCredential([
            'credential_type' => 'ftp',
            'trader_id' => $traderId,
            'credentials' => [
                'trader_id' => $traderId,
                'username' => '',
                'password' => '',
            ],
        ]);

        try {
            // The generator handles its own relationship loading with proper scope bypassing
            $result = $this->t12Generator->generate($declaration, $tempCredential);
            $filename = $this->t12Generator->generateFilename($traderId);

            return response($result['content'])
                ->header('Content-Type', 'text/plain')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
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
            'trader_id' => $validated['trader_id'],
            'credentials' => [
                'username' => $validated['username'],
                'password' => $validated['password'],
                'trader_id' => $validated['trader_id'],
            ],
        ]);

        try {
            $result = $this->ftpService->submit($declaration, $tempCredential);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'] ?? ($result['success'] ? 'FTP submission successful!' : 'FTP submission failed'),
                'filename' => $result['filename'] ?? null,
                'submission_id' => $result['submission_id'] ?? null,
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
}
