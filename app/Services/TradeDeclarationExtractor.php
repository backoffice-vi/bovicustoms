<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class TradeDeclarationExtractor
{
    public function __construct(
        protected DocumentTextExtractor $textExtractor,
        protected ClaudeJsonClient $claude,
    ) {
    }

    /**
     * Extracts trade declaration header + items (including HS codes).
     *
     * Returns:
     * [
     *   'form_number' => ?string,
     *   'declaration_date' => ?string (YYYY-MM-DD),
     *   'total_duty' => ?number,
     *   'currency' => ?string,
     *   'invoice_number' => ?string (if present in declaration),
     *   'invoice_date' => ?string (YYYY-MM-DD),
     *   'total_amount' => ?number,
     *   'items' => [
     *     { description, sku/item_number, qty, unit_price, line_total, hs_code, hs_description }
     *   ],
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
            $prompt = $this->buildDeclarationVisionPrompt();
            $json = $this->claude->promptForJsonWithImage($prompt, $file->get(), $mediaType);
            return $this->normalizeDeclarationResult($json, null, array_merge($meta, ['mode' => 'vision']));
        }

        if ($isExcel) {
            $text = $this->textExtractor->extractText($file->getPathname(), $ext);
            $prompt = $this->buildDeclarationTextPrompt($text);
            $json = $this->claude->promptForJson($prompt);
            return $this->normalizeDeclarationResult($json, $text, array_merge($meta, ['mode' => 'text']));
        }

        if ($isPdf) {
            if (($file->getSize() ?? 0) > 2_000_000) {
                return $this->normalizeDeclarationResult([], null, array_merge($meta, [
                    'mode' => 'pdf_rejected_large',
                    'error' => 'PDF too large for text extraction; upload as image (JPG/PNG) or Excel.',
                ]));
            }

            $text = $this->textExtractor->extractText($file->getPathname(), 'pdf');
            $text = trim($text);
            if (mb_strlen($text) < 50) {
                return $this->normalizeDeclarationResult([], $text, array_merge($meta, [
                    'mode' => 'pdf_empty_text',
                    'error' => 'PDF appears scanned or has no readable text; upload as image (JPG/PNG) or Excel.',
                ]));
            }

            $prompt = $this->buildDeclarationTextPrompt($text);
            $json = $this->claude->promptForJson($prompt);
            return $this->normalizeDeclarationResult($json, $text, array_merge($meta, ['mode' => 'text']));
        }

        return $this->normalizeDeclarationResult([], null, array_merge($meta, [
            'mode' => 'unsupported',
            'error' => 'Unsupported declaration file type. Upload PDF, Excel, or image.',
        ]));
    }

    protected function normalizeDeclarationResult(array $json, ?string $extractedText, array $meta): array
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
                'hs_code' => isset($item['hs_code']) ? trim((string) $item['hs_code']) : null,
                'hs_description' => isset($item['hs_description']) ? trim((string) $item['hs_description']) : null,
            ];
        }, $items)));

        return [
            'form_number' => isset($json['form_number']) ? trim((string) $json['form_number']) : null,
            'declaration_date' => isset($json['declaration_date']) ? trim((string) $json['declaration_date']) : null,
            'total_duty' => isset($json['total_duty']) ? (float) $json['total_duty'] : null,
            'currency' => isset($json['currency']) ? strtoupper(trim((string) $json['currency'])) : null,

            // Helpful fields for auto-linking to invoice
            'invoice_number' => isset($json['invoice_number']) ? trim((string) $json['invoice_number']) : null,
            'invoice_date' => isset($json['invoice_date']) ? trim((string) $json['invoice_date']) : null,
            'total_amount' => isset($json['total_amount']) ? (float) $json['total_amount'] : null,

            'items' => $items,
            'extracted_text' => $extractedText,
            'extraction_meta' => $meta,
        ];
    }

    protected function buildDeclarationVisionPrompt(): string
    {
        return <<<PROMPT
You are extracting structured data from a Trade Declaration / Customs Declaration form image.

Return ONLY valid JSON in this format:
{
  "form_number": "string or null",
  "declaration_date": "YYYY-MM-DD or null",
  "total_duty": number or null,
  "currency": "USD or null",

  "invoice_number": "string or null",
  "invoice_date": "YYYY-MM-DD or null",
  "total_amount": number or null,

  "items": [
    {
      "line_number": 1,
      "sku": "string or null",
      "item_number": "string or null",
      "description": "string (required)",
      "quantity": number or null,
      "unit_price": number or null,
      "line_total": number or null,
      "currency": "USD or null",
      "hs_code": "string or null",
      "hs_description": "string or null"
    }
  ]
}

Rules:
- Extract ALL line items.
- HS code is required when present in the document (it is the approved categorization).
- If fields are missing, use null.
- Do not include explanations, only JSON.
PROMPT;
    }

    protected function buildDeclarationTextPrompt(string $text): string
    {
        $snippet = mb_substr($text, 0, 70_000);

        return <<<PROMPT
You are extracting structured data from a Trade Declaration / Customs Declaration form text.

Return ONLY valid JSON in this format:
{
  "form_number": "string or null",
  "declaration_date": "YYYY-MM-DD or null",
  "total_duty": number or null,
  "currency": "USD or null",

  "invoice_number": "string or null",
  "invoice_date": "YYYY-MM-DD or null",
  "total_amount": number or null,

  "items": [
    {
      "line_number": 1,
      "sku": "string or null",
      "item_number": "string or null",
      "description": "string (required)",
      "quantity": number or null,
      "unit_price": number or null,
      "line_total": number or null,
      "currency": "USD or null",
      "hs_code": "string or null",
      "hs_description": "string or null"
    }
  ]
}

Rules:
- Extract ALL line items and their HS codes.
- If you can't find a value, set it to null.
- Do not include explanations, only JSON.

DECLARATION TEXT:
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

