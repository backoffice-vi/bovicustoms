<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\CustomsCode;
use App\Models\CountryLevy;
use App\Models\DeclarationForm;
use Illuminate\Support\Collection;

class DutyCalculationService
{
    /**
     * Calculate all duties and levies for a shipment
     *
     * @param Shipment $shipment
     * @return array Complete calculation breakdown
     */
    public function calculateForShipment(Shipment $shipment): array
    {
        $countryId = $shipment->country_id;
        
        // Get all invoices in the shipment with their items
        $invoices = $shipment->invoices()->with('invoiceItems')->get();
        
        // Calculate totals
        $fobTotal = $this->calculateFobTotal($invoices);
        $freightTotal = (float) $shipment->freight_total;
        $insuranceTotal = $this->calculateInsurance($shipment, $fobTotal);
        $cifTotal = $fobTotal + $freightTotal + $insuranceTotal;

        // Calculate proration ratios for each invoice
        $invoiceProrations = $this->calculateInvoiceProrations($invoices, $fobTotal, $freightTotal, $insuranceTotal);

        // Calculate duty for each line item
        $itemDuties = $this->calculateItemDuties($invoices, $invoiceProrations, $countryId);

        // Sum up customs duty
        $customsDutyTotal = collect($itemDuties)->sum('duty_amount');

        // Calculate country-level levies
        $levyResult = CountryLevy::calculateAllForCountry(
            $countryId,
            $fobTotal,
            $cifTotal,
            $customsDutyTotal,
            $this->getTotalQuantity($invoices),
            (float) $shipment->gross_weight_kg
        );

        // Extract wharfage from levies
        $wharfageTotal = collect($levyResult['levies'])
            ->filter(fn($l) => $l['levy_code'] === CountryLevy::CODE_WHARFAGE)
            ->sum('amount');

        $otherLeviesTotal = $levyResult['total_levies'] - $wharfageTotal;

        // Total payable
        $totalDuty = $customsDutyTotal + $levyResult['total_levies'];

        return [
            'fob_value' => round($fobTotal, 2),
            'freight_total' => round($freightTotal, 2),
            'insurance_total' => round($insuranceTotal, 2),
            'cif_value' => round($cifTotal, 2),
            
            'customs_duty_total' => round($customsDutyTotal, 2),
            'wharfage_total' => round($wharfageTotal, 2),
            'other_levies_total' => round($otherLeviesTotal, 2),
            'total_duty' => round($totalDuty, 2),
            
            'invoice_prorations' => $invoiceProrations,
            'item_duties' => $itemDuties,
            'levies' => $levyResult['levies'],
            
            'duty_breakdown' => $this->groupDutiesByTariff($itemDuties),
            'levy_breakdown' => $levyResult['levies'],
            
            'summary' => [
                'invoice_count' => $invoices->count(),
                'item_count' => collect($itemDuties)->count(),
                'tariff_codes_used' => collect($itemDuties)->pluck('tariff_code')->unique()->count(),
            ],
        ];
    }

    /**
     * Calculate FOB total from all invoices
     */
    protected function calculateFobTotal(Collection $invoices): float
    {
        return $invoices->sum('total_amount');
    }

    /**
     * Calculate insurance based on shipment settings
     */
    protected function calculateInsurance(Shipment $shipment, float $fobTotal): float
    {
        return match ($shipment->insurance_method) {
            Shipment::INSURANCE_PERCENTAGE => $fobTotal * (($shipment->insurance_percentage ?? 0) / 100),
            Shipment::INSURANCE_MANUAL, Shipment::INSURANCE_DOCUMENT => (float) $shipment->insurance_total,
            default => (float) $shipment->insurance_total,
        };
    }

    /**
     * Calculate proration for each invoice in shipment
     */
    protected function calculateInvoiceProrations(Collection $invoices, float $fobTotal, float $freightTotal, float $insuranceTotal): array
    {
        $prorations = [];

        foreach ($invoices as $invoice) {
            $invoiceFob = (float) $invoice->total_amount;
            $ratio = $fobTotal > 0 ? $invoiceFob / $fobTotal : 0;

            $prorations[$invoice->id] = [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'fob_value' => round($invoiceFob, 2),
                'fob_ratio' => round($ratio, 6),
                'prorated_freight' => round($freightTotal * $ratio, 2),
                'prorated_insurance' => round($insuranceTotal * $ratio, 2),
                'cif_value' => round($invoiceFob + ($freightTotal * $ratio) + ($insuranceTotal * $ratio), 2),
            ];
        }

        return $prorations;
    }

