<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\ShippingDocument;
use App\Models\TradeContact;
use App\Services\ShippingDocumentExtractor;
use App\Services\ContactMatchingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ShippingDocumentController extends Controller
{
    protected ShippingDocumentExtractor $extractor;
    protected ContactMatchingService $contactMatcher;

    public function __construct(
        ShippingDocumentExtractor $extractor,
        ContactMatchingService $contactMatcher
    ) {
        $this->extractor = $extractor;
        $this->contactMatcher = $contactMatcher;
    }

    /**
     * Upload a shipping document to a shipment
     */
    public function store(Request $request, Shipment $shipment)
    {
        $validated = $request->validate([
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:20480', // 20MB max
            'document_type' => 'required|in:' . implode(',', array_keys(ShippingDocument::getDocumentTypes())),
        ]);

        $file = $request->file('document');
        $user = auth()->user();

        // Generate unique filename
        $extension = $file->getClientOriginalExtension();
        $filename = Str::uuid() . '.' . $extension;
        
        // Store the file
        $path = $file->storeAs('shipping-documents/' . $shipment->id, $filename);

        // Create document record
        $document = ShippingDocument::create([
            'shipment_id' => $shipment->id,
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
            'document_type' => $validated['document_type'],
            'filename' => $filename,
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_type' => strtolower($extension),
            'file_size' => $file->getSize(),
            'extraction_status' => ShippingDocument::STATUS_PENDING,
        ]);

        // Update shipment status
        if ($shipment->status === Shipment::STATUS_DRAFT) {
            $shipment->updateStatus(Shipment::STATUS_DOCUMENTS_UPLOADED);
        }

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'document' => [
                    'id' => $document->id,
                    'document_type' => $document->document_type,
                    'document_type_label' => $document->document_type_label,
                    'original_filename' => $document->original_filename,
                    'extraction_status' => $document->extraction_status,
                ],
                'message' => 'Document uploaded. Click "Extract" to process.',
            ]);
        }

        return redirect()->route('shipments.show', $shipment)
            ->with('success', 'Document uploaded successfully. Click "Extract Data" to process.');
    }

    /**
     * Extract data from a shipping document using AI
     */
    public function extract(Request $request, ShippingDocument $shippingDocument)
    {
        if ($shippingDocument->isProcessing()) {
            return response()->json([
                'success' => false,
                'error' => 'Document is already being processed.',
            ], 400);
        }

        $shippingDocument->markAsProcessing();

        try {
            // Get the file from storage
            $fullPath = Storage::path($shippingDocument->file_path);
            
            if (!file_exists($fullPath)) {
                throw new \Exception('Document file not found.');
            }

            // Create an UploadedFile instance for the extractor
            $file = new \Illuminate\Http\UploadedFile(
                $fullPath,
                $shippingDocument->original_filename,
                mime_content_type($fullPath),
                null,
                true // test mode to prevent move
            );

            // Extract data
            $extracted = $this->extractor->extract($file, $shippingDocument->document_type);

            // Check for extraction errors
            if (!empty($extracted['extraction_meta']['error'])) {
                $shippingDocument->markAsFailed($extracted['extraction_meta']['error']);
                
                return response()->json([
                    'success' => false,
                    'error' => $extracted['extraction_meta']['error'],
                ]);
            }

            // Apply extracted data to document
            $this->extractor->applyToDocument($shippingDocument, $extracted);

            // Sync key data to shipment
            $shippingDocument->syncToShipment();

            // Find matching contacts
            $contactMatches = $this->findContactMatches($extracted);

            return response()->json([
                'success' => true,
                'document' => [
                    'id' => $shippingDocument->id,
                    'document_number' => $shippingDocument->document_number,
                    'manifest_number' => $shippingDocument->manifest_number,
                    'carrier_name' => $shippingDocument->carrier_name,
                    'vessel_name' => $shippingDocument->vessel_name,
                    'port_of_loading' => $shippingDocument->port_of_loading,
                    'port_of_discharge' => $shippingDocument->port_of_discharge,
                    'freight_charges' => $shippingDocument->freight_charges,
                    'total_packages' => $shippingDocument->total_packages,
                    'gross_weight_kg' => $shippingDocument->gross_weight_kg,
                    'goods_description' => $shippingDocument->goods_description,
                    'invoice_references' => $shippingDocument->invoice_references,
                ],
                'extracted' => $extracted,
                'contact_matches' => $contactMatches,
                'message' => 'Data extracted successfully.',
            ]);

        } catch (\Exception $e) {
            Log::error('Shipping document extraction failed', [
                'document_id' => $shippingDocument->id,
                'error' => $e->getMessage(),
            ]);

            $shippingDocument->markAsFailed($e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Extraction failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Find matching contacts for extracted party details
     */
    protected function findContactMatches(array $extracted): array
    {
        $matches = [];

        if (!empty($extracted['shipper_details'])) {
            $result = $this->contactMatcher->findMatches(
                $extracted['shipper_details'],
                TradeContact::TYPE_SHIPPER
            );
            $matches['shipper'] = $this->contactMatcher->formatMatchResult($result);
            $matches['shipper']['extracted'] = $extracted['shipper_details'];
        }

        if (!empty($extracted['consignee_details'])) {
            $result = $this->contactMatcher->findMatches(
                $extracted['consignee_details'],
                TradeContact::TYPE_CONSIGNEE
            );
            $matches['consignee'] = $this->contactMatcher->formatMatchResult($result);
            $matches['consignee']['extracted'] = $extracted['consignee_details'];
        }

        if (!empty($extracted['notify_party_details'])) {
            $result = $this->contactMatcher->findMatches(
                $extracted['notify_party_details'],
                TradeContact::TYPE_NOTIFY_PARTY
            );
            $matches['notify_party'] = $this->contactMatcher->formatMatchResult($result);
            $matches['notify_party']['extracted'] = $extracted['notify_party_details'];
        }

        return $matches;
    }

    /**
     * Update shipping document data
     */
    public function update(Request $request, ShippingDocument $shippingDocument)
    {
        $validated = $request->validate([
            'document_number' => 'nullable|string|max:100',
            'manifest_number' => 'nullable|string|max:100',
            'carrier_name' => 'nullable|string|max:255',
            'vessel_name' => 'nullable|string|max:255',
            'voyage_number' => 'nullable|string|max:100',
            'port_of_loading' => 'nullable|string|max:255',
            'port_of_discharge' => 'nullable|string|max:255',
            'final_destination' => 'nullable|string|max:255',
            'shipping_date' => 'nullable|date',
            'estimated_arrival' => 'nullable|date',
            'freight_charges' => 'nullable|numeric|min:0',
            'freight_terms' => 'nullable|string|max:50',
            'insurance_amount' => 'nullable|numeric|min:0',
            'other_charges' => 'nullable|numeric|min:0',
            'total_packages' => 'nullable|integer|min:0',
            'package_type' => 'nullable|string|max:100',
            'goods_description' => 'nullable|string|max:1000',
            'gross_weight_kg' => 'nullable|numeric|min:0',
            'net_weight_kg' => 'nullable|numeric|min:0',
        ]);

        $shippingDocument->update($validated);

        // Sync to shipment
        $shippingDocument->syncToShipment();

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Document updated.',
            ]);
        }

        return redirect()->route('shipments.show', $shippingDocument->shipment)
            ->with('success', 'Document updated successfully.');
    }

    /**
     * Verify extracted data
     */
    public function verify(ShippingDocument $shippingDocument)
    {
        $shippingDocument->verify();

        return response()->json([
            'success' => true,
            'message' => 'Document verified.',
        ]);
    }

    /**
     * Delete a shipping document
     */
    public function destroy(ShippingDocument $shippingDocument)
    {
        $shipment = $shippingDocument->shipment;
        
        // Delete the file
        $shippingDocument->deleteFile();
        
        // Delete the record
        $shippingDocument->delete();

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Document deleted.',
            ]);
        }

        return redirect()->route('shipments.show', $shipment)
            ->with('success', 'Document deleted successfully.');
    }

    /**
     * Download the document file
     */
    public function download(ShippingDocument $shippingDocument)
    {
        if (!Storage::exists($shippingDocument->file_path)) {
            abort(404, 'File not found.');
        }

        return Storage::download(
            $shippingDocument->file_path,
            $shippingDocument->original_filename
        );
    }

    /**
     * Create a new contact from extracted details
     */
    public function createContact(Request $request, ShippingDocument $shippingDocument)
    {
        $validated = $request->validate([
            'contact_type' => 'required|in:shipper,consignee,notify_party',
            'company_name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state_province' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'fax' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
        ]);

        $contact = $this->contactMatcher->createContactFromDetails(
            $validated,
            $validated['contact_type'],
            $shippingDocument->shipment->country_id
        );

        // Update shipment with new contact
        $shipment = $shippingDocument->shipment;
        $contactField = match ($validated['contact_type']) {
            'shipper' => 'shipper_contact_id',
            'consignee' => 'consignee_contact_id',
            'notify_party' => 'notify_party_contact_id',
            default => null,
        };

        if ($contactField) {
            $shipment->update([$contactField => $contact->id]);
        }

        return response()->json([
            'success' => true,
            'contact' => [
                'id' => $contact->id,
                'company_name' => $contact->company_name,
                'full_address' => $contact->full_address,
                'phone' => $contact->phone,
            ],
            'message' => 'Contact created and linked to shipment.',
        ]);
    }

    /**
     * Link an existing contact to shipment
     */
    public function linkContact(Request $request, Shipment $shipment)
    {
        $validated = $request->validate([
            'contact_type' => 'required|in:shipper,consignee,notify_party',
            'contact_id' => 'required|exists:trade_contacts,id',
        ]);

        $contactField = match ($validated['contact_type']) {
            'shipper' => 'shipper_contact_id',
            'consignee' => 'consignee_contact_id',
            'notify_party' => 'notify_party_contact_id',
            default => null,
        };

        if ($contactField) {
            $shipment->update([$contactField => $validated['contact_id']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Contact linked to shipment.',
        ]);
    }
}
