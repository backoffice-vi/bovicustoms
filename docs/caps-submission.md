# CAPS Automated Submission

This document explains how the BVI Customs application automates the submission of Trade Declarations (TDs) to the **CAPS (Customs Automated Processing System)** web portal at `https://caps.gov.vg`.

---

## Overview

The system takes a completed Declaration Form from the local database, maps all its data to the exact field structure CAPS expects, and uses browser automation (Playwright) to fill and save the TD on the CAPS portal. Every code sent to CAPS (carrier, port, country, CPC, tax type, etc.) is validated against the **Reference Data** stored under the BVI country record.

### Architecture

```
┌──────────────────┐     ┌──────────────────────┐     ┌──────────────────┐
│  DeclarationForm  │────▶│  WebFormDataMapper    │────▶│  JSON payload    │
│  + Items          │     │  (PHP – maps & codes) │     │  (headerData +   │
│  + Shipment       │     │  • tariff resolution  │     │   items[])       │
│  + Invoice        │     │  • HS code grouping   │     │  + td_number     │
│  + Contacts       │     │  • ref data lookups   │     │  + attachments   │
│                   │     └──────────────────────┘     └────────┬─────────┘
└──────────────────┘                                           │
                                                               ▼
                                                    ┌──────────────────────┐
        ┌─────────────────────┐                     │  caps-web-submitter  │
        │  CAPS Web Portal    │◀────────────────────│  (Playwright / JS)   │
        │  caps.gov.vg        │  browser automation │  • create or edit TD │
        └─────────────────────┘                     │  • delete extra recs │
                                                    │  • attach files      │
                                                    └──────────────────────┘
```

**Key files:**

| File | Purpose |
|------|---------|
| `app/Services/WebFormSubmission/WebFormDataMapper.php` | Maps declaration data to CAPS fields, resolves tariff codes, groups items |
| `playwright/caps-web-submitter.mjs` | Browser automation script that fills the CAPS form (create or edit) |
| `app/Models/CountryReferenceData.php` | Model for the reference data (carriers, ports, CPCs, etc.) |
| `app/Models/Country.php` | Country-level settings including `caps_group_items` |

---

## Reference Data

All Lookup/dropdown codes that CAPS requires are stored in the `country_reference_data` table, scoped to BVI (`country_id = 1`). This data is managed via the admin panel at `/admin/countries/1` under the **Reference Data** tab.

### Reference Types

| Type | Count | Example Codes | Used For |
|------|-------|---------------|----------|
| `carrier` | 330 | ADP (Admiral Pride), TRP (Tropical), SMC (Seaboard) | Carrier ID field |
| `port` | 21 | PP (Port Purcell), BI (Beef Island Airport) | Port of Arrival |
| `country` | 246 | US (United States), GB (United Kingdom) | Country fields |
| `cpc` | 97 | C400 (Release to Free Circulation), C401 (Replacement Goods) | Customs Procedure Code |
| `currency` | 45 | USD (US Dollar), EUR (Euro) | Currency code |
| `unit` | 36 | EA (Each), LB (Pound), GAL (Gallon) | Units / Package types |
| `tax_type` | 37 | CUD (Customs Duty), WHA (Wharfage) | Tax type rows |
| `charge_code` | 21 | FRT (Freight Charges), INS (Insurance Charges) | Charges/deductions |
| `exempt_id` | 40 | E001 (Government Departments), E003 (Charitable Orgs) | Tax exemptions |
| `additional_info` | 24 | AGR (Agricultural Certificate), LIC (Import License) | Additional info codes |
| `payment_method` | 32 | 10 (Cash/TC-BVI), 22 (Cheque-USD), BW (Bank Wire), CS (Cash) | Method of payment (Box 6a) |

### How Codes Are Resolved

The `WebFormDataMapper` uses `mapToReference()` to look up codes:

