<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\OrganizationSubmissionCredential;
use App\Models\WebFormTarget;
use App\Services\FtpSubmission\FtpSubmissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrganizationCredentialController extends Controller
{
    protected FtpSubmissionService $ftpService;

    public function __construct(FtpSubmissionService $ftpService)
    {
        $this->ftpService = $ftpService;
    }

    /**
     * Display a listing of the organization's credentials
     */
    public function index()
    {
        $user = auth()->user();
        $organization = $user->organization;

        if (!$organization) {
            return redirect()->route('dashboard')
                ->with('error', 'You must be part of an organization to manage submission credentials.');
        }

        $credentials = OrganizationSubmissionCredential::where('organization_id', $organization->id)
            ->with(['country', 'webFormTarget'])
            ->orderBy('country_id')
            ->orderBy('credential_type')
            ->get();

        // Get available countries for adding new credentials
        $countries = Country::active()
            ->whereHas('webFormTargets', function ($q) {
                $q->active();
            })
            ->orWhere('ftp_enabled', true)
            ->orderBy('name')
            ->get();

        // Get web form targets grouped by country
        $webTargets = WebFormTarget::active()
            ->with('country')
            ->get()
            ->groupBy('country_id');

        return view('settings.submission-credentials', compact(
            'credentials',
            'countries',
            'webTargets',
            'organization'
        ));
    }

    /**
     * Store a newly created credential
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        $organization = $user->organization;

        if (!$organization) {
            return redirect()->back()->with('error', 'Organization not found.');
        }

        $validated = $request->validate([
            'country_id' => 'required|exists:countries,id',
            'credential_type' => 'required|in:ftp,web',
            'web_form_target_id' => 'nullable|exists:web_form_targets,id',
            'display_name' => 'nullable|string|max:255',
            'trader_id' => 'required_if:credential_type,ftp|nullable|string|max:10',
            'username' => 'required|string|max:255',
            'password' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Check for existing credential
        $existing = OrganizationSubmissionCredential::where('organization_id', $organization->id)
            ->where('country_id', $validated['country_id'])
            ->where('credential_type', $validated['credential_type'])
            ->where('web_form_target_id', $validated['web_form_target_id'] ?? null)
            ->first();

        if ($existing) {
            return redirect()->back()
                ->with('error', 'A credential for this country and type already exists. Please edit the existing one.');
        }

        // Build credentials array
        $credentials = [
            'username' => $validated['username'],
            'password' => $validated['password'],
        ];

        if ($validated['credential_type'] === 'ftp') {
            $credentials['trader_id'] = $validated['trader_id'];
            $credentials['email'] = $validated['email'] ?? '';
        } else {
            // Web credentials may need field selectors
            $credentials['username_field'] = $request->input('username_field', 'input[name="username"]');
            $credentials['password_field'] = $request->input('password_field', 'input[name="password"]');
            $credentials['submit_selector'] = $request->input('submit_selector', 'button[type="submit"]');
        }

        OrganizationSubmissionCredential::create([
            'organization_id' => $organization->id,
            'country_id' => $validated['country_id'],
            'credential_type' => $validated['credential_type'],
            'web_form_target_id' => $validated['web_form_target_id'] ?? null,
            'trader_id' => $validated['trader_id'] ?? null,
            'display_name' => $validated['display_name'],
            'credentials' => $credentials,
            'notes' => $validated['notes'],
            'is_active' => true,
        ]);

        return redirect()->route('settings.submission-credentials')
            ->with('success', 'Submission credentials saved successfully.');
    }

    /**
     * Update the specified credential
     */
    public function update(Request $request, OrganizationSubmissionCredential $credential)
    {
        $user = auth()->user();
        
        // Verify ownership
        if ($credential->organization_id !== $user->organization_id) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'display_name' => 'nullable|string|max:255',
            'trader_id' => 'nullable|string|max:10',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'notes' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        // Get existing credentials
        $existingCreds = $credential->decrypted_credentials ?? [];

        // Update only provided fields
        if (!empty($validated['username'])) {
            $existingCreds['username'] = $validated['username'];
        }
        if (!empty($validated['password'])) {
            $existingCreds['password'] = $validated['password'];
        }
        if ($credential->isFtp()) {
            if (!empty($validated['trader_id'])) {
                $existingCreds['trader_id'] = $validated['trader_id'];
            }
            if (isset($validated['email'])) {
                $existingCreds['email'] = $validated['email'];
            }
        }

        $credential->update([
            'display_name' => $validated['display_name'] ?? $credential->display_name,
            'trader_id' => $validated['trader_id'] ?? $credential->trader_id,
            'credentials' => $existingCreds,
            'notes' => $validated['notes'] ?? $credential->notes,
            'is_active' => $validated['is_active'] ?? $credential->is_active,
        ]);

        return redirect()->route('settings.submission-credentials')
            ->with('success', 'Credentials updated successfully.');
    }

    /**
     * Remove the specified credential
     */
    public function destroy(OrganizationSubmissionCredential $credential)
    {
        $user = auth()->user();
        
        // Verify ownership
        if ($credential->organization_id !== $user->organization_id) {
            abort(403, 'Unauthorized');
        }

        $credential->delete();

        return redirect()->route('settings.submission-credentials')
            ->with('success', 'Credentials deleted successfully.');
    }

    /**
     * Test the credentials
     */
    public function test(OrganizationSubmissionCredential $credential)
    {
        $user = auth()->user();
        
        // Verify ownership
        if ($credential->organization_id !== $user->organization_id) {
            abort(403, 'Unauthorized');
        }

        if ($credential->isFtp()) {
            return $this->testFtpCredentials($credential);
        } else {
            return $this->testWebCredentials($credential);
        }
    }

    /**
     * Test FTP credentials
     */
    protected function testFtpCredentials(OrganizationSubmissionCredential $credential)
    {
        $country = $credential->country;

        if (!$country || !$country->isFtpEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'FTP is not enabled for this country.',
            ]);
        }

        $result = $this->ftpService->testConnection($country, $credential);

        if ($result['success']) {
            $credential->markTested();
        }

        return response()->json($result);
    }

    /**
     * Test web credentials (placeholder - would need Playwright integration)
     */
    protected function testWebCredentials(OrganizationSubmissionCredential $credential)
    {
        // For now, just validate the credentials are complete
        if (!$credential->hasCompleteWebCredentials()) {
            return response()->json([
                'success' => false,
                'message' => 'Web credentials are incomplete. Please provide username and password.',
            ]);
        }

        // Mark as tested (actual test would require Playwright)
        $credential->markTested();

        return response()->json([
            'success' => true,
            'message' => 'Credentials appear valid. Full test requires submission attempt.',
        ]);
    }

    /**
     * Test connection with provided credentials before saving
     */
    public function testUnsavedConnection(Request $request)
    {
        $user = auth()->user();
        $organization = $user->organization;

        if (!$organization) {
            return response()->json([
                'success' => false,
                'message' => 'You must be part of an organization to test credentials.',
            ]);
        }

        $validated = $request->validate([
            'country_id' => 'required|exists:countries,id',
            'credential_type' => 'required|in:ftp,web',
            'username' => 'required|string',
            'password' => 'required|string',
            'trader_id' => 'nullable|required_if:credential_type,ftp|string|max:10',
        ]);

        $country = Country::findOrFail($validated['country_id']);

        if ($validated['credential_type'] === 'ftp') {
            if (!$country->isFtpEnabled()) {
                return response()->json([
                    'success' => false,
                    'message' => 'FTP is not enabled for this country.',
                ]);
            }

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
                Log::error('FTP test connection failed', [
                    'organization_id' => $organization->id,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Connection test failed: ' . $e->getMessage(),
                ]);
            }
        }

        // Web credential test (placeholder)
        return response()->json([
            'success' => true,
            'message' => 'Web credentials format appears valid. Full verification requires a submission attempt.',
        ]);
    }

    /**
     * Get web form targets for a country (AJAX)
     */
    public function getTargetsForCountry(Request $request)
    {
        $countryId = $request->input('country_id');
        
        $targets = WebFormTarget::active()
            ->where('country_id', $countryId)
            ->get(['id', 'name', 'code']);

        $country = Country::find($countryId);
        $ftpEnabled = $country ? $country->isFtpEnabled() : false;

        return response()->json([
            'targets' => $targets,
            'ftp_enabled' => $ftpEnabled,
        ]);
    }
}
