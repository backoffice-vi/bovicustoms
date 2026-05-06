<?php

namespace App\Services\Documents;

use App\Models\DeclarationForm;
use Illuminate\Support\Facades\Log;

/**
 * Collects attachments (B/L, AWB, invoices) for a declaration so they can be
 * sent to CAPS via either the web form (Playwright) or the FTP attachment flow.
 *
 * Each entry contains:
 *   label              human-readable label (e.g. "Bill of Lading - 2613SJU1046.pdf")
 *   filePath           absolute filesystem path (null if file is missing on disk)
 *   relativePath       path stored in DB (e.g. "shipping-documents/56/abc.pdf")
 *   type               document type (bill_of_lading, awb, invoice, etc.)
 *   sourceReference    e.g. invoice number or B/L number
 *   originalFilename   user-uploaded filename
 *   exists             true if filePath was found on disk
 *   missingReason      null when exists=true; otherwise a short explanation
 */
class DeclarationAttachmentGatherer
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function gather(DeclarationForm $declaration, bool $includeMissing = true): array
    {
        $declaration->loadMissing(['shipment.shippingDocuments']);

        $attachments = [];

        if ($declaration->shipment) {
            $transportDoc = $declaration->shipment->shippingDocuments
                ->filter(fn ($doc) => $doc->isPrimaryTransportDocument() && !empty($doc->file_path))
                ->first();

            if ($transportDoc) {
                $entry = $this->buildEntry(
                    relativePath: $transportDoc->file_path,
                    label: ($transportDoc->document_type_label ?? 'Bill of Lading')
                        . ' - ' . ($transportDoc->original_filename ?? $transportDoc->document_number ?? 'B/L'),
                    type: $transportDoc->document_type ?: 'bill_of_lading',
                    sourceReference: $transportDoc->document_number,
                    originalFilename: $transportDoc->original_filename,
                );

                if ($entry['exists'] || $includeMissing) {
                    $attachments[] = $entry;
                }

                if (!$entry['exists']) {
                    Log::warning('Declaration attachment missing on disk', [
                        'declaration_id' => $declaration->id,
                        'type' => 'bill_of_lading',
                        'relative_path' => $transportDoc->file_path,
                    ]);
                }
            }
        }

        $invoices = $declaration->getAllInvoices();
        foreach ($invoices as $invoice) {
            if (empty($invoice->source_file_path)) {
                continue;
            }

            $invoiceFallback = $invoice->original_filename;
            if (empty($invoiceFallback) || $this->looksLikeStorageHash($invoiceFallback)) {
                $ext = pathinfo($invoice->source_file_path, PATHINFO_EXTENSION) ?: 'pdf';
                $invoiceFallback = 'Invoice-' . ($invoice->invoice_number ?? $invoice->id) . '.' . $ext;
            }

            $entry = $this->buildEntry(
                relativePath: $invoice->source_file_path,
                label: 'Invoice #' . ($invoice->invoice_number ?? $invoice->id),
                type: 'invoice',
                sourceReference: $invoice->invoice_number,
                originalFilename: $invoiceFallback,
            );

            if ($entry['exists'] || $includeMissing) {
                $attachments[] = $entry;
            }

            if (!$entry['exists']) {
                Log::warning('Declaration attachment missing on disk', [
                    'declaration_id' => $declaration->id,
                    'type' => 'invoice',
                    'relative_path' => $invoice->source_file_path,
                ]);
            }
        }

        return $attachments;
    }

    /**
     * Convenience wrapper for the existing Playwright submitter shape.
     * Filters out entries that are missing on disk (the web flow cannot use them).
     *
     * @return array<int, array{label: string, filePath: string, type: string}>
     */
    public function gatherForWebSubmission(DeclarationForm $declaration): array
    {
        $entries = $this->gather($declaration, includeMissing: false);

        return array_map(fn ($e) => [
            'label' => $e['label'],
            'filePath' => $e['filePath'],
            'type' => $e['type'],
        ], $entries);
    }

    private function buildEntry(
        string $relativePath,
        string $label,
        string $type,
        ?string $sourceReference,
        ?string $originalFilename,
    ): array {
        $absolute = storage_path('app/' . ltrim($relativePath, '/'));
        $exists = is_file($absolute);

        $resolvedOriginal = $originalFilename;
        if ($resolvedOriginal === null || $resolvedOriginal === '' || $this->looksLikeStorageHash($resolvedOriginal)) {
            $base = basename($relativePath);
            $resolvedOriginal = $this->looksLikeStorageHash($base)
                ? ($label . '.' . (pathinfo($relativePath, PATHINFO_EXTENSION) ?: 'bin'))
                : $base;
        }

        return [
            'label' => $label,
            'filePath' => $exists ? $absolute : null,
            'relativePath' => $relativePath,
            'type' => $type,
            'sourceReference' => $sourceReference,
            'originalFilename' => $resolvedOriginal,
            'exists' => $exists,
            'missingReason' => $exists ? null : 'File not found on disk at ' . $absolute,
        ];
    }

    /**
     * Detect Laravel-style storage hashes (e.g. "6wibawJ96fpLBzkHJthehojb6Plj5IYZnFzc12UH.pdf")
     * that should not surface to brokers as a "filename".
     */
    private function looksLikeStorageHash(string $name): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9]{20,}\.[A-Za-z0-9]+$/', $name);
    }
}