1. **Exact code match** – checks if the raw value IS a valid code (e.g., "US" matches country code "US")
2. **Label search** – checks if the raw value appears in the label (e.g., "Admiral Pride" matches carrier label "ADMIRAL PRIDE" → code "ADP")
3. **Hardcoded fallback** – for common mappings that rarely change (e.g., "tropical" → "TRP")
4. **Default** – returns a safe default (e.g., "US" for country, "PP" for port, "EA" for unit)

```php
// Example: resolving a carrier name to a CAPS code
$carrierRaw = "Admiral Pride";         // from shipment vessel_name
$code = $this->mapCarrier($carrierRaw);  // → "ADP" (found in reference data)
```

---

## Data Flow: Declaration → CAPS

### Step 1: Resolve Declaration Items

Items are gathered from the first available source:

1. `declaration.items` JSON field
2. `filledForms.data.tariff_groups` (from filled form templates)
3. `declarationItems` relation (`DeclarationFormItem` models)
4. `invoice.invoiceItems` (last resort fallback)

### Step 1b: Tariff Code Resolution

HS codes from classifications are resolved to valid **7-digit BVI tariff codes** that CAPS accepts. This is not a simple zero-pad — the `resolveCapsTariffCode()` method uses a multi-step strategy:

1. **HS version mappings** — codes that were split/restructured in HS2017/2022 are mapped first (e.g., `2009.80` → `2009801`, `12.07` → `1207901`)
2. **Exact 7-digit match** — if stripping dots produces 7 digits and the code exists in `customs_codes`, use it
3. **7-digit descendant search** — look for a valid 7-digit code under the same prefix, preferring "Other" catch-alls ending in 9
4. **Existing subheading check** — if the 6-digit subheading exists in the DB (even without 7-digit children), the padded form (+ trailing 0) is accepted by CAPS
5. **Broader heading search** — broaden to the 4-digit heading to find any 7-digit code
6. **"Other" subheading fallback** — find the `.90` catch-all subheading under the same heading
7. **Zero-pad** — last resort fallback

**BVI Tariff Code Patterns:**

| Scenario | Example | Resolution |
|----------|---------|------------|
| Already 7 digits in DB | `2008.002` | `2008002` (use as-is) |
| 6-digit subheading exists | `0801.20` | `0801200` (pad with 0) |
| Has 7-digit descendants | `1703.10` → `1703.102` | `1703102` (Edible) |
| Subheading doesn't exist | `1904.20` → no DB match | `1904900` (mapped to "Other" under 1904) |
| 4-digit heading only | `09.04` → `0904.209` exists | `0904209` (Other capsicum) |
| HS version split | `2009.80` → split in HS2017 | `2009801` (BVI sub-item 1) |

**Known HS Version Mappings** (in `$hsVersionMappings`):

| Old Code | CAPS Code | Reason |
|----------|-----------|--------|
| `2009.80` | `2009801` | Split into .81 (cranberry) / .89 (other) in HS2017 |
| `12.07` | `1207901` | Heading restructured; BVI uses sub-item 1 |
| `2102.20` | `2102201` | BVI tariff uses sub-item 1 instead of 0 |

### Step 1c: Group by HS Code (default: enabled)

Items that resolve to the same 7-digit tariff code are consolidated into a single declaration record. This is a **country-level setting** managed via the admin panel at `/admin/countries/1` (the `caps_group_items` checkbox). It can also be overridden with `CAPS_GROUP_ITEMS=false` in `.env`.

Grouping uses the **resolved** tariff code as the key (not the raw HS code), so items like `1904.20` and `1904.90` that both resolve to `1904900` will be merged.

| Field | Aggregation |
|-------|-------------|
| Quantity | Summed across all items in the group |
| FOB Value | Summed across all items in the group |
| Weight | Summed (falls back to quantity if no weight data) |
| Description | Unique descriptions joined with `;` (truncated to 250 chars if too long) |
| Country of Origin | Uses the first item's country |
| Tariff Code / CPC / Unit / Currency | Uses the resolved code from the first item |

**Example:** An invoice with 22 line items might produce 16 CAPS records after tariff resolution + grouping. Items like `1904.20` (granola) and `1904.90` (puffs) both resolve to `1904900` and merge into one record.

