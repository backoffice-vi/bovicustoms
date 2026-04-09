<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessCapsDownload;
use App\Jobs\ProcessCapsInvoices;
use App\Models\CapsImport;
use App\Models\Country;
use App\Models\Organization;
use App\Models\OrganizationSubmissionCredential;
use App\Services\CapsImportService;
use Illuminate\Http\Request;

class CapsImportController extends Controller
{
    public function __construct(protected CapsImportService $service)
    {
    }

    public function index()
    {
        $user = auth()->user();
        $bviCountry = $this->getBviCountry();

        if (!$bviCountry) {
            return view('caps-import.index', [
                'imports' => collect(),
                'bviCountry' => null,
                'capsCredential' => null,
                'capsConfigured' => false,
                'statusCounts' => $this->emptyStatusCounts(),
            ]);
        }

        $imports = CapsImport::where('organization_id', $user->organization_id)
            ->orderByDesc('created_at')
            ->get();

        $capsCredential = $this->getCapsCredential($user, $bviCountry->id);
        $capsConfigured = $capsCredential?->hasCompleteCapsCredentials() ?? false;

        // Fall back to .env for admin
        if (!$capsConfigured && $user->isAdmin() && !empty(config('services.caps.username'))) {
            $capsConfigured = true;
        }

        $statusCounts = [
            'total' => $imports->count(),
            'pending' => $imports->where('status', CapsImport::STATUS_PENDING)->count(),
            'downloaded' => $imports->where('status', CapsImport::STATUS_DOWNLOADED)->count(),
            'imported' => $imports->where('status', CapsImport::STATUS_IMPORTED)->count(),
            'completed' => $imports->where('status', CapsImport::STATUS_COMPLETED)->count(),
            'failed' => $imports->where('status', CapsImport::STATUS_FAILED)->count(),
            'processing' => $imports->whereIn('status', [
                CapsImport::STATUS_DOWNLOADING,
                CapsImport::STATUS_IMPORTING,
                CapsImport::STATUS_PROCESSING_INVOICES,
            ])->count(),
        ];

        return view('caps-import.index', compact('imports', 'bviCountry', 'capsCredential', 'capsConfigured', 'statusCounts'));
    }

    public function saveCredentials(Request $request)
    {
        $user = $request->user();
        $bviCountry = $this->getBviCountry();

        if (!$bviCountry) {
            return back()->with('error', 'BVI country not found in system.');
        }

        $orgId = $user->organization_id;
        if (!$orgId && $user->isAdmin()) {
            $orgId = Organization::first()?->id;
        }

        if (!$orgId) {
            return back()->with('error', 'No organization found. Please contact support.');
        }

        $credential = OrganizationSubmissionCredential::firstOrNew([
            'organization_id' => $orgId,
            'country_id' => $bviCountry->id,
            'credential_type' => OrganizationSubmissionCredential::TYPE_CAPS,
        ]);

        $isNew = !$credential->exists;

        $request->validate([
            'caps_username' => 'required|string|max:255',
            'caps_password' => [$isNew ? 'required' : 'nullable', 'string', 'max:255'],
        ]);

        $existingCreds = $credential->getCapsCredentials();
        $password = $request->caps_password ?: ($existingCreds['password'] ?? '');

        $credential->credentials = [
            'username' => $request->caps_username,
            'password' => $password,
            'url' => 'https://caps.gov.vg',
        ];
        $credential->is_active = true;
        $credential->display_name = 'CAPS - British Virgin Islands';
        $credential->save();

        return back()->with('success', 'CAPS credentials saved successfully.');
    }

    public function fetchTDs(Request $request)
    {
        $user = $request->user();
        $bviCountry = $this->getBviCountry();

        if (!$bviCountry) {
            return back()->with('error', 'BVI country not configured.');
        }

        try {
            $result = $this->service->fetchTDList($user, $bviCountry->id);

            return back()->with('success',
                "Found {$result['total']} TD(s). {$result['created']} new, {$result['existing']} already tracked."
            );
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to fetch TD list: ' . $e->getMessage());
        }
    }

    public function downloadAll(Request $request)
    {
        $user = $request->user();

        $pending = CapsImport::where('organization_id', $user->organization_id)
            ->where('status', CapsImport::STATUS_PENDING)
            ->get();

        if ($pending->isEmpty()) {
            return back()->with('info', 'No pending TDs to download.');
        }

        foreach ($pending as $import) {
            ProcessCapsDownload::dispatch($import);
        }

        return back()->with('success', "Queued {$pending->count()} TD(s) for download.");
    }

    public function downloadSingle(Request $request, CapsImport $capsImport)
    {
        $this->authorizeImport($capsImport);

        if (in_array($capsImport->status, [CapsImport::STATUS_DOWNLOADING, CapsImport::STATUS_IMPORTING, CapsImport::STATUS_PROCESSING_INVOICES])) {
            return back()->with('info', 'This TD is already being processed.');
        }

        $capsImport->update(['status' => CapsImport::STATUS_PENDING, 'error_message' => null]);
        ProcessCapsDownload::dispatch($capsImport);

        return back()->with('success', "TD {$capsImport->td_number} queued for download.");
    }

