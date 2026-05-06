<?php

namespace App\Services\FtpSubmission;

use App\Models\CustomsCode;
use App\Models\DeclarationForm;
use App\Models\DeclarationFormItem;
use App\Models\InvoiceItem;
use App\Models\OrganizationSubmissionCredential;
use App\Models\WebFormSubmission;
use App\Services\DutyCalculationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Apply broker-confirmed fixes to a rejected declaration, regenerate the T12,
 * and resubmit it via FTP. Always links the new submission back to the parent
 * via parent_submission_id so the resubmission chain is queryable.
 *
 * Inputs:
 *   - WebFormSubmission $parent  : the rejected submission
 *   - array $fixes               : per-item changes the broker confirmed in the
 *                                  Fix Classification UI:
 *
 *      [
 *        [
 *          'declaration_form_item_id' => int,   // required if invoice_item_id missing
 *          'invoice_item_id'          => int,   // required if declaration_form_item_id missing
 *          'new_code'                 => string,// dotted form (e.g. "1006.301") or 7-digit
 *        ],
 *        ...
 *      ]
 *
 * Pipeline:
 *   1. Validate fixes — every new_code must resolve to an exact customs_codes row.
 *   2. Apply fixes to the underlying invoice_items (the canonical source).
 *   3. Recompute duty for the shipment via DutyCalculationService.
 *   4. Wipe and rebuild declaration_form_items so the T12 sees fresh data.
 *   5. Generate, pre-validate, and submit the new T12 via FtpSubmissionService
 *      with auto-attach enabled.
 *   6. Set parent_submission_id on the new submission so the chain is preserved.
 */
class FixAndResubmitService
{
    protected FtpSubmissionService $ftp;
    protected DutyCalculationService $dutyCalculator;
    protected CapsT12Generator $generator;

    public function __construct(
        FtpSubmissionService $ftp,
        DutyCalculationService $dutyCalculator,
        CapsT12Generator $generator
    ) {
        $this->ftp = $ftp;
        $this->dutyCalculator = $dutyCalculator;
        $this->generator = $generator;
    }

