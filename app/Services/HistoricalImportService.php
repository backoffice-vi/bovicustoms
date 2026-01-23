<?php

namespace App\Services;

use App\Models\DeclarationForm;
use App\Models\DeclarationFormItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Shipment;
use App\Models\ShippingDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HistoricalImportService
{
    public function __construct(
        protected InvoiceDocumentExtractor $invoiceExtractor,
        protected TradeDeclarationExtractor $declarationExtractor,
        protected ShippingDocumentExtractor $shippingDocExtractor,
        protected InvoiceDeclarationMatcher $matcher,
    ) {
    }

    public function importHistoricalInvoice(User $user, int $countryId, UploadedFile $file): array
    {
        $extracted = $this->invoiceExtractor->extract($file);

        if (!empty($extracted['extraction_meta']['error'])) {
            return [
                'success' => false,
                'error' => $extracted['extraction_meta']['error'],
                'extracted' => $extracted,
            ];
        }

        $storedPath = $file->store('imports/invoices');

        return DB::transaction(function () use ($user, $countryId, $storedPath, $extracted) {
            $invoiceNumber = $this->makeUniqueInvoiceNumber(
                $extracted['invoice_number'] ?: ('IMP-INV-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6)))
            );

            $invoiceDate = $this->coerceDate($extracted['invoice_date']) ?? now()->toDateString();

            $invoice = Invoice::create([
                'organization_id' => $user->organization_id,
                'country_id' => $countryId,
                'user_id' => $user->id,
                'invoice_number' => $invoiceNumber,
                'invoice_date' => $invoiceDate,
                'total_amount' => $extracted['total_amount'] ?? null,
                'status' => 'completed',
                'processed' => true,
                'items' => $extracted['items'] ?? [],
                'source_type' => 'imported',
                'source_file_path' => $storedPath,
                'extracted_text' => $extracted['extracted_text'] ?? null,
                'extraction_meta' => $extracted['extraction_meta'] ?? null,
            ]);

            $line = 1;
            foreach (($extracted['items'] ?? []) as $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'organization_id' => $user->organization_id,
                    'user_id' => $user->id,
                    'country_id' => $countryId,
                    'line_number' => $item['line_number'] ?? $line++,
                    'sku' => $this->nullIfEmpty($item['sku'] ?? null),
                    'item_number' => $this->nullIfEmpty($item['item_number'] ?? null),
                    'description' => $item['description'],
                    'quantity' => $item['quantity'] ?? null,
                    'unit_price' => $item['unit_price'] ?? null,
                    'line_total' => $item['line_total'] ?? null,
                    'currency' => $this->nullIfEmpty($item['currency'] ?? $extracted['currency'] ?? null),
                    'meta' => null,
                ]);
            }

            return [
                'success' => true,
                'invoice' => $invoice,
            ];
        });
    }

    public function importHistoricalTradeDeclaration(User $user, int $countryId, UploadedFile $file): array
    {
        $extracted = $this->declarationExtractor->extract($file);

        if (!empty($extracted['extraction_meta']['error'])) {
            return [
                'success' => false,
                'error' => $extracted['extraction_meta']['error'],
                'extracted' => $extracted,
            ];
        }

        $storedPath = $file->store('imports/declarations');

        return DB::transaction(function () use ($user, $countryId, $storedPath, $extracted) {
            $invoice = $this->findOrCreateInvoiceForDeclaration($user, $countryId, $extracted);

            $formNumber = $extracted['form_number'] ?: ('IMP-DEC-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6)));
            $declarationDate = $this->coerceDate($extracted['declaration_date']) ?? now()->toDateString();

            $form = DeclarationForm::create([
                'organization_id' => $user->organization_id,
                'country_id' => $countryId,
                'invoice_id' => $invoice->id,
                'form_number' => $formNumber,
                'declaration_date' => $declarationDate,
                'total_duty' => $extracted['total_duty'] ?? 0,
                'items' => $extracted['items'] ?? [],
                'source_type' => 'imported',
                'source_file_path' => $storedPath,
                'extracted_text' => $extracted['extracted_text'] ?? null,
                'extraction_meta' => $extracted['extraction_meta'] ?? null,
            ]);

            $line = 1;
            foreach (($extracted['items'] ?? []) as $item) {
                DeclarationFormItem::create([
                    'declaration_form_id' => $form->id,
                    'invoice_id' => $invoice->id,
                    'organization_id' => $user->organization_id,
                    'user_id' => $user->id,
                    'country_id' => $countryId,
                    'line_number' => $item['line_number'] ?? $line++,
                    'sku' => $this->nullIfEmpty($item['sku'] ?? null),
                    'item_number' => $this->nullIfEmpty($item['item_number'] ?? null),
                    'description' => $item['description'],
                    'quantity' => $item['quantity'] ?? null,
                    'unit_price' => $item['unit_price'] ?? null,
                    'line_total' => $item['line_total'] ?? null,
                    'currency' => $this->nullIfEmpty($item['currency'] ?? $extracted['currency'] ?? null),
                    'hs_code' => $this->nullIfEmpty($item['hs_code'] ?? null),
                    'hs_description' => $this->nullIfEmpty($item['hs_description'] ?? null),
                    'meta' => null,
                ]);
            }

            // Ensure invoice items exist for matching; if missing, seed from declaration
            if ($invoice->invoiceItems()->count() === 0) {
                $this->seedInvoiceItemsFromDeclaration($invoice, $user, $countryId, $extracted);
            }

            $matchResult = $this->matcher->matchInvoiceToDeclaration($invoice, $form, $user);

            return [
                'success' => true,
                'declaration_form' => $form,
                'invoice' => $invoice,
                'match_result' => $matchResult,
            ];
        });
    }

    /**
     * Import a complete legacy clearance: invoices + shipping doc + declaration
     * Creates a Shipment with all linked records and matches items.
     */
    public function importLegacyClearance(
        User $user,
        int $countryId,
        array $invoiceFiles,
        ?UploadedFile $shippingDocFile,
        UploadedFile $declarationFile
    ): array {
        // Extract declaration first (required, has HS codes)
        $declarationData = $this->declarationExtractor->extract($declarationFile);
        if (!empty($declarationData['extraction_meta']['error'])) {
            return [
                'success' => false,
                'error' => 'Declaration extraction failed: ' . $declarationData['extraction_meta']['error'],
            ];
        }

        // Extract shipping document if provided
        $shippingData = null;
        if ($shippingDocFile) {
            $shippingData = $this->shippingDocExtractor->extract($shippingDocFile);
        }

        // Extract all invoices
        $invoiceDataList = [];
        foreach ($invoiceFiles as $invoiceFile) {
            $invData = $this->invoiceExtractor->extract($invoiceFile);
            if (empty($invData['extraction_meta']['error'])) {
                $invData['_file'] = $invoiceFile;
                $invoiceDataList[] = $invData;
            }
        }

        if (empty($invoiceDataList)) {
            return [
                'success' => false,
                'error' => 'No invoices could be extracted. Please check file formats.',
            ];
        }

        return DB::transaction(function () use (
            $user, $countryId, $declarationFile, $shippingDocFile,
            $declarationData, $shippingData, $invoiceDataList
        ) {
            // 1. Create the Shipment
            $shipment = Shipment::create([
                'organization_id' => $user->organization_id,
                'user_id' => $user->id,
                'country_id' => $countryId,
                'status' => Shipment::STATUS_RELEASED, // Historical = already released
                'source_type' => 'legacy',
                'bill_of_lading_number' => $shippingData['document_number'] ?? null,
                'manifest_number' => $shippingData['manifest_number'] ?? null,
                'carrier_name' => $shippingData['carrier_name'] ?? null,
                'vessel_name' => $shippingData['vessel_name'] ?? null,
                'voyage_number' => $shippingData['voyage_number'] ?? null,
                'port_of_loading' => $shippingData['port_of_loading'] ?? null,
                'port_of_discharge' => $shippingData['port_of_discharge'] ?? null,
                'final_destination' => $shippingData['final_destination'] ?? null,
                'freight_total' => $shippingData['freight_charges'] ?? 0,
                'insurance_total' => $shippingData['insurance_amount'] ?? 0,
                'total_packages' => $shippingData['total_packages'] ?? null,
                'package_type' => $shippingData['package_type'] ?? null,
                'gross_weight_kg' => $shippingData['gross_weight_kg'] ?? null,
                'net_weight_kg' => $shippingData['net_weight_kg'] ?? null,
            ]);

            // 2. Create ShippingDocument if we have shipping data
            if ($shippingDocFile && $shippingData) {
                $shippingStoredPath = $shippingDocFile->store('imports/shipping-documents');
                ShippingDocument::create([
                    'shipment_id' => $shipment->id,
                    'organization_id' => $user->organization_id,
                    'document_type' => $this->guessShippingDocType($shippingData),
                    'document_number' => $shippingData['document_number'] ?? null,
                    'file_path' => $shippingStoredPath,
                    'file_name' => $shippingDocFile->getClientOriginalName(),
                    'file_size' => $shippingDocFile->getSize(),
                    'mime_type' => $shippingDocFile->getMimeType(),
                    'extracted_data' => $shippingData,
                    'is_verified' => true, // Legacy = already verified historically
                ]);
            }

            // 3. Create invoices and link to shipment
            $invoices = [];
            $fobTotal = 0;
            foreach ($invoiceDataList as $invData) {
                $file = $invData['_file'];
                $storedPath = $file->store('imports/invoices');

                $invoiceNumber = $this->makeUniqueInvoiceNumber(
                    $invData['invoice_number'] ?: ('LEG-INV-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6)))
                );
                $invoiceDate = $this->coerceDate($invData['invoice_date']) ?? now()->toDateString();

                $invoice = Invoice::create([
                    'organization_id' => $user->organization_id,
                    'country_id' => $countryId,
                    'user_id' => $user->id,
                    'invoice_number' => $invoiceNumber,
                    'invoice_date' => $invoiceDate,
                    'total_amount' => $invData['total_amount'] ?? null,
                    'status' => 'completed',
                    'processed' => true,
                    'items' => $invData['items'] ?? [],
                    'source_type' => 'legacy',
                    'source_file_path' => $storedPath,
                    'extracted_text' => $invData['extracted_text'] ?? null,
                    'extraction_meta' => $invData['extraction_meta'] ?? null,
                ]);

                // Create invoice items
                $line = 1;
                foreach (($invData['items'] ?? []) as $item) {
                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'organization_id' => $user->organization_id,
                        'user_id' => $user->id,
                        'country_id' => $countryId,
                        'line_number' => $item['line_number'] ?? $line++,
                        'sku' => $this->nullIfEmpty($item['sku'] ?? null),
                        'item_number' => $this->nullIfEmpty($item['item_number'] ?? null),
                        'description' => $item['description'],
                        'quantity' => $item['quantity'] ?? null,
                        'unit_price' => $item['unit_price'] ?? null,
                        'line_total' => $item['line_total'] ?? null,
                        'currency' => $this->nullIfEmpty($item['currency'] ?? $invData['currency'] ?? null),
                        'meta' => null,
                    ]);
                }

                // Link to shipment
                $shipment->invoices()->attach($invoice->id, [
                    'invoice_fob' => $invoice->total_amount,
                ]);

                $fobTotal += (float) ($invoice->total_amount ?? 0);
                $invoices[] = $invoice;
            }

            // 4. Create Declaration Form
            $declStoredPath = $declarationFile->store('imports/declarations');
            $formNumber = $declarationData['form_number'] ?: ('LEG-DEC-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6)));
            $declarationDate = $this->coerceDate($declarationData['declaration_date']) ?? now()->toDateString();

            $declarationForm = DeclarationForm::create([
                'organization_id' => $user->organization_id,
                'country_id' => $countryId,
                'shipment_id' => $shipment->id,
                'invoice_id' => $invoices[0]->id ?? null, // Primary invoice
                'form_number' => $formNumber,
                'declaration_date' => $declarationDate,
                'total_duty' => $declarationData['total_duty'] ?? 0,
                'items' => $declarationData['items'] ?? [],
                'source_type' => 'legacy',
                'source_file_path' => $declStoredPath,
                'extracted_text' => $declarationData['extracted_text'] ?? null,
                'extraction_meta' => $declarationData['extraction_meta'] ?? null,
            ]);

            // Create declaration items
            $line = 1;
            foreach (($declarationData['items'] ?? []) as $item) {
                DeclarationFormItem::create([
                    'declaration_form_id' => $declarationForm->id,
                    'invoice_id' => $invoices[0]->id ?? null,
                    'organization_id' => $user->organization_id,
                    'user_id' => $user->id,
                    'country_id' => $countryId,
                    'line_number' => $item['line_number'] ?? $line++,
                    'sku' => $this->nullIfEmpty($item['sku'] ?? null),
                    'item_number' => $this->nullIfEmpty($item['item_number'] ?? null),
                    'description' => $item['description'],
                    'quantity' => $item['quantity'] ?? null,
                    'unit_price' => $item['unit_price'] ?? null,
                    'line_total' => $item['line_total'] ?? null,
                    'currency' => $this->nullIfEmpty($item['currency'] ?? $declarationData['currency'] ?? null),
                    'hs_code' => $this->nullIfEmpty($item['hs_code'] ?? null),
                    'hs_description' => $this->nullIfEmpty($item['hs_description'] ?? null),
                    'meta' => null,
                ]);
            }

            // 5. Update shipment totals
            $shipment->update([
                'fob_total' => $fobTotal,
                'cif_total' => $fobTotal + (float) $shipment->freight_total + (float) $shipment->insurance_total,
            ]);
            $shipment->prorateToInvoices();

            // 6. Match invoice items to declaration items
            $totalMatched = 0;
            foreach ($invoices as $invoice) {
                $matchResult = $this->matcher->matchInvoiceToDeclaration($invoice, $declarationForm, $user);
                $totalMatched += $matchResult['matched'] ?? 0;
            }

            return [
                'success' => true,
                'shipment' => $shipment,
                'invoices' => $invoices,
                'declaration_form' => $declarationForm,
                'items_matched' => $totalMatched,
            ];
        });
    }

    /**
     * Guess shipping document type from extracted data
     */
    protected function guessShippingDocType(array $data): string
    {
        $docNumber = strtoupper($data['document_number'] ?? '');
        if (str_contains($docNumber, 'AWB') || str_contains($docNumber, 'MAWB') || str_contains($docNumber, 'HAWB')) {
            return ShippingDocument::TYPE_AIR_WAYBILL;
        }
        return ShippingDocument::TYPE_BILL_OF_LADING;
    }

    protected function findOrCreateInvoiceForDeclaration(User $user, int $countryId, array $extracted): Invoice
    {
        $invoiceNumber = $this->nullIfEmpty($extracted['invoice_number'] ?? null);
        if ($invoiceNumber) {
            $existing = Invoice::withoutGlobalScopes()
                ->where('organization_id', $user->organization_id)
                ->where('country_id', $countryId)
                ->where('invoice_number', $invoiceNumber)
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        $invoiceDate = $this->coerceDate($extracted['invoice_date'] ?? null);
        $totalAmount = $extracted['total_amount'] ?? null;

        if ($invoiceDate && $totalAmount !== null) {
            $existing = Invoice::withoutGlobalScopes()
                ->where('organization_id', $user->organization_id)
                ->where('country_id', $countryId)
                ->whereBetween('invoice_date', [
                    date('Y-m-d', strtotime($invoiceDate . ' -7 days')),
                    date('Y-m-d', strtotime($invoiceDate . ' +7 days')),
                ])
                ->orderByRaw('ABS(total_amount - ?) asc', [$totalAmount])
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        // Create a placeholder imported invoice so the declaration can attach.
        $newNumber = $this->makeUniqueInvoiceNumber($invoiceNumber ?: ('IMP-INV-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6))));

        return Invoice::create([
            'organization_id' => $user->organization_id,
            'country_id' => $countryId,
            'user_id' => $user->id,
            'invoice_number' => $newNumber,
            'invoice_date' => $invoiceDate ?? now()->toDateString(),
            'total_amount' => $totalAmount,
            'status' => 'completed',
            'processed' => true,
            'items' => [],
            'source_type' => 'imported',
            'source_file_path' => null,
            'extracted_text' => null,
            'extraction_meta' => [
                'created_from' => 'declaration_import',
                'original_invoice_number' => $invoiceNumber,
            ],
        ]);
    }

    protected function seedInvoiceItemsFromDeclaration(Invoice $invoice, User $user, int $countryId, array $extracted): void
    {
        $line = 1;
        foreach (($extracted['items'] ?? []) as $item) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'organization_id' => $user->organization_id,
                'user_id' => $user->id,
                'country_id' => $countryId,
                'line_number' => $item['line_number'] ?? $line++,
                'sku' => $this->nullIfEmpty($item['sku'] ?? null),
                'item_number' => $this->nullIfEmpty($item['item_number'] ?? null),
                'description' => $item['description'],
                'quantity' => $item['quantity'] ?? null,
                'unit_price' => $item['unit_price'] ?? null,
                'line_total' => $item['line_total'] ?? null,
                'currency' => $this->nullIfEmpty($item['currency'] ?? $extracted['currency'] ?? null),
                'meta' => [
                    'seeded_from_declaration' => true,
                ],
            ]);
        }
    }

    protected function makeUniqueInvoiceNumber(string $invoiceNumber): string
    {
        $invoiceNumber = trim($invoiceNumber);
        $base = $invoiceNumber !== '' ? $invoiceNumber : ('IMP-INV-' . now()->format('Ymd'));

        $candidate = $base;
        $i = 1;
        while (Invoice::withoutGlobalScopes()->where('invoice_number', $candidate)->exists()) {
            $i++;
            $candidate = $base . '-' . $i;
        }

        return $candidate;
    }

    protected function nullIfEmpty(?string $value): ?string
    {
        if ($value === null) return null;
        $v = trim($value);
        return $v === '' ? null : $v;
    }

    protected function coerceDate(?string $value): ?string
    {
        if (!$value) return null;
        $value = trim($value);

        // Accept YYYY-MM-DD, otherwise attempt strtotime
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        $ts = strtotime($value);
        if ($ts === false) {
            return null;
        }
        return date('Y-m-d', $ts);
    }
}