    /**
     * Calculate duty for each line item across all invoices
     */
    protected function calculateItemDuties(Collection $invoices, array $invoiceProrations, int $countryId): array
    {
        $itemDuties = [];

        foreach ($invoices as $invoice) {
            $proration = $invoiceProrations[$invoice->id] ?? null;
            if (!$proration) continue;

            $invoiceItems = $invoice->invoiceItems ?? collect();
            $invoiceFob = $proration['fob_value'];
            
            foreach ($invoiceItems as $item) {
                $itemFob = (float) ($item->line_total ?? (($item->quantity ?? 1) * ($item->unit_price ?? 0)));
                
                // Calculate item's share of freight and insurance
                $itemRatio = $invoiceFob > 0 ? $itemFob / $invoiceFob : 0;
                $itemFreight = $proration['prorated_freight'] * $itemRatio;
                $itemInsurance = $proration['prorated_insurance'] * $itemRatio;
                $itemCif = $itemFob + $itemFreight + $itemInsurance;

                // Look up tariff rate
                $tariffCode = $item->customs_code ?? $item->hs_code ?? null;
                $dutyRate = 0;
                $dutyAmount = 0;
                $tariffDescription = null;

                // First, use the rate stored on the item (from classification)
                if ($item->duty_rate !== null) {
                    $dutyRate = (float) $item->duty_rate;
                    $tariffDescription = $item->customs_code_description;
                    $dutyAmount = $itemCif * ($dutyRate / 100);
                }
                // Fall back to lookup in customs_codes table
                elseif ($tariffCode) {
                    $customsCode = CustomsCode::forCountry($countryId)
                        ->where('code', 'like', $tariffCode . '%')
                        ->first();

                    if ($customsCode) {
                        $dutyRate = (float) $customsCode->duty_rate;
                        $tariffDescription = $customsCode->description;
                        // Duty is calculated on CIF value
                        $dutyAmount = $itemCif * ($dutyRate / 100);
                    }
                }

                $itemDuties[] = [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'item_id' => $item->id,
                    'line_number' => $item->line_number,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'fob_value' => round($itemFob, 2),
                    'freight_share' => round($itemFreight, 2),
                    'insurance_share' => round($itemInsurance, 2),
                    'cif_value' => round($itemCif, 2),
                    'tariff_code' => $tariffCode,
                    'tariff_description' => $tariffDescription,
                    'duty_rate' => $dutyRate,
                    'duty_amount' => round($dutyAmount, 2),
                ];
            }
        }

        return $itemDuties;
    }

    /**
     * Group duties by tariff code for summary
     */
    protected function groupDutiesByTariff(array $itemDuties): array
    {
        $grouped = [];

        foreach ($itemDuties as $item) {
            $code = $item['tariff_code'] ?? 'unclassified';
            
            if (!isset($grouped[$code])) {
                $grouped[$code] = [
                    'tariff_code' => $code,
                    'tariff_description' => $item['tariff_description'],
                    'duty_rate' => $item['duty_rate'],
                    'total_cif' => 0,
                    'total_duty' => 0,
                    'item_count' => 0,
                ];
            }

            $grouped[$code]['total_cif'] += $item['cif_value'];
            $grouped[$code]['total_duty'] += $item['duty_amount'];
            $grouped[$code]['item_count']++;
        }

        // Round totals
        foreach ($grouped as &$group) {
            $group['total_cif'] = round($group['total_cif'], 2);
            $group['total_duty'] = round($group['total_duty'], 2);
        }

        return array_values($grouped);
    }

    /**
     * Get total quantity across all items
     */
    protected function getTotalQuantity(Collection $invoices): float
    {
        return $invoices->flatMap(fn($inv) => $inv->invoiceItems ?? collect())
            ->sum('quantity') ?? 1;
    }

