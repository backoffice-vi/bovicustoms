# Web Submission Recent Findings

This document captures the live issues found while submitting Trade Declarations to CAPS via the Playwright web automation in the last two weeks (April–May 2026). The intent is to keep the lessons in one place so we do not repeat them and so future fixes target the right code.

For the architectural reference, see [caps-submission.md](caps-submission.md).

---

## 1. MFT (Manifest Number) Property Bug — FIXED

### Symptom
CAPS query report flagged TD `004463956` (declaration #60) with "incorrect MFT #". Investigation showed the manifest number was being submitted as an empty string for many declarations.

### Root cause
In `app/Services/WebFormSubmission/WebFormDataMapper.php`, the manifest fallback chain referenced `$shipment?->bill_of_lading`, but the actual database column is `bill_of_lading_number`. There is no accessor named `bill_of_lading` on the Shipment model, so the property silently returned `null`. As a result the manifest fallback chain `manifest_number → bill_of_lading → ''` collapsed to `''` whenever `manifest_number` was null.

### Fix applied
- `WebFormDataMapper.php:295` — manifest fallback now uses `$shipment?->bill_of_lading_number`.
- `WebFormDataMapper.php:973` — AI context shipment block now uses `bill_of_lading_number`.

The corrected fallback chain is `manifest_number → bill_of_lading_number → ''`.

### What MFT actually is
The MFT (Manifest) number is **not** the B/L number. It is the carrier's manifest number assigned when the vessel manifest is registered with CAPS. For shipment #55 (Admiral Pride voyage 2612) the real manifest was `254`, printed on the carrier's "PLEASE RELEASE CARGO" stamp on the B/L. The B/L number `2612SJU027` was wrong as a manifest.

### Operational implication
- The fallback to B/L is a "better than empty" defensive measure but is still likely to be flagged by customs.
- We should capture the carrier-issued manifest number explicitly during shipment creation/edit and stop relying on the B/L fallback when possible.

---

## 2. FOB Definition and Calculation

### Symptom
CAPS query report flagged "incorrect FOB" on declaration #60. Original FOB was $1,376.71; correct was $1,385.54.

### Root cause
The invoice subtotal had a $0.30 split-case charge plus rounding differences that did not flow into the declaration's FOB. The invoice line items summed to a different value than the stored `fob_value`.

### Definition (for clarity in future work)
- **FOB** = total invoice value of the goods only, before freight and insurance.
- **CIF** = FOB + freight + insurance (must be recalculated whenever FOB or freight or insurance changes).

### Fix applied
- Updated invoice, shipment, and declaration form records with the correct FOB ($1,385.54).
- Recalculated CIF accordingly.
- Re-edited the live CAPS TD via Playwright to update item-level FOBs (CAPS does not have a header-level FOB field — it is the sum of item FOBs).

### Operational implication
- Whenever line items change, FOB on the declaration header must be re-derived from the items.
- A reusable "recalculate totals" service or hook should be applied any time invoice/declaration items are edited.

---

## 3. B/L File Missing From Disk

### Symptom
For shipment #56 the Laravel `ShippingDocument` record pointed at `shipping-documents/56/53ad9010-68c2-4b48-b012-365d82048b34.jpeg`, but the file was not present locally. CAPS attachment upload could not include the B/L.

### Root cause
The local DB had been copied from the production server, but `storage/app/shipping-documents/` had not been rsynced. The DB metadata existed without the underlying binary.

### Fix
- The original B/L was provided manually by the user.
- The file was uploaded to CAPS as a one-off attachment via the "attach-only" Playwright action.
- Local disk was not repopulated automatically; we should formalize this as part of dev-env setup.

### Operational implication
- When syncing prod to local, always rsync `storage/app/shipping-documents/`, `storage/app/invoices/`, and other binary asset folders.
- The attachment gatherer should warn the user when a referenced file is missing on disk rather than silently skipping it.

---

## 4. Tariff "Padded Heading" Rejection

### Symptom
For TD `004496360` (shipment #56), records 4, 8, 12, 16, 20, 27, 28, 29, 33, 35, 38, and 51 were rejected with `TARIFF NO. NOT KNOWN`.

### Root cause
The mapper produced codes such as `2009300`, `2009600`, and `2202900` — heading-level codes padded with trailing zeros. CAPS only accepts valid 7-digit BVI tariff codes; padded heading codes are not in the CAPS reference table. The local `customs_codes` table only had 6-digit headings for these codes, so `findBest7DigitCode()` fell back to padding instead of finding a real descendant.

### Required follow-up (out of scope for this ticket)
A new "no short tariff" rule should be enforced in `WebFormDataMapper::resolveCapsTariffCode()`:
- Never emit `[heading]00` or `[heading]0000` if no descendant matches.
- Either return a known "Other" 7-digit catch-all (`X909`-style) or fail loudly so the user must classify the item.
- The project rule `.cursor/rules/no-short-tariff.mdc` (to be created in a follow-up) will codify this.

---

## 5. Unit Code Mismatch on Beverages (Record 29)

### Symptom
Record 29 had tariff `2203001` (beer) submitted with unit `UNIT`. CAPS rejected with `QUANTITY UNITS NOT KNOWN`.

### Root cause
Beverages need volume units (`LTR`), not generic `UNIT`. The current `mapUnitCode()` defaults to `UNIT` because that is what CAPS expects for non-volume goods, but it should be tariff-prefix aware.

### Required follow-up (out of scope for this ticket)
Update `mapUnitCode()` in both `WebFormDataMapper` and `CapsT12Generator` to detect tariff prefixes that imply volume (`2201`, `2202`, `2203`, etc.) and default to `LTR`. Similar logic for weight-only categories.

---

## 6. Record 51 Incomplete After Resume

### Symptom
After a Playwright resume run for TD `004496360`, CAPS reported `FIELD NOT COMPLETE Rec 51` and `TOTAL INSURANCE AMOUNT NOT CORRECT`.

### Root cause
The resume flow in `playwright/caps-web-submitter.mjs` skipped record 51 because a previous crashed run had partially filled it. The resume logic counted record 51 as already done even though CPC and charge lines were missing.

### Required follow-up (out of scope for this ticket)
- The resume flow must validate that each record it skips has CPC, tariff, FOB, charge lines, and tax lines populated. If any required field is missing it should refill the record.
- `caps-web-submitter.mjs` resume tests should include a "partial record" scenario.

---

## 7. Unreliable `validation_passed` From Automation

### Symptom
The Playwright submitter reported `validation_passed: true` even though the live CAPS validation screen showed errors.

### Root cause
The error-detection regex did not match all CAPS error phrases, and timing windows allowed the script to read the page before validation finished rendering.

### Required follow-up (out of scope for this ticket)
- Refine the error-detection regex to cover the full vocabulary: `NOT COMPLETE`, `NOT KNOWN`, `NOT FOUND`, `NOT ACTIVE`, `NOT VALID`, `NOT CORRECT`, `INCORRECT`.
- Always capture a screenshot of the assessment page before declaring success, so we have evidence.
- Treat `validation_passed: true` as advisory; do not auto-submit based on it alone — require an explicit user confirmation.

---

## Quick Reference Table

| Issue | Status | Code Path |
|-------|--------|-----------|
| MFT bill_of_lading vs bill_of_lading_number | Fixed | `WebFormDataMapper.php:295,973` |
| FOB definition + recalculation | Documented | n/a — process |
| B/L missing from disk | Documented | n/a — sync process |
| Padded-heading tariff rejection | Pending follow-up | `WebFormDataMapper::resolveCapsTariffCode` |
| Beverage unit (LTR) | Pending follow-up | `mapUnitCode` (web + FTP) |
| Resume skips partial records | Pending follow-up | `playwright/caps-web-submitter.mjs` |
| Unreliable `validation_passed` | Pending follow-up | `playwright/caps-web-submitter.mjs` |
