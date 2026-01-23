<?php

namespace App\Services;

use App\Models\ShippingDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class ShippingDocumentExtractor
{
    public function __construct(
        protected DocumentTextExtractor $textExtractor,
        protected ClaudeJsonClient $claude,
        protected ?UnstructuredApiClient $unstructuredApi = null,
    ) {
    }

    /**
     * Extract data from a shipping document file
     *
     * @param UploadedFile $file
     * @param string $documentType
     * @return array
     */
    public function extract(UploadedFile $file, string $documentType = ShippingDocument::TYPE_BILL_OF_LADING): array
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');
        $mime = strtolower((string) $file->getMimeType());

        $isImage = str_starts_with($mime, 'image/') || in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true);
        $isPdf = $ext === 'pdf' || $mime === 'application/pdf';

        $meta = [
            'file_ext' => $ext,
            'mime' => $mime,
            'size_bytes' => $file->getSize(),
            'document_type' => $documentType,
        ];

        // For images, use vision
        if ($isImage) {
            $mediaType = $mime ?: $this->guessMediaTypeFromExtension($ext);
            $prompt = $this->buildVisionPrompt($documentType);
            $json = $this->claude->promptForJsonWithImage($prompt, $file->get(), $mediaType);
            return $this->normalizeResult($json, null, array_merge($meta, ['mode' => 'vision']));
        }

        // For PDFs
        if ($isPdf) {
            $fileSize = $file->getSize() ?? 0;
            
            // Try Unstructured API for large or scanned PDFs
            if ($this->unstructuredApi) {
                try {
                    $text = $this->unstructuredApi->extractText($file, $fileSize > 2_000_000 ? 'hi_res' : 'ocr_only');
                    $text = trim($text);
                    
                    if (mb_strlen($text) >= 50) {
                        Log::info('Shipping document extracted via Unstructured API', [
                            'size' => $fileSize,
                            'text_length' => mb_strlen($text),
                        ]);
                        $prompt = $this->buildTextPrompt($text, $documentType);
                        $json = $this->claude->promptForJson($prompt);
                        return $this->normalizeResult($json, $text, array_merge($meta, ['mode' => 'unstructured_api']));
                    }
                } catch (\Throwable $e) {
                    Log::warning('Unstructured API failed for shipping document', ['error' => $e->getMessage()]);
                }
            }

            // Try standard text extraction
            $text = $this->textExtractor->extractText($file->getPathname(), 'pdf');
            $text = trim($text);
            
            if (mb_strlen($text) >= 50) {
                $prompt = $this->buildTextPrompt($text, $documentType);
                $json = $this->claude->promptForJson($prompt);
                return $this->normalizeResult($json, $text, array_merge($meta, ['mode' => 'text']));
            }

            return $this->normalizeResult([], null, array_merge($meta, [
                'mode' => 'pdf_empty_text',
                'error' => 'PDF appears scanned or has no readable text. Try uploading as an image (JPG/PNG).',
            ]));
        }

        return $this->normalizeResult([], null, array_merge($meta, [
            'mode' => 'unsupported',
            'error' => 'Unsupported file type. Please upload a PDF or image file.',
        ]));
    }

    /**
     * Build AI prompt for vision (image) extraction
     */
    protected function buildVisionPrompt(string $documentType): string
    {
        $typeInstructions = $this->getTypeSpecificInstructions($documentType);

        return <<<PROMPT
You are extracting structured data from a shipping document image ({$this->getDocumentTypeLabel($documentType)}).

{$typeInstructions}

Return ONLY valid JSON in this format:
{
  "document_number": "string or null (B/L number, AWB number, etc.)",
  "manifest_number": "string or null",
  
  "shipper": {
    "company_name": "string or null",
    "address": "string or null",
    "city": "string or null",
    "state_province": "string or null",
    "postal_code": "string or null",
    "country": "string or null",
    "phone": "string or null",
    "fax": "string or null"
  },
  
  "consignee": {
    "company_name": "string or null",
    "address": "string or null",
    "city": "string or null",
    "state_province": "string or null",
    "postal_code": "string or null",
    "country": "string or null",
    "phone": "string or null"
  },
  
  "notify_party": {
    "company_name": "string or null",
    "address": "string or null",
    "phone": "string or null"
  },
  
  "forwarding_agent": {
    "company_name": "string or null",
    "address": "string or null",
    "phone": "string or null"
  },
  
  "carrier_name": "string or null",
  "vessel_name": "string or null",
  "voyage_number": "string or null",
  "port_of_loading": "string or null",
  "port_of_discharge": "string or null",
  "final_destination": "string or null",
  "shipping_date": "YYYY-MM-DD or null",
  "estimated_arrival": "YYYY-MM-DD or null",
  
  "freight_charges": number or null,
  "freight_terms": "prepaid or collect or null",
  "insurance_amount": number or null,
  "other_charges": number or null,
  "currency": "USD or other currency code",
  
  "total_packages": number or null,
  "package_type": "string (boxes, cartons, pallets, etc.) or null",
  "goods_description": "string or null",
  "gross_weight_kg": number or null,
  "net_weight_kg": number or null,
  "volume_cbm": number or null,
  
  "invoice_references": ["string array of invoice numbers referenced"]
}

Rules:
- Extract ALL visible information from the document.
- For weights, convert to kilograms if shown in pounds (1 lb = 0.453592 kg).
- For addresses, try to parse into components but also preserve the full address.
- If freight shows "COLLECT", set freight_terms to "collect"; if "PREPAID", set to "prepaid".
- Look for invoice numbers mentioned in remarks or description sections.
- Do not include explanations, only JSON.
PROMPT;
    }

    /**
     * Build AI prompt for text extraction
     */
    protected function buildTextPrompt(string $text, string $documentType): string
    {
        $snippet = mb_substr($text, 0, 60_000);
        $typeInstructions = $this->getTypeSpecificInstructions($documentType);

        return <<<PROMPT
You are extracting structured data from a shipping document ({$this->getDocumentTypeLabel($documentType)}).

{$typeInstructions}

Return ONLY valid JSON in this format:
{
  "document_number": "string or null (B/L number, AWB number, etc.)",
  "manifest_number": "string or null",
  
  "shipper": {
    "company_name": "string or null",
    "address": "string or null",
    "city": "string or null",
    "state_province": "string or null",
    "postal_code": "string or null",
    "country": "string or null",
    "phone": "string or null",
    "fax": "string or null"
  },
  
  "consignee": {
    "company_name": "string or null",
    "address": "string or null",
    "city": "string or null",
    "state_province": "string or null",
    "postal_code": "string or null",
    "country": "string or null",
    "phone": "string or null"
  },
  
  "notify_party": {
    "company_name": "string or null",
    "address": "string or null",
    "phone": "string or null"
  },
  
  "forwarding_agent": {
    "company_name": "string or null",
    "address": "string or null",
    "phone": "string or null"
  },
  
  "carrier_name": "string or null",
  "vessel_name": "string or null",
  "voyage_number": "string or null",
  "port_of_loading": "string or null",
  "port_of_discharge": "string or null",
  "final_destination": "string or null",
  "shipping_date": "YYYY-MM-DD or null",
  "estimated_arrival": "YYYY-MM-DD or null",
  
  "freight_charges": number or null,
  "freight_terms": "prepaid or collect or null",
  "insurance_amount": number or null,
  "other_charges": number or null,
  "currency": "USD or other currency code",
  
  "total_packages": number or null,
  "package_type": "string (boxes, cartons, pallets, etc.) or null",
  "goods_description": "string or null",
  "gross_weight_kg": number or null,
  "net_weight_kg": number or null,
  "volume_cbm": number or null,
  
  "invoice_references": ["string array of invoice numbers referenced"]
}

Rules:
- Extract ALL information you can find.
- For weights, convert to kilograms if shown in pounds.
- Look for invoice numbers in remarks, references, or description sections.
- Do not include explanations, only JSON.

DOCUMENT TEXT:
{$snippet}
PROMPT;
    }

    /**
     * Get type-specific extraction instructions
     */
    protected function getTypeSpecificInstructions(string $documentType): string
    {
        return match ($documentType) {
            ShippingDocument::TYPE_BILL_OF_LADING => 
                'This is a Bill of Lading (B/L). Focus on extracting the B/L number, shipper (exporter), consignee (importer), notify party, vessel/carrier details, ports, and cargo information.',
            
            ShippingDocument::TYPE_AIR_WAYBILL => 
                'This is an Air Waybill (AWB). Focus on extracting the AWB number, shipper, consignee, airline/carrier, airports, and cargo details.',
            
            ShippingDocument::TYPE_PACKING_LIST => 
                'This is a Packing List. Focus on extracting package counts, weights, dimensions, and goods descriptions. Link to invoice numbers if mentioned.',
            
            ShippingDocument::TYPE_CERTIFICATE_OF_ORIGIN => 
                'This is a Certificate of Origin. Focus on extracting the country of origin, exporter details, and goods descriptions.',
            
            ShippingDocument::TYPE_INSURANCE_CERTIFICATE => 
                'This is an Insurance Certificate. Focus on extracting the insured value, insurance amount/premium, and coverage details.',
            
            default => 
                'Extract all shipping and cargo-related information from this document.',
        };
    }

    /**
     * Get human-readable document type label
     */
    protected function getDocumentTypeLabel(string $documentType): string
    {
        return ShippingDocument::getDocumentTypes()[$documentType] ?? 'Shipping Document';
    }

    /**
     * Normalize the extracted result
     */
    protected function normalizeResult(array $json, ?string $extractedText, array $meta): array
    {
        // Normalize shipper details
        $shipper = $this->normalizePartyDetails($json['shipper'] ?? null);
        $consignee = $this->normalizePartyDetails($json['consignee'] ?? null);
        $notifyParty = $this->normalizePartyDetails($json['notify_party'] ?? null);
        $forwardingAgent = $this->normalizePartyDetails($json['forwarding_agent'] ?? null);

        // Normalize invoice references
        $invoiceRefs = $json['invoice_references'] ?? [];
        if (!is_array($invoiceRefs)) {
            $invoiceRefs = [];
        }

        return [
            'document_number' => $this->normalizeString($json['document_number'] ?? null),
            'manifest_number' => $this->normalizeString($json['manifest_number'] ?? null),
            
            'shipper_details' => $shipper,
            'consignee_details' => $consignee,
            'notify_party_details' => $notifyParty,
            'forwarding_agent_details' => $forwardingAgent,
            
            'carrier_name' => $this->normalizeString($json['carrier_name'] ?? null),
            'vessel_name' => $this->normalizeString($json['vessel_name'] ?? null),
            'voyage_number' => $this->normalizeString($json['voyage_number'] ?? null),
            'port_of_loading' => $this->normalizeString($json['port_of_loading'] ?? null),
            'port_of_discharge' => $this->normalizeString($json['port_of_discharge'] ?? null),
            'final_destination' => $this->normalizeString($json['final_destination'] ?? null),
            'shipping_date' => $this->normalizeDate($json['shipping_date'] ?? null),
            'estimated_arrival' => $this->normalizeDate($json['estimated_arrival'] ?? null),
            
            'freight_charges' => $this->normalizeNumber($json['freight_charges'] ?? null),
            'freight_terms' => $this->normalizeString($json['freight_terms'] ?? null),
            'insurance_amount' => $this->normalizeNumber($json['insurance_amount'] ?? null),
            'other_charges' => $this->normalizeNumber($json['other_charges'] ?? null),
            'currency' => strtoupper($this->normalizeString($json['currency'] ?? null) ?: 'USD'),
            
            'total_packages' => $this->normalizeInteger($json['total_packages'] ?? null),
            'package_type' => $this->normalizeString($json['package_type'] ?? null),
            'goods_description' => $this->normalizeString($json['goods_description'] ?? null),
            'gross_weight_kg' => $this->normalizeNumber($json['gross_weight_kg'] ?? null),
            'net_weight_kg' => $this->normalizeNumber($json['net_weight_kg'] ?? null),
            'volume_cbm' => $this->normalizeNumber($json['volume_cbm'] ?? null),
            
            'invoice_references' => array_values(array_filter(array_map('trim', $invoiceRefs))),
            
            'extracted_text' => $extractedText,
            'extraction_meta' => $meta,
        ];
    }

    /**
     * Normalize party (shipper/consignee) details
     */
    protected function normalizePartyDetails(?array $party): ?array
    {
        if (!$party || !is_array($party)) {
            return null;
        }

        $normalized = [
            'company_name' => $this->normalizeString($party['company_name'] ?? $party['name'] ?? null),
            'address' => $this->normalizeString($party['address'] ?? $party['address_line_1'] ?? null),
            'city' => $this->normalizeString($party['city'] ?? null),
            'state_province' => $this->normalizeString($party['state_province'] ?? $party['state'] ?? null),
            'postal_code' => $this->normalizeString($party['postal_code'] ?? $party['zip'] ?? null),
            'country' => $this->normalizeString($party['country'] ?? null),
            'phone' => $this->normalizeString($party['phone'] ?? $party['telephone'] ?? null),
            'fax' => $this->normalizeString($party['fax'] ?? null),
            'email' => $this->normalizeString($party['email'] ?? null),
        ];

        // Return null if no meaningful data
        if (empty(array_filter($normalized))) {
            return null;
        }

        return $normalized;
    }

    /**
     * Normalize string value
     */
    protected function normalizeString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        
        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Normalize number value
     */
    protected function normalizeNumber($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        
        return (float) $value;
    }

    /**
     * Normalize integer value
     */
    protected function normalizeInteger($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        
        return (int) $value;
    }

    /**
     * Normalize date value
     */
    protected function normalizeDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            $date = new \DateTime($value);
            return $date->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Guess media type from extension
     */
    protected function guessMediaTypeFromExtension(string $ext): string
    {
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/png',
        };
    }

    /**
     * Apply extracted data to a ShippingDocument model
     */
    public function applyToDocument(ShippingDocument $document, array $extracted): void
    {
        $document->update([
            'document_number' => $extracted['document_number'],
            'manifest_number' => $extracted['manifest_number'],
            'shipper_details' => $extracted['shipper_details'],
            'consignee_details' => $extracted['consignee_details'],
            'notify_party_details' => $extracted['notify_party_details'],
            'forwarding_agent_details' => $extracted['forwarding_agent_details'],
            'carrier_name' => $extracted['carrier_name'],
            'vessel_name' => $extracted['vessel_name'],
            'voyage_number' => $extracted['voyage_number'],
            'port_of_loading' => $extracted['port_of_loading'],
            'port_of_discharge' => $extracted['port_of_discharge'],
            'final_destination' => $extracted['final_destination'],
            'shipping_date' => $extracted['shipping_date'],
            'estimated_arrival' => $extracted['estimated_arrival'],
            'freight_charges' => $extracted['freight_charges'],
            'freight_terms' => $extracted['freight_terms'],
            'insurance_amount' => $extracted['insurance_amount'],
            'other_charges' => $extracted['other_charges'],
            'currency' => $extracted['currency'],
            'total_packages' => $extracted['total_packages'],
            'package_type' => $extracted['package_type'],
            'goods_description' => $extracted['goods_description'],
            'gross_weight_kg' => $extracted['gross_weight_kg'],
            'net_weight_kg' => $extracted['net_weight_kg'],
            'volume_cbm' => $extracted['volume_cbm'],
            'invoice_references' => $extracted['invoice_references'],
            'extracted_text' => $extracted['extracted_text'],
            'extraction_meta' => $extracted['extraction_meta'],
            'extracted_data' => $extracted,
            'extraction_status' => ShippingDocument::STATUS_COMPLETED,
            'extracted_at' => now(),
        ]);
    }
}