To disable grouping, uncheck "Group items by HS code" in the country admin panel, or set `CAPS_GROUP_ITEMS=false` in `.env`.

### Step 2: Map Header Data

The mapper extracts header-level data from the declaration, shipment, and contacts:

| CAPS Field | Input Name | Source |
|------------|------------|--------|
| Trader Reference | `head_TraderReference` | `declaration.reference_number` or B/L number |
| Supplier ID | `head_SupplierID` | Left **empty** — CAPS expects a registered trader code, not a reference number |
| Supplier Name | `head_SupplierName` | `shipperContact.company_name` |
| Supplier Street | `head_SupplierStreet` | `shipperContact.address_line_1` |
| Supplier City | `head_SupplierState` | `shipperContact.city` |
| Supplier Country | `head_SupplierCountry` | `shipperContact.country` → `mapCountry()` |
| Carrier ID | `head_CarrierID` | `shipment.carrier` or `shipment.vessel_name` → `mapCarrier()` |
| Carrier/Voyage No (Box 3a) | `head_CarrierNo` | `shipment.voyage_number` — **required for validation** |
| Port of Arrival | `head_PortOfArrival` | `shipment.port_of_arrival` → `mapPort()` |
| Arrival Date | `head_ArrDepDate` | `shipment.arrival_date` formatted as DD/MM/YYYY |
| Manifest No. (Box 4) | `head_ManifestNo` | `shipment.manifest_number`, falls back to B/L number — **required for validation** |
| Total Packages | `head_TotalNoOfPackages` | Sum of item quantities |
| Bill of Lading | `head_MasterBOL` | `shipment.bill_of_lading` or `shippingDocument.document_number` |
| City of Shipment | `head_CityDirShip` | `shipment.port_of_loading` or shipper city |
| Country (Direct) | `head_CountryDirShip` | Shipper country code |
| Country (Origin) | `head_CountryOrgShip` | Shipper country code |
| Payment Method (Box 6a) | `head_PaymentCode_line1` | Country setting `caps_default_payment_method`, default `22` (Cheque - USD) — **must be set via Lookup popup, not JS** |
| Total Freight | `head_TotalFreight` | `shipment.freight_total` (falls back to `freight_cost`) |
| Total Insurance | `head_TotalInsurance` | `shipment.insurance_total` (falls back to `insurance_cost`) |

### Step 3: Map Each Item

For each declaration item, the mapper produces:

| CAPS Field | Input Name Pattern | Source | Reference Type |
|------------|-------------------|--------|----------------|
| CPC | `rec{N}_CPC` | Default: `C400` | `cpc` |
| Tariff No. | `rec{N}_TariffNo` | `item.hs_code` → `resolveCapsTariffCode()` (7-digit BVI code) | — |
| Country of Origin | `rec{N}_CountryOfOrigin` | `item.country_of_origin` → `mapCountry()` | `country` |
| No. of Packages | `rec{N}_NoOfPackages` | `item.quantity` (integer) | — |
| Package Type | `rec{N}_TypeOfPackages` | `item.unit` → `mapPackageType()` | `unit` |
| Description | `rec{N}_Description` | `item.description` | — |
| Net Weight (Box 17a) | `rec{N}_Quantity1` | `item.weight`, falls back to quantity if empty (required) | — |
| Quantity | `rec{N}_Quantity2` | `item.quantity` (integer) | — |
| Units (Box 17b) | `rec{N}_UnitForQuantity` | Hardcoded `UNIT` — CAPS rejects "EA" | `unit` |
| FOB Value | `rec{N}_RecordValue` | `item.quantity × item.unit_price` | — |
| Currency | `rec{N}_CurrencyCode` | `item.currency` (default: USD) | `currency` |
| Freight Code | `rec{N}_ChargeCode_line1` | `FRT` | `charge_code` |
| Freight Amount | `rec{N}_ChargeAmount_line1` | Prorated from total freight | — |
| Insurance Code | `rec{N}_ChargeCode_line2` | `INS` | `charge_code` |
| Insurance Amount | `rec{N}_ChargeAmount_line2` | Prorated from total insurance | — |
| Tax Type 1 | `rec{N}_TaxType_line1` | `CUD` (Customs Duty) | `tax_type` |
| Tax Value 1 (CUD) | `rec{N}_TaxValue_line1` | CIF value (FOB + Freight + Insurance) | — |
| Tax Type 2 | `rec{N}_TaxType_line2` | `WHA` (Wharfage) | `tax_type` |
| Tax Value 2 (WHA) | `rec{N}_TaxValue_line2` | FOB value | — |