    public function importAll(Request $request)
    {
        $user = $request->user();

        $downloaded = CapsImport::where('organization_id', $user->organization_id)
            ->where('status', CapsImport::STATUS_DOWNLOADED)
            ->get();

        if ($downloaded->isEmpty()) {
            return back()->with('info', 'No downloaded TDs to import.');
        }

        $imported = 0;
        $failed = 0;

        foreach ($downloaded as $import) {
            try {
                if ($this->service->importTD($import)) {
                    $imported++;
                } else {
                    $failed++;
                }
            } catch (\Throwable $e) {
                $import->markAs(CapsImport::STATUS_FAILED, $e->getMessage());
                $failed++;
            }
        }

        return back()->with('success', "Imported {$imported} TD(s). {$failed} failed.");
    }

    public function processInvoices(Request $request)
    {
        $user = $request->user();

        $imported = CapsImport::where('organization_id', $user->organization_id)
            ->where('status', CapsImport::STATUS_IMPORTED)
            ->whereNotNull('attachments')
            ->get()
            ->filter(fn($i) => count($i->attachments ?? []) > 0);

        if ($imported->isEmpty()) {
            return back()->with('info', 'No imported TDs with invoice attachments to process.');
        }

        foreach ($imported as $import) {
            ProcessCapsInvoices::dispatch($import);
        }

        return back()->with('success', "Queued {$imported->count()} TD(s) for invoice processing.");
    }

    public function processInvoicesSingle(Request $request, CapsImport $capsImport)
    {
        $this->authorizeImport($capsImport);

        if (!in_array($capsImport->status, [CapsImport::STATUS_IMPORTED, CapsImport::STATUS_FAILED])) {
            return back()->with('info', 'TD must be imported before processing invoices.');
        }

        ProcessCapsInvoices::dispatch($capsImport);

        return back()->with('success', "TD {$capsImport->td_number} queued for invoice processing.");
    }

    public function retryFailed(Request $request)
    {
        $user = $request->user();

        $failed = CapsImport::where('organization_id', $user->organization_id)
            ->where('status', CapsImport::STATUS_FAILED)
            ->get();

        if ($failed->isEmpty()) {
            return back()->with('info', 'No failed TDs to retry.');
        }

        $maxRetries = config('services.caps.max_retries', 3);
        $retried = 0;
        $skipped = 0;

        foreach ($failed as $import) {
            if ($import->retry_count >= $maxRetries) {
                $skipped++;
                continue;
            }

            $import->update(['status' => CapsImport::STATUS_PENDING, 'error_message' => null]);
            ProcessCapsDownload::dispatch($import);
            $retried++;
        }

        $msg = "Retrying {$retried} TD(s).";
        if ($skipped > 0) {
            $msg .= " {$skipped} skipped (max retries reached).";
        }

        return back()->with('success', $msg);
    }

    public function status(Request $request)
    {
        $user = $request->user();

        $imports = CapsImport::where('organization_id', $user->organization_id)
            ->orderByDesc('created_at')
            ->get();

        $statusCounts = [
            'total' => $imports->count(),
            'pending' => $imports->where('status', CapsImport::STATUS_PENDING)->count(),
            'downloaded' => $imports->where('status', CapsImport::STATUS_DOWNLOADED)->count(),
            'imported' => $imports->where('status', CapsImport::STATUS_IMPORTED)->count(),
            'completed' => $imports->where('status', CapsImport::STATUS_COMPLETED)->count(),
            'failed' => $imports->where('status', CapsImport::STATUS_FAILED)->count(),
        ];

        return response()->json([
            'status_counts' => $statusCounts,
            'imports' => $imports->map(fn($i) => [
                'id' => $i->id,
                'td_number' => $i->td_number,
                'status' => $i->status,
                'status_label' => CapsImport::statusLabel($i->status),
                'items_count' => $i->items_count,
                'attachments_count' => count($i->attachments ?? []),
                'retry_count' => $i->retry_count,
                'error_message' => $i->error_message,
                'ai_diagnosis' => $i->getAiDiagnosisText(),
                'ai_recommendations' => $i->getAiRecommendations(),
                'error_categories' => $i->getErrorCategories(),
                'can_retry' => $i->ai_diagnosis['can_retry'] ?? false,
            ]),
        ]);
    }

    protected function authorizeImport(CapsImport $capsImport): void
    {
        $user = auth()->user();
        if ($user->isAdmin()) return;
        if ($capsImport->organization_id !== $user->organization_id) {
            abort(403);
        }
    }

    protected function getBviCountry(): ?Country
    {
        return Country::where('code', 'VGB')
            ->orWhere('name', 'like', '%British Virgin%')
            ->first();
    }

    protected function getCapsCredential($user, int $countryId): ?OrganizationSubmissionCredential
    {
        $orgId = $user->organization_id;
        if (!$orgId && $user->isAdmin()) {
            $orgId = Organization::first()?->id;
        }
        if (!$orgId) return null;

        return OrganizationSubmissionCredential::where('organization_id', $orgId)
            ->forCaps()
            ->forCountry($countryId)
            ->active()
            ->first();
    }

    protected function emptyStatusCounts(): array
    {
        return [
            'total' => 0, 'pending' => 0, 'downloaded' => 0,
            'imported' => 0, 'completed' => 0, 'failed' => 0, 'processing' => 0,
        ];
    }
}
