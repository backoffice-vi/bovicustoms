<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class InvoiceDocumentExtractor
{
    public function __construct(
        protected DocumentTextExtractor $textExtractor,
        protected ClaudeJsonClient $claude,
        protected ?UnstructuredApiClient $unstructuredApi = null,
    ) {
    }

    /**
     * Extracts invoice header + items.
     *
     * Returns:
     * [
     *   'invoice_number' => ?string,
     *   'invoice_date' => ?string (YYYY-MM-DD),
     *   'total_amount' => ?number,
     *   'currency' => ?string,
     *   'items' => [ ... ],
     *   'extracted_text' => ?string,
     *   'extraction_meta' => array,
     * ]
     */
    public function extract(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');
        $mime = strtolower((string) $file->getMimeType());

        $isImage = str_starts_with($mime, 'image/') || in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true);
        $isPdf = $ext === 'pdf' || $mime === 'application/pdf';
        $isExcel = in_array($ext, ['xls', 'xlsx'], true);

        $meta = [
            'file_ext' => $ext,
            'mime' => $mime,
            'size_bytes' => $file->getSize(),
        ];

        if ($isImage) {
            $mediaType = $mime ?: $this->guessMediaTypeFromExtension($ext);
            $prompt = $this->buildInvoiceVisionPrompt();
            $json = $this->claude->promptForJsonWithImage($prompt, $file->get(), $mediaType);
            return $this->normalizeInvoiceResult($json, null, array_merge($meta, ['mode' => 'vision']));
        }

        if ($isExcel) {
            $text = $this->textExtractor->extractText($file->getPathname(), $ext);
            $prompt = $this->buildInvoiceTextPrompt($text);
            $json = $this->claude->promptForJson($prompt);
            return $this->normalizeInvoiceResult($json, $text, array_merge($meta, ['mode' => 'text']));
        }

        if ($isPdf) {
            $fileSize = $file->getSize() ?? 0;
            
            // For PDFs under 30MB, use Claude's PDF vision mode (best for invoices)
            // Claude supports up to 100 pages per PDF for vision
            if ($fileSize <= 30_000_000) {
                try {
                    Log::info('Attempting PDF vision extraction', ['size' => $fileSize]);
                    $prompt = $this->buildInvoiceVisionPrompt();
                    $json = $this->claude->promptForJsonWithPdf($prompt, $file->get());
                    
                    // Check if we got meaningful results
                    if (!empty($json['items']) && count($json['items']) > 0) {
                        Log::info('PDF extracted via Claude Vision', [
                            'size' => $fileSize,
                            'items_count' => count($json['items']),
                        ]);
                        return $this->normalizeInvoiceResult($json, null, array_merge($meta, ['mode' => 'pdf_vision']));
                    }
                    
                    Log::warning('PDF vision returned no items, trying text extraction', ['json' => $json]);
                } catch (\Throwable $e) {
                    Log::warning('PDF vision extraction failed, falling back to text extraction', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            // For very large PDFs or if vision failed, use text extraction
            if ($fileSize > 30_000_000 && $this->unstructuredApi) {
                try {
                    $text = $this->unstructuredApi->extractText($file, 'hi_res');
                    $text = trim($text);
                    
                    if (mb_strlen($text) >= 50) {
                        Log::info('Large PDF extracted via Unstructured API', [
                            'size' => $fileSize,
                            'text_length' => mb_strlen($text),
                        ]);
                        $prompt = $this->buildInvoiceTextPrompt($text);
                        $json = $this->claude->promptForJson($prompt);
                        return $this->normalizeInvoiceResult($json, $text, array_merge($meta, ['mode' => 'unstructured_api']));
                    }
                } catch (\Throwable $e) {
                    Log::warning('Unstructured API failed for large PDF', ['error' => $e->getMessage()]);
                }
                
                // If Unstructured API failed for large file
                return $this->normalizeInvoiceResult([], null, array_merge($meta, [
                    'mode' => 'pdf_rejected_large',
                    'error' => 'PDF too large for processing. Please split into smaller files or upload as images.',
                ]));
            }

            // Fallback: try text extraction
            $text = $this->textExtractor->extractText($file->getPathname(), 'pdf');
            $text = trim($text);
            
            // If standard extraction fails, try Unstructured API (handles scanned PDFs with OCR)
            if (mb_strlen($text) < 50 && $this->unstructuredApi) {
                try {
                    $text = $this->unstructuredApi->extractText($file, 'ocr_only');
                    $text = trim($text);
                    
                    if (mb_strlen($text) >= 50) {
                        Log::info('Scanned PDF extracted via Unstructured API OCR', [
                            'text_length' => mb_strlen($text),
                        ]);
                        $prompt = $this->buildInvoiceTextPrompt($text);
                        $json = $this->claude->promptForJson($prompt);
                        return $this->normalizeInvoiceResult($json, $text, array_merge($meta, ['mode' => 'unstructured_ocr']));
                    }
                } catch (\Throwable $e) {
                    Log::warning('Unstructured API OCR failed', ['error' => $e->getMessage()]);
                }
            }
            
            if (mb_strlen($text) < 50) {
                return $this->normalizeInvoiceResult([], $text, array_merge($meta, [
                    'mode' => 'pdf_empty_text',
                    'error' => 'PDF appears scanned or has no readable text. Please try uploading again.',
                ]));
            }

            $prompt = $this->buildInvoiceTextPrompt($text);
            $json = $this->claude->promptForJson($prompt);
            return $this->normalizeInvoiceResult($json, $text, array_merge($meta, ['mode' => 'text']));
        }

        return $this->normalizeInvoiceResult([], null, array_merge($meta, [
            'mode' => 'unsupported',
            'error' => 'Unsupported invoice file type. Upload PDF, Excel, or image.',
        ]));
    }

    protected function normalizeInvoiceResult(array $json, ?string $extractedText, array $meta): array
    {
        $items = $json['items'] ?? [];
        if (!is_array($items)) {
            $items = [];
        }

        $items = array_values(array_filter(array_map(function ($item) {
            if (!is_array($item)) {
                return null;
            }

            $desc = trim((string) ($item['description'] ?? ''));
            if ($desc === '') {
                return null;
            }

            return [
                'line_number' => isset($item['line_number']) ? (int) $item['line_number'] : null,
                'sku' => isset($item['sku']) ? trim((string) $item['sku']) : null,
                'item_number' => isset($item['item_number']) ? trim((string) $item['item_number']) : null,
                'description' => $desc,
                'quantity' => isset($item['quantity']) ? (float) $item['quantity'] : null,
                'unit_price' => isset($item['unit_price']) ? (float) $item['unit_price'] : null,
                'line_total' => isset($item['line_total']) ? (float) $item['line_total'] : null,
                'currency' => isset($item['currency']) ? strtoupper(trim((string) $item['currency'])) : null,
            ];
        }, $items)));

        return [
            'invoice_number' => isset($json['invoice_number']) ? trim((string) $json['invoice_number']) : null,
            'invoice_date' => isset($json['invoice_date']) ? trim((string) $json['invoice_date']) : null,
            'total_amount' => isset($json['total_amount']) ? (float) $json['total_amount'] : null,
            'currency' => isset($json['currency']) ? strtoupper(trim((string) $json['currency'])) : null,
            'items' => $items,
            'extracted_text' => $extractedText,
            'extraction_meta' => $meta,
        ];
    }

    protected function buildInvoiceVisionPrompt(): string
    {
        return <<<PROMPT
You are extracting structured data from a commercial invoice image.

Return ONLY valid JSON in this format:
{
  "invoice_number": "string or null",
  "invoice_date": "YYYY-MM-DD or null",
  "total_amount": number or null,
  "currency": "USD or null",
  "items": [
    {
      "line_number": 1,
      "sku": "string or null",
      "item_number": "string or null",
      "description": "string (required)",
      "quantity": number or null,
      "unit_price": number or null,
      "line_total": number or null,
      "currency": "USD or null"
    }
  ]
}

Rules:
- Extract ALL line items.
- If fields are missing, use null.
- Do not include explanations, only JSON.
PROMPT;
    }

    protected function buildInvoiceTextPrompt(string $text): string
    {
        $snippet = mb_substr($text, 0, 60_000);

        return <<<PROMPT
You are extracting structured data from invoice text.

Return ONLY valid JSON in this format:
{
  "invoice_number": "string or null",
  "invoice_date": "YYYY-MM-DD or null",
  "total_amount": number or null,
  "currency": "USD or null",
  "items": [
    {
      "line_number": 1,
      "sku": "string or null",
      "item_number": "string or null",
      "description": "string (required)",
      "quantity": number or null,
      "unit_price": number or null,
      "line_total": number or null,
      "currency": "USD or null"
    }
  ]
}

Rules:
- Extract ALL line items.
- If the document repeats headers/footers, ignore noise.
- If you can't find a value, set it to null.
- Do not include explanations, only JSON.

INVOICE TEXT:
{$snippet}
PROMPT;
    }

    protected function guessMediaTypeFromExtension(string $ext): string
    {
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/png',
        };
    }
}