Where `{N}` is the 1-based record number (rec1, rec2, rec3...).

### Freight/Insurance Proration

When a declaration has multiple items, freight and insurance costs are distributed proportionally:

```
Item Freight = Total Freight × (Item FOB / Total FOB)
Item Insurance = Total Insurance × (Item FOB / Total FOB)
CIF Value = FOB + Freight + Insurance
```

If there are multiple items, the prorated checkboxes are automatically set.

---

## Browser Automation (Playwright)

The `caps-web-submitter.mjs` script drives a real Chrome browser to fill the CAPS form.

### How Fields Are Filled

Every field is targeted by its exact HTML `name` attribute using Playwright's `page.fill()`:

```javascript
// Header fields use "head_" prefix
await page.fill('input[name="head_CarrierID"]', 'ADP');
await page.fill('input[name="head_PortOfArrival"]', 'PP');

// Item fields use "rec{N}_" prefix
await page.fill('input[name="rec1_CPC"]', 'C400');
await page.fill('input[name="rec1_TariffNo"]', '190420');
await page.fill('input[name="rec1_RecordValue"]', '81.26');
```

For **readonly Lookup fields** (like Currency), the script temporarily removes the `readonly` attribute, fills the value, then restores it.

For **textarea fields** (like Description), the script checks both `input[name]` and `textarea[name]` selectors.

### Submission Flow

**Creating a new TD:**

```
1. Login          → Navigate to caps.gov.vg, fill credentials, submit
2. Create TD      → Click "New" on the TD list page
3. Fill Header    → Fill all head_* fields using page.fill()
                    Clear Supplier ID if empty (for editing existing TDs)
                    Set Payment Method via JS (readonly field + hidden key)
4. Fill Item 1    → Fill all rec1_* fields
5. Add Record     → Click "Add Record" (triggers page reload)
6. Fill Item 2    → Fill all rec2_* fields
7. ... repeat for all items ...
8. Save           → Click "Save" via page.evaluate() (bypasses Playwright nav wait)
9. Attach Files   → Click "Attach File", upload B/L and Invoice PDFs
10. Validate      → Click "Validate" to check all fields
11. Exit          → Click "Exit" to return to TD list
```

**Editing an existing TD** (when `td_number` is provided):

```
1. Login
2. Open TD        → Direct navigation to TDDataEntryServlet?method=tddataentry.RetrieveTD&bcdNumber=XXXXX
3. Fill Header    → Overwrite all head_* fields
4. Fill Items     → Overwrite existing rec{N}_* fields (no need to "Add Record" for existing records)
5. Delete Extras  → If editing with fewer items, mark extra records for deletion via JS:
                    Set rec{N}_delete = "true" for records beyond the new item count
6. Save           → Deleted records are removed by CAPS on save
7. Validate
8. Exit
```

### Handling CAPS Quirks