    /**
     * Calculate duties for a single invoice (standalone, not in shipment)
     */
    public function calculateForInvoice(Invoice $invoice, float $freight = 0, float $insurance = 0): array
    {
        $countryId = $invoice->country_id;
        $fobTotal = (float) $invoice->total_amount;
        $cifTotal = $fobTotal + $freight + $insurance;

        // Create a pseudo-proration for single invoice
        $invoiceProration = [
            $invoice->id => [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'fob_value' => round($fobTotal, 2),
                'fob_ratio' => 1.0,
                'prorated_freight' => round($freight, 2),
                'prorated_insurance' => round($insurance, 2),
                'cif_value' => round($cifTotal, 2),
            ],
        ];

        // Calculate item duties
        $itemDuties = $this->calculateItemDuties(collect([$invoice]), $invoiceProration, $countryId);
        $customsDutyTotal = collect($itemDuties)->sum('duty_amount');

        // Calculate levies
        $levyResult = CountryLevy::calculateAllForCountry(
            $countryId,
            $fobTotal,
            $cifTotal,
            $customsDutyTotal
        );

        $wharfageTotal = collect($levyResult['levies'])
            ->filter(fn($l) => $l['levy_code'] === CountryLevy::CODE_WHARFAGE)
            ->sum('amount');

        $totalDuty = $customsDutyTotal + $levyResult['total_levies'];

        return [
            'fob_value' => round($fobTotal, 2),
            'freight_total' => round($freight, 2),
            'insurance_total' => round($insurance, 2),
            'cif_value' => round($cifTotal, 2),
            'customs_duty_total' => round($customsDutyTotal, 2),
            'wharfage_total' => round($wharfageTotal, 2),
            'other_levies_total' => round($levyResult['total_levies'] - $wharfageTotal, 2),
            'total_duty' => round($totalDuty, 2),
            'item_duties' => $itemDuties,
            'levies' => $levyResult['levies'],
            'duty_breakdown' => $this->groupDutiesByTariff($itemDuties),
        ];
    }

    /**
     * Apply calculation results to a declaration form
     */
    public function applyToDeclaration(DeclarationForm $declaration, array $calculation): void
    {
        $declaration->update([
            'fob_value' => $calculation['fob_value'],
            'freight_total' => $calculation['freight_total'],
            'insurance_total' => $calculation['insurance_total'],
            'cif_value' => $calculation['cif_value'],
            'customs_duty_total' => $calculation['customs_duty_total'],
            'wharfage_total' => $calculation['wharfage_total'],
            'other_levies_total' => $calculation['other_levies_total'],
            'total_duty' => $calculation['total_duty'],
            'duty_breakdown' => $calculation['duty_breakdown'] ?? null,
            'levy_breakdown' => $calculation['levy_breakdown'] ?? $calculation['levies'] ?? null,
        ]);
    }

    /**
     * Recalculate and update a shipment's totals
     */
    public function recalculateShipment(Shipment $shipment): array
    {
        $calculation = $this->calculateForShipment($shipment);

        // Update shipment totals
        $shipment->update([
            'fob_total' => $calculation['fob_value'],
            'cif_total' => $calculation['cif_value'],
        ]);

        // Update invoice prorations
        foreach ($calculation['invoice_prorations'] as $proration) {
            $shipment->invoices()->updateExistingPivot($proration['invoice_id'], [
                'invoice_fob' => $proration['fob_value'],
                'prorated_freight' => $proration['prorated_freight'],
                'prorated_insurance' => $proration['prorated_insurance'],
            ]);
        }

        return $calculation;
    }

    /**
     * Generate a duty summary suitable for display
     */
    public function formatForDisplay(array $calculation): array
    {
        return [
            'values' => [
                ['label' => 'FOB Value', 'amount' => $calculation['fob_value'], 'format' => 'currency'],
                ['label' => 'Freight', 'amount' => $calculation['freight_total'], 'format' => 'currency'],
                ['label' => 'Insurance', 'amount' => $calculation['insurance_total'], 'format' => 'currency'],
                ['label' => 'CIF Value', 'amount' => $calculation['cif_value'], 'format' => 'currency', 'highlight' => true],
            ],
            'duties' => [
                ['label' => 'Customs Duty (CUD)', 'amount' => $calculation['customs_duty_total'], 'format' => 'currency'],
                ['label' => 'Wharfage (WHA)', 'amount' => $calculation['wharfage_total'], 'format' => 'currency'],
                ['label' => 'Other Levies', 'amount' => $calculation['other_levies_total'], 'format' => 'currency'],
            ],
            'total' => [
                'label' => 'Total Payable',
                'amount' => $calculation['total_duty'],
                'format' => 'currency',
            ],
            'breakdown_by_tariff' => $calculation['duty_breakdown'] ?? [],
            'levies_detail' => $calculation['levies'] ?? [],
        ];
    }
}
