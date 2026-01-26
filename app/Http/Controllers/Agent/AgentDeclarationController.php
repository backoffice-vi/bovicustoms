<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\DeclarationForm;
use App\Models\SubmissionAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class AgentDeclarationController extends Controller
{
    /**
     * Display list of declarations for agent's clients
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $clientIds = $user->getAgentClientIds();
        
        // Check if filtering by a specific client
        $selectedClientId = session('agent_selected_client_id');
        
        $query = DeclarationForm::withoutGlobalScope('tenant')
            ->whereIn('organization_id', $clientIds)
            ->with(['organization', 'country', 'submittedBy']);

        // Apply client filter if selected
        if ($selectedClientId && in_array($selectedClientId, $clientIds)) {
            $query->where('organization_id', $selectedClientId);
        }

        // Apply status filter
        if ($request->filled('status')) {
            $query->where('submission_status', $request->status);
        }

        // Apply search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('form_number', 'like', "%{$search}%")
                  ->orWhere('bill_of_lading_number', 'like', "%{$search}%")
                  ->orWhere('manifest_number', 'like', "%{$search}%");
            });
        }

        $declarations = $query->latest()->paginate(15);

        // Get filter options
        $statuses = DeclarationForm::getSubmissionStatuses();
        $clients = $user->activeAgentClients()->get();

        return view('agent.declarations.index', compact(
            'declarations',
            'statuses',
            'clients',
            'selectedClientId'
        ));
    }

    /**
     * Display a single declaration
     */
    public function show(DeclarationForm $declaration)
    {
        $user = auth()->user();
        
        // Verify agent has access
        if (!$user->hasAccessToOrganization($declaration->organization_id)) {
            abort(403, 'You do not have access to this declaration.');
        }

        $declaration->load([
            'organization',
            'country',
            'shipment',
            'invoice',
            'declarationItems',
            'submissionAttachments.uploadedBy',
            'submittedBy',
            'shipperContact',
            'consigneeContact',
        ]);

        return view('agent.declarations.show', compact('declaration'));
    }

    /**
     * Show the submission form
     */
    public function showSubmitForm(DeclarationForm $declaration)
    {
        $user = auth()->user();
        
        // Verify agent has access
        if (!$user->hasAccessToOrganization($declaration->organization_id)) {
            abort(403, 'You do not have access to this declaration.');
        }

        // Check if can be submitted
        if (!$declaration->canBeSubmitted()) {
            return redirect()->route('agent.declarations.show', $declaration)
                ->with('error', 'This declaration cannot be submitted. Current status: ' . $declaration->submission_status_label);
        }

        $declaration->load([
            'organization',
            'country',
            'submissionAttachments',
            'declarationItems',
        ]);

        $documentTypes = SubmissionAttachment::getDocumentTypes();

        return view('agent.declarations.submit', compact('declaration', 'documentTypes'));
    }

    /**
     * Process the submission
     */
    public function submit(Request $request, DeclarationForm $declaration)
    {
        $user = auth()->user();
        
        // Verify agent has access
        if (!$user->hasAccessToOrganization($declaration->organization_id)) {
            abort(403, 'You do not have access to this declaration.');
        }

        // Check if can be submitted
        if (!$declaration->canBeSubmitted()) {
            return redirect()->route('agent.declarations.show', $declaration)
                ->with('error', 'This declaration cannot be submitted.');
        }

        $validated = $request->validate([
            'submission_reference' => 'nullable|string|max:100',
            'submission_notes' => 'nullable|string|max:2000',
        ]);

        // Submit the declaration
        $declaration->submit(
            $user,
            $validated['submission_reference'] ?? null,
            $validated['submission_notes'] ?? null
        );

        return redirect()->route('agent.declarations.show', $declaration)
            ->with('success', 'Declaration submitted successfully!');
    }

    /**
     * Upload an attachment
     */
    public function uploadAttachment(Request $request, DeclarationForm $declaration): JsonResponse
    {
        $user = auth()->user();
        
        // Verify agent has access
        if (!$user->hasAccessToOrganization($declaration->organization_id)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'max:' . (SubmissionAttachment::getMaxFileSize() / 1024), // Convert to KB
                'mimes:' . implode(',', SubmissionAttachment::getAllowedExtensions()),
            ],
            'document_type' => 'required|in:' . implode(',', array_keys(SubmissionAttachment::getDocumentTypes())),
            'description' => 'nullable|string|max:255',
        ]);

        try {
            $attachment = SubmissionAttachment::storeFile(
                $declaration,
                $user,
                $request->file('file'),
                $validated['document_type'],
                $validated['description'] ?? null
            );

            return response()->json([
                'success' => true,
                'attachment' => [
                    'id' => $attachment->id,
                    'file_name' => $attachment->file_name,
                    'document_type' => $attachment->document_type,
                    'document_type_label' => $attachment->document_type_label,
                    'formatted_file_size' => $attachment->formatted_file_size,
                    'file_icon' => $attachment->file_icon,
                    'created_at' => $attachment->created_at->format('M d, Y H:i'),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to upload file: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete an attachment
     */
    public function deleteAttachment(SubmissionAttachment $attachment): JsonResponse
    {
        $user = auth()->user();
        $declaration = $attachment->declarationForm;
        
        // Verify agent has access
        if (!$user->hasAccessToOrganization($declaration->organization_id)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Don't allow deletion if declaration is already submitted
        if ($declaration->isSubmitted()) {
            return response()->json([
                'error' => 'Cannot delete attachments from submitted declarations.',
            ], 400);
        }

        try {
            $attachment->deleteWithFile();
            
            return response()->json([
                'success' => true,
                'message' => 'Attachment deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to delete attachment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download an attachment
     */
    public function downloadAttachment(SubmissionAttachment $attachment)
    {
        $user = auth()->user();
        $declaration = $attachment->declarationForm;
        
        // Verify agent has access
        if (!$user->hasAccessToOrganization($declaration->organization_id)) {
            abort(403, 'Unauthorized');
        }

        if (!$attachment->fileExists()) {
            abort(404, 'File not found');
        }

        return Storage::disk('local')->download(
            $attachment->file_path,
            $attachment->file_name
        );
    }

    /**
     * Mark declaration as ready for submission
     */
    public function markReady(DeclarationForm $declaration)
    {
        $user = auth()->user();
        
        // Verify agent has access
        if (!$user->hasAccessToOrganization($declaration->organization_id)) {
            abort(403, 'Unauthorized');
        }

        if ($declaration->submission_status !== DeclarationForm::SUBMISSION_STATUS_DRAFT) {
            return redirect()->back()->with('error', 'Only draft declarations can be marked as ready.');
        }

        $declaration->markReady();

        return redirect()->back()->with('success', 'Declaration marked as ready for submission.');
    }
}