- **Page reloads**: CAPS reloads the entire page when adding records or saving. The script uses `Promise.all([page.waitForNavigation(), button.click()])` with dynamic timeouts that increase with more records.
- **Slow performance**: CAPS gets progressively slower with more items. Timeouts scale: `45s + (recordCount × 8s)`, up to 180s max. Opening an existing 16+ item TD can take 60–120 seconds.
- **Save button**: Uses `page.evaluate(() => btn.click())` to click the Save button instead of Playwright's default click — this avoids Playwright's internal navigation wait timeout on the 30s click action. The script then waits separately for `domcontentloaded` and checks for the form to be interactive.
- **Field confirmation**: After adding a record, the script waits for `rec{N}_CPC` to appear in the DOM before proceeding.
- **Number formats**: CAPS expects integers for quantity/package fields. Decimal values like "1.000" cause `NumberFormatException` errors — the mapper casts with `intval()`.
- **Date format**: Arrival dates must be in DD/MM/YYYY format.
- **Tariff codes**: Not a simple zero-pad. See "Tariff Code Resolution" section above. Codes must be exactly 7 digits and match the BVI tariff schedule. Some codes need BVI-specific sub-item digits; others need HS version mappings.
- **Payment Method (Lookup only)**: The `head_PaymentCode_line1` field is readonly and **cannot be set via JavaScript** — direct JS changes to the value do not persist on save (CAPS reverts to stale data like "CSH"). The script must click the "Lookup" link next to the field, wait for the popup window, and click the correct numeric code (e.g., `22`) in the popup. The popup's onclick handler properly sets the field in the parent window. Available codes include: `10` (Cash/TC - BVI), `11` (Cash/TC - USD), `22` (Cheque - USD), `BW` (Bank Wire), `CS` (Cash), `CQ` (Cheque), `TA` (Trader Account).
- **Supplier ID**: Must be left **empty** unless you have a valid CAPS-registered supplier code. Setting it to a B/L number or reference causes "SUPPLIER ID NOT FOUND" validation errors.
- **Deleting records**: When editing a TD with fewer items than it currently has, extra records are deleted by setting `rec{N}_delete = "true"` (hidden field) via JavaScript. CAPS removes them on save. Clicking the Delete buttons directly has visibility/timeout issues.
- **Attach File button**: Only appears on certain pages. When editing a TD that already has attachments, the button may not be present after save — the script skips attachment upload gracefully in that case.
- **Validation timeouts**: CAPS validation is slow for declarations with many items (16+ items can take 60–120 seconds). The script uses `Promise.all([page.waitForNavigation({ timeout }), validateBtn.click()])` to accommodate this.
- **Validation detection**: The script checks for CAPS-specific error phrases ("NOT COMPLETE", "NOT KNOWN", "NOT FOUND", "NOT ACTIVE") instead of just the generic word "error". It extracts structured error details including record numbers and SAD Box references.

---

## Important Constraints

### Tariff Codes
- **Must be exactly 7 digits** — CAPS rejects codes shorter or longer than 7 digits.
- **Digits only** — dots, dashes, and spaces are stripped before resolution.
- **Not a simple zero-pad** — the mapper uses `resolveCapsTariffCode()` which checks the `customs_codes` DB table, applies HS version mappings, and finds valid BVI-specific 7-digit codes. Simply appending zeros works for ~60% of codes but fails for others.
- **BVI sub-item pattern** — for subheadings without 7-digit codes in the DB that CAPS rejects with trailing "0", the correct 7th digit is often "1" (BVI sub-item 1). These are defined in `$hsVersionMappings`.
- **HS version differences** — our `customs_codes` table may have older HS2012 codes while CAPS uses HS2017/2022. Codes like `2009.80` (split into `.81`/`.89`) must be mapped explicitly.

### Header Fields
- **Supplier ID must be empty** — unless you have a valid CAPS-registered supplier trader code. Setting it to a B/L or reference number causes "SUPPLIER ID NOT FOUND" errors. The script explicitly clears this field when editing existing TDs.
- **Carrier/Voyage Number (Box 3a) is required** — filled from `shipment.voyage_number`. Validation fails with "FIELD NOT COMPLETE Rec 0, Box 3a" if empty.
- **Manifest Number (Box 4) is required** — filled from `shipment.manifest_number`, falls back to B/L number.
- **Payment Method (Box 6a) is required** — default `22` (Cheque - USD), configurable per country in admin (`caps_default_payment_method`). **Must be set via the Lookup popup**, not direct JavaScript — CAPS ignores JS-set values on this readonly field and reverts them on save. The script clicks the Lookup link, waits for the popup, and clicks the numeric code.