    /**
     * Apply fixes and resubmit. Returns the new WebFormSubmission.
     *
     * @throws \RuntimeException on validation or submission failure
     */
    public function apply(
        WebFormSubmission $parent,
        DeclarationForm $declaration,
        OrganizationSubmissionCredential $credentials,
        array $fixes,
        bool $autoAttach = true
    ): WebFormSubmission {
        if ($parent->retry_count >= 3) {
            throw new \RuntimeException('Maximum retry count reached (3). Manual intervention required.');
        }

        $declaration->loadMissing(['shipment.invoices.invoiceItems', 'invoice.invoiceItems', 'country']);

        $shipment = $declaration->shipment;
        if (!$shipment) {
            throw new \RuntimeException('Cannot resubmit: declaration has no associated shipment.');
        }

        $resolvedFixes = $this->resolveFixes($fixes, $declaration);

        DB::beginTransaction();
        try {
            $this->applyFixesToInvoiceItems($resolvedFixes);

            $calculation = $this->dutyCalculator->calculateForShipment($shipment);
            $this->dutyCalculator->applyToDeclaration($declaration, $calculation);

            $this->rebuildDeclarationItems($declaration, $calculation);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('FixAndResubmitService: failed to apply fixes', [
                'parent_submission_id' => $parent->id,
                'declaration_id' => $declaration->id,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Failed to apply fixes: ' . $e->getMessage(), 0, $e);
        }

        $declaration->refresh();

        try {
            $newSubmission = $this->ftp->submit($declaration, $credentials, true, $autoAttach);
        } catch (\Throwable $e) {
            Log::error('FixAndResubmitService: FTP resubmission failed', [
                'parent_submission_id' => $parent->id,
                'declaration_id' => $declaration->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        $newSubmission->update([
            'parent_submission_id' => $parent->id,
            'retry_count' => $parent->retry_count + 1,
        ]);

        Log::info('FixAndResubmitService: resubmission completed', [
            'parent_submission_id' => $parent->id,
            'new_submission_id' => $newSubmission->id,
            'fixes_applied' => count($resolvedFixes),
            'filename' => $newSubmission->external_reference,
        ]);

        return $newSubmission;
    }

    // ===========================================================
    // Fix resolution
    // ===========================================================

    /**
     * Resolve a list of broker-supplied fixes into concrete
     * (InvoiceItem, CustomsCode) pairs. Throws on any unresolvable fix so
     * we don't partially apply.
     *
     * @return array<int,array{invoice_item: InvoiceItem, customs_code: CustomsCode, dotted: string}>
     */
    protected function resolveFixes(array $fixes, DeclarationForm $declaration): array
    {
        if (empty($fixes)) {
            throw new \RuntimeException('No fixes provided.');
        }

        $declaration->loadMissing('declarationItems');
        $declarationItems = $declaration->declarationItems->keyBy('id');
        $countryId = $declaration->country_id;

        $resolved = [];

        foreach ($fixes as $idx => $fix) {
            $newCode = isset($fix['new_code']) ? trim((string) $fix['new_code']) : '';
            if ($newCode === '') {
                throw new \RuntimeException("Fix #{$idx}: new_code is required.");
            }

            $customsCode = $this->lookupExactCustomsCode($newCode, $countryId);
            if (!$customsCode) {
                throw new \RuntimeException(
                    "Fix #{$idx}: tariff '{$newCode}' is not an exact match in customs_codes for this country. "
                    . 'Per the no-tariff-guessing rule, the resubmission will not proceed.'
                );
            }

            $invoiceItem = $this->resolveInvoiceItem($fix, $declarationItems);
            if (!$invoiceItem) {
                throw new \RuntimeException("Fix #{$idx}: could not resolve target invoice item.");
            }

            $resolved[] = [
                'invoice_item' => $invoiceItem,
                'customs_code' => $customsCode,
                'dotted' => (string) $customsCode->code,
            ];
        }

        return $resolved;
    }

    protected function resolveInvoiceItem(array $fix, $declarationItems): ?InvoiceItem
    {
        $invoiceItemId = $fix['invoice_item_id'] ?? null;
        if ($invoiceItemId) {
            return InvoiceItem::withoutGlobalScopes()->find($invoiceItemId);
        }

        $declarationItemId = $fix['declaration_form_item_id'] ?? null;
        if ($declarationItemId && $declarationItems->has($declarationItemId)) {
            $di = $declarationItems->get($declarationItemId);
            if ($di->invoice_id) {
                $line = $di->line_number;
                $candidate = InvoiceItem::withoutGlobalScopes()
                    ->where('invoice_id', $di->invoice_id)
                    ->where('description', $di->description)
                    ->first();

                if ($candidate) {
                    return $candidate;
                }

                return InvoiceItem::withoutGlobalScopes()
                    ->where('invoice_id', $di->invoice_id)
                    ->orderBy('id')
                    ->skip(max(0, ($line ?? 1) - 1))
                    ->first();
            }
        }

        return null;
    }

    protected function lookupExactCustomsCode(string $code, ?int $countryId): ?CustomsCode
    {
        $digits = preg_replace('/\D+/', '', $code) ?? '';
        $candidates = array_filter([$code]);

        if (strlen($digits) === 7) {
            $candidates[] = $digits;
            $candidates[] = substr($digits, 0, 4) . '.' . substr($digits, 4);
            if (substr($digits, -1) === '0') {
                $candidates[] = substr($digits, 0, 4) . '.' . substr($digits, 4, 2);
            }
        } elseif (strlen($digits) === 6) {
            $candidates[] = substr($digits, 0, 4) . '.' . substr($digits, 4);
        }

        $candidates = array_values(array_unique(array_filter($candidates)));

        $query = CustomsCode::query()->whereIn('code', $candidates);
        if ($countryId) {
            $query->where('country_id', $countryId);
        }
        return $query->first();
    }

    // ===========================================================
    // Mutators
    // ===========================================================

    protected function applyFixesToInvoiceItems(array $resolvedFixes): void
    {
        foreach ($resolvedFixes as $fix) {
            /** @var InvoiceItem $item */
            $item = $fix['invoice_item'];
            /** @var CustomsCode $code */
            $code = $fix['customs_code'];

            $item->forceFill([
                'customs_code' => $code->code,
                'duty_rate' => $code->duty_rate,
                'customs_code_description' => Str::limit((string) $code->description, 250, '...'),
            ])->saveQuietly();
        }
    }

    /**
     * Wipe and rebuild declaration_form_items from the fresh duty calculation
     * so the T12 generator sees the corrected codes/rates.
     */
    protected function rebuildDeclarationItems(DeclarationForm $declaration, array $calculation): void
    {
        DeclarationFormItem::where('declaration_form_id', $declaration->id)->delete();

        $organizationId = $declaration->organization_id;
        $userId = $declaration->user_id ?? auth()->id();
        $countryId = $declaration->country_id;
        $line = 1;

        foreach ($calculation['item_duties'] ?? [] as $itemDuty) {
            DeclarationFormItem::create([
                'declaration_form_id' => $declaration->id,
                'invoice_id' => $itemDuty['invoice_id'] ?? null,
                'organization_id' => $organizationId,
                'user_id' => $userId,
                'country_id' => $countryId,
                'line_number' => $line++,
                'description' => $itemDuty['description'] ?? '',
                'quantity' => $itemDuty['quantity'] ?? 0,
                'unit_price' => $itemDuty['unit_price'] ?? 0,
                'line_total' => $itemDuty['fob_value'] ?? 0,
                'hs_code' => $itemDuty['tariff_code'] ?? null,
                'hs_description' => Str::limit((string) ($itemDuty['tariff_description'] ?? ''), 250, '...'),
            ]);
        }
    }
}