### Item Fields
- **Quantities must be integers** — CAPS rejects decimal values. The mapper uses `intval()` to cast.
- **Net Weight (Box 17a) is required** — falls back to quantity if no explicit weight.
- **Quantity Units (Box 17b) must be "UNIT"** — CAPS rejects "EA" (Each). Hardcoded in the mapper.
- **Currency is readonly** — the script removes `readonly`, sets the value, and restores it.
- **CPC defaults to C400** — "Release to Free Circulation" is the standard import procedure.
- **Tax Value for CUD must be the CIF value** — CAPS calculates duty as `CIF × rate`. If `TaxValue_line1` is 0.00, duty will be 0.00 regardless of the tariff rate. CIF = FOB + prorated Freight + prorated Insurance.
- **Tax Value for WHA must be the FOB value** — Wharfage is calculated on FOB at a fixed 2% rate.

### General
- **All Lookup codes must be valid** — CAPS validates codes on save. Invalid codes cause silent failures.
- **Freight/Insurance source fields** — the mapper prefers `shipment.freight_total` / `insurance_total` over the older `freight_cost` / `insurance_cost` fields.

---

## File Attachments

After the TD is saved, the script uploads supporting documents (B/L, invoices, etc.) to CAPS via an attachment popup. This happens as Step 9 in the submission flow, after all item records have been filled and the form has been saved.

### Where the Files Come From

The `WebFormSubmitterService::gatherAttachments()` method collects files from the declaration's related models:

| Document | Source | How It's Found |
|----------|--------|----------------|
| Bill of Lading / AWB | `shipment.shippingDocuments` | Filters for the **primary transport document** (`isPrimaryTransportDocument()`) that has a `file_path` |
| Invoice(s) | `declaration.getAllInvoices()` | Each invoice with a non-empty `source_file_path` |

Each attachment is resolved to an **absolute filesystem path** (`storage_path('app/' . $doc->file_path)`) and checked for existence before being included. The final JSON payload sent to the Playwright script looks like:

```json
{
  "attachments": [
    {
      "label": "Bill of Lading - shipping-doc.jpeg",
      "filePath": "C:/Users/.../storage/app/shipping-documents/55/b457168b-....jpeg",
      "type": "bill_of_lading"
    },
    {
      "label": "Invoice #1042",
      "filePath": "C:/Users/.../storage/app/invoices/80iudicYLd....pdf",
      "type": "invoice"
    }
  ]
}
```

### CAPS Attachment Popup

Clicking the **"Attach File (7)"** button on the TD form opens a popup window at:

```
/CAPSWeb/TDDataEntryServlet?method=tddataentry.AttachFile
```

The popup HTML contains:

```html
<form method="post" action="uploadFile" enctype="multipart/form-data" name="attachFile">
  <input type="hidden" name="functionality" value="attachFile.Upload">
  <input type="hidden" name="traderId" value="100184">
  <input type="hidden" name="tdNumber" value="003979606">

  <!-- Row for each uploaded file (shown after upload) -->
  <tr>
    <td>1.</td>
    <td>filename.pdf</td>
    <td><a onclick="deleteAtachment(1)">Delete</a></td>
  </tr>

  <!-- Empty file input for next upload -->
  <tr>
    <td>2.</td>
    <td><input type="file" name="uploadFile1"></td>
  </tr>

  <button type="button" onclick="...this.form.submit();">Upload</button>
  <button type="button" onclick="window.close();">Close Window</button>
</form>
```

Key details:
- The form posts as `multipart/form-data` to the `/uploadFile` endpoint
- The Upload button is `type="button"` (not submit) — its `onclick` sets a hidden field and calls `this.form.submit()`
- After each upload, the popup **reloads** showing the uploaded file in a numbered list plus a fresh empty `<input type="file">` for the next upload
- The hidden `traderId` and `tdNumber` fields tie the upload to the correct TD

### How the Playwright Script Uploads

All files are uploaded in a **single popup session** — the popup is opened once and reused for every file:

```
1. Scroll to bottom of TD form
2. Listen for popup event: context.waitForEvent('page')
3. Click "Attach File (7)" button on the main page
4. Wait for popup to load (domcontentloaded)

   For each attachment:
   5. Wait for input[type="file"] to appear in the popup
   6. Set the file: fileInput.setInputFiles(attachment.filePath)
   7. Wait for the Upload button: popup.waitForSelector('button:has-text("Upload")')
   8. Click Upload + wait for form navigation:
      Promise.all([
        popup.waitForNavigation({ waitUntil: 'domcontentloaded' }),
        uploadBtn.click()
      ])
   9. After reload, the uploaded file appears in the list
   10. Loop back to step 5 for the next file

11. Click "Close Window" button to close the popup
```

The `Promise.all([waitForNavigation, click])` pattern on step 8 is critical — the Upload button triggers `this.form.submit()`, which navigates the popup page. Without waiting for this navigation, subsequent file inputs would be looked up on a stale DOM.

### Error Handling

- If the attachment file doesn't exist on disk, it's skipped with a warning
- If the popup fails to open, the entire attachment step is skipped (form data is already saved)
- If the file input or Upload button can't be found after timeout, that individual file is skipped
- After each upload, the popup body text is checked for "error" to detect server-side failures
- Screenshots are taken after each upload for debugging

---

## CAPS Validation

After saving a TD with all header fields, items, and attachments, the script can trigger CAPS validation. This checks every field against CAPS's internal rules before a TD can be submitted for processing.

### Validation Flow

```
1. Click "Validate" button on the saved TD
2. CAPS processes all items (can take 60–120s for 20+ items)
3. Page reloads showing either:
   a) Green "Assessment Complete" status → validation passed
   b) Red error list → validation failed with specific Box references
4. Script parses the page for error messages
5. Returns validation_passed: true/false with any error details
```

### SAD Box Reference

CAPS validation errors reference **SAD (Single Administrative Document) Box numbers**. Here's a mapping of the boxes encountered:

| Box | CAPS Field | What It Means |
|-----|-----------|---------------|
| Box 1 | `head_SupplierID` | Supplier/Exporter trader code (must be valid or empty) |
| Box 3a | `head_CarrierNo` | Carrier voyage/flight number |
| Box 4 | `head_ManifestNo` | Manifest number |
| Box 6a | `head_PaymentCode_line1` | Method of payment |
| Box 13 | `rec{N}_TariffNo` | HS tariff classification code (7 digits) |
| Box 17a | `rec{N}_Quantity1` | Net weight of the goods |
| Box 17b | `rec{N}_UnitForQuantity` | Supplementary unit code |

### Validation Errors & Fixes Applied

These are the validation errors encountered during testing and their resolutions:

| Error | Cause | Fix |
|-------|-------|-----|
| "FIELD NOT COMPLETE - Rec 0, Box 3a" | `head_CarrierNo` was empty | Now filled from `shipment.voyage_number` |
| "FIELD NOT COMPLETE - Rec 0, Box 4" | `head_ManifestNo` was empty | Now filled from `shipment.manifest_number`, B/L fallback |
| "SUPPLIER ID NOT FOUND - Rec 0, Box 1" | `head_SupplierID` had a B/L number instead of CAPS trader code | Now left empty (cleared explicitly when editing) |
| "TRADER NOT ACTIVE - Rec 0" | Cascading error from invalid Supplier ID | Resolved by clearing Supplier ID |
| "PAYMENT METHOD NOT KNOWN - Rec 0, Box 6a" | `head_PaymentCode_line1` was "CSH" (stale) or set via JS (doesn't persist) | Must use Lookup popup to set numeric code (`22` = Cheque - USD). Direct JS value changes are ignored by CAPS on save. Now a country-level admin setting (`caps_default_payment_method`). |
| "FIELD NOT COMPLETE - Rec N, Box 17a" | `rec{N}_Quantity1` (net weight) was empty | Defaults to item quantity when no explicit weight available |
| "QUANTITY UNITS NOT KNOWN - Rec N, Box 17b" | `rec{N}_UnitForQuantity` was "EA" | Changed to hardcoded "UNIT" — CAPS does not recognize "EA" |
| "TARIFF NO. NOT KNOWN - Rec N, Box 13" | HS code resolved to invalid 7-digit code | Full tariff resolution: DB lookup, HS version mapping, BVI sub-item handling |

**Tariff resolution examples from TD 004463956:**

| Original HS | Old (Failed) | New (Passed) | Method |
|-------------|-------------|-------------|--------|
| `1904.20` | `1904200` | `1904900` | Subheading not in DB → "Other" (.90) under same heading |
| `2009.80` | `2009800` | `2009801` | HS version mapping (BVI sub-item 1) |
| `12.07` | `1207000` | `1207901` | Heading-level → HS version mapping |
| `2102.20` | `2102200` | `2102201` | HS version mapping (BVI sub-item 1) |
| `09.04` | `0904000` | `0904209` | 7-digit descendant found (Other capsicum) |
| `2106.90` | `2106900` | `2106009` | 7-digit descendant found under heading (Other) |
| `1703.10` | `1703100` | `1703102` | 7-digit descendant found (Edible molasses) |
| `21.03` | `2103000` | `2103909` | 7-digit descendant found (Other sauces) |

### Validation Timeout Handling

CAPS validation is slow, especially for declarations with many items. The script handles this with:

- Extended navigation timeout: `180000ms` (3 minutes)
- `Promise.all([page.waitForNavigation({ timeout }), validateBtn.click()])` pattern
- Even if the navigation wait times out, CAPS may still complete validation — the script checks the page for error/success indicators after the timeout
- Dynamic timeout scaling: `45000 + (itemCount × 8000)` ms, capped at 180s

---

## Running a Submission

### From the Application

Submissions are triggered through the web submission interface. The `WebFormSubmitterService` orchestrates the process:

1. Calls `WebFormDataMapper::buildCapsSubmissionData()` to generate the JSON payload
2. Writes the JSON to a temporary file
3. Spawns the Playwright script: `node playwright/caps-web-submitter.mjs --input-file=path/to/input.json`
4. Monitors the process and captures the JSON result

### Manual Test

```bash
# Generate input JSON via tinker
php artisan tinker
> $d = DeclarationForm::find(60);
> $target = WebFormTarget::where('name', 'like', '%CAPS%')->first();
> $mapper = new WebFormDataMapper();
> $data = $mapper->buildCapsSubmissionData($d, $target, false);
> file_put_contents(storage_path('app/test-input.json'), json_encode([
>     'action' => 'save',
>     'credentials' => $data['credentials'],
>     'loginUrl' => $data['loginUrl'],
>     'headerData' => $data['headerData'],
>     'items' => $data['items'],
>     'headless' => false,
> ], JSON_PRETTY_PRINT));

# Run the Playwright script
node playwright/caps-web-submitter.mjs --input-file="storage/app/test-input.json"
```

### Actions

| Action | Behavior |
|--------|----------|
| `test` | Login only — verifies credentials work |
| `save` | Create/edit TD, fill all fields, save (no validation/submit) |
| `validate` | Save + run CAPS validation |
| `submit` | Save + validate + submit for processing |

### Editing Existing TDs

To edit an existing TD instead of creating a new one, include `td_number` in the JSON payload:

```json
{
  "action": "validate",
  "td_number": "004463956",
  "headerData": { ... },
  "items": [ ... ]
}
```

When `td_number` is provided:
- The script navigates directly to the TD's data entry URL
- Existing record fields are overwritten in place (no "Add Record" clicks needed)
- If the new item count is less than the existing record count, extra records are marked for deletion
- Attachments already on the TD are preserved (the Attach File button may not appear)
