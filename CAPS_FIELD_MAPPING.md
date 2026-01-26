# CAPS Field Mapping Guide

This document maps BVI Customs Application fields to the CAPS (Customs Automated Processing System) Trade Declaration form fields.

## Overview

**CAPS URL:** `https://caps.gov.vg/CAPSWeb/`
**TD Data Entry URL:** `https://caps.gov.vg/CAPSWeb/TDDataEntryServlet?method=tddataentry.RetrieveTD&bcdNumber={TD_NUMBER}&isWebTrader=Y`

---

## Header Section Mapping

### Section 1: SUPPLIER (Exporter)

| CAPS Field | CAPS Input Name | Our System Field | Source Model |
|------------|-----------------|------------------|--------------|
| Supplier ID | `SupplierID` | - | (lookup in CAPS) |
| Name | `SupplierName` | `trade_contact.company_name` | TradeContact (supplier) |
| Street | `SupplierStreet` | `trade_contact.address_line_1` | TradeContact |
| City, State/Prov | `SupplierCityState` | `trade_contact.city`, `trade_contact.state` | TradeContact |
| ZIP/Post Code | `SupplierZip` | `trade_contact.postal_code` | TradeContact |
| Country | `SupplierCountry` | `trade_contact.country_code` | TradeContact |

### Section 2: IMPORTER

| CAPS Field | CAPS Input Name | Our System Field | Source Model |
|------------|-----------------|------------------|--------------|
| Importer ID | `ImporterID` | `organization.caps_importer_id` | Organization |
| Name | `ImporterName` | `organization.name` | Organization |
| Address Line 1 | `ImporterAddress1` | `organization.address_line_1` | Organization |
| Address Line 2 | `ImporterAddress2` | `organization.address_line_2` | Organization |
| Post Code | `ImporterPostCode` | `organization.postal_code` | Organization |

### Section 3: TRANSPORT DETAILS

| CAPS Field | CAPS Input Name | Our System Field | Source Model |
|------------|-----------------|------------------|--------------|
| Carrier ID / No. | `CarrierID` / `CarrierNo` | `shipment.carrier_name` | Shipment |
| Port of Arrival | `PortOfArrival` | `shipment.arrival_port` | Shipment |
| Arrival Date | `ArrivalDate` | `shipment.arrival_date` | Shipment |

**Port Codes:**
- `PP` = Port Purcell (Road Town)
- `WE` = West End

### Section 4: MANIFEST

| CAPS Field | CAPS Input Name | Our System Field | Source Model |
|------------|-----------------|------------------|--------------|
| Manifest No. | `ManifestNo` | - | (from shipping line) |
| No. of Packages | `NoOfPackages` | `shipment.total_packages` | Shipment |
| Bill of Lading / AWB | `BillOfLading` | `shipment.bill_of_lading_number` | Shipment |
| Container ID | `ContainerID` | `shipment.container_number` | Shipment |

### Section 5: SHIPMENT ORIGIN

| CAPS Field | CAPS Input Name | Our System Field | Source Model |
|------------|-----------------|------------------|--------------|
| City of Direct Shipment | `CityOfDirectShipment` | `shipment.origin_city` | Shipment |
| Country of Direct Shipment | `CountryOfDirectShipment` | `shipment.origin_country_code` | Shipment |
| Country of Original Shipment | `CountryOfOriginalShipment` | `invoice.country_of_origin` | Invoice |

### Section 7-10: TOTALS

| CAPS Field | CAPS Input Name | Our System Field | Calculation |
|------------|-----------------|------------------|-------------|
| Total No. of Records | `TotalNoOfRecords` | `COUNT(invoice_items)` | Count of line items |
| Total Freight | `TotalFreight` | `shipment.freight_total` | Shipment |
| Prorated? | `FreightProrated` | `true` | Always check |
| Total Insurance | `TotalInsurance` | `shipment.insurance_total` | Shipment |
| Prorated? | `InsuranceProrated` | `true` | Always check |
| Total FOB | - | `SUM(invoice_items.total)` | Calculated |
| Total Payable | - | `SUM(duties + wharfage)` | Calculated |

---

## Line Item (Record) Section Mapping

Each invoice item creates one CAPS record. Fields are prefixed with `rec{N}_` where N is the record number.

### Section 12-16: PRODUCT DETAILS

| CAPS Field | CAPS Input Name | Our System Field | Source Model |
|------------|-----------------|------------------|--------------|
| CPC | `rec{N}_CPC` | `'C400'` | Constant for standard imports |
| Tariff No. | `rec{N}_TariffNo` | `invoice_item.customs_code` (no dots) | InvoiceItem |
| Country of Origin | `rec{N}_CountryOfOrigin` | `'US'` or from invoice | Invoice |
| No. of Packages | `rec{N}_NoOfPackages` | `invoice_item.quantity` | InvoiceItem |
| Type of Packages | `rec{N}_TypeOfPackages` | `'CTN'` | Default |
| Description | `rec{N}_Description` | `invoice_item.description` | InvoiceItem |

**CPC Codes:**
- `C400` = Import for Home Consumption (standard)
- `C410` = Import with duty exemption

### Section 17-19: VALUES

| CAPS Field | CAPS Input Name | Our System Field | Calculation |
|------------|-----------------|------------------|-------------|
| Net Weight (LB) | `rec{N}_NetWeight` | `invoice_item.weight` | InvoiceItem |
| Quantity | `rec{N}_Quantity2` | `invoice_item.quantity` | InvoiceItem |
| Units | `rec{N}_UnitForQuantity` | - | From lookup |
| F.O.B. Value | `rec{N}_RecordValue` | `invoice_item.unit_price * invoice_item.quantity` | Calculated |
| Currency | `rec{N}_CurrencyCode` | `'USD'` | Constant |
| Exchange Rate | `rec{N}_ExchangeRate` | `1.00` | USD = BVI Dollar |
| BVI Value | `rec{N}_BVIValue` | Same as FOB | Auto-calculated |

### Section 20: CHARGES / DEDUCTIONS

| CAPS Field | CAPS Input Name | Our System Field | Calculation |
|------------|-----------------|------------------|-------------|
| Charge Code 1 | `rec{N}_ChargeCode_line1` | `'FRT'` | Freight |
| Charge Amount 1 | `rec{N}_ChargeAmount_line1` | Prorated freight | `(item_fob / total_fob) * total_freight` |
| Charge Code 2 | `rec{N}_ChargeCode_line2` | `'INS'` | Insurance |
| Charge Amount 2 | `rec{N}_ChargeAmount_line2` | Prorated insurance | `(item_fob / total_fob) * total_insurance` |

**Charge Codes:**
- `FRT` = Freight Charges
- `INS` = Insurance Charges
- `EXE` = Exemption Amount
- `HIR` = Hire Costs
- `REP` = Repair Costs

### Section 21: CIF VALUE

| CAPS Field | CAPS Input Name | Calculation |
|------------|-----------------|-------------|
| C.I.F. Value | `rec{N}_CIFValue` | `FOB + Freight + Insurance` |

### Section 22: TAX TYPES

| CAPS Field | CAPS Input Name | Our System Field | Calculation |
|------------|-----------------|------------------|-------------|
| Tax Type 1 | `rec{N}_TaxType_line1` | `'CUD'` | Customs Duty |
| Exempt Ind. 1 | `rec{N}_TaxId_line1` | - | Leave blank unless exempt |
| Value for Tax 1 | `rec{N}_TaxValue_line1` | CIF Value | `FOB + Freight + Insurance` |
| Tax Rate 1 | `rec{N}_TaxRate_line1` | `invoice_item.duty_rate` | InvoiceItem |
| Tax Amount 1 | `rec{N}_TaxAmount_line1` | Customs Duty | `CIF * duty_rate / 100` |
| Tax Type 2 | `rec{N}_TaxType_line2` | `'WHA'` | Wharfage |
| Value for Tax 2 | `rec{N}_TaxValue_line2` | FOB Value | Item FOB |
| Tax Rate 2 | `rec{N}_TaxRate_line2` | `2.00` | 2% Wharfage |
| Tax Amount 2 | `rec{N}_TaxAmount_line2` | Wharfage | `FOB * 2 / 100` |

**Tax Type Codes:**
- `CUD` = Customs Duty (on CIF value)
- `WHA` = Wharfage (on FOB value, 2%)
- `EXC` = Excise Tax
- `SUR` = Surcharge
- `ENV` = Environmental Levy

---

## Calculation Formulas

### Per-Item Calculations

```javascript
// FOB (Free on Board) - Item value
item_fob = invoice_item.unit_price * invoice_item.quantity

// Prorated Freight
item_freight = (item_fob / total_fob) * shipment.freight_total

// Prorated Insurance  
item_insurance = (item_fob / total_fob) * shipment.insurance_total

// CIF (Cost, Insurance, Freight)
item_cif = item_fob + item_freight + item_insurance

// Customs Duty (CUD) - Based on CIF
customs_duty = item_cif * (invoice_item.duty_rate / 100)

// Wharfage (WHA) - Based on FOB at 2%
wharfage = item_fob * 0.02

// Total Tax for Item
item_total_tax = customs_duty + wharfage
```

### Shipment Totals

```javascript
total_fob = SUM(all item_fob)
total_cif = total_fob + shipment.freight_total + shipment.insurance_total
total_duty = SUM(all customs_duty)
total_wharfage = SUM(all wharfage)
total_payable = total_duty + total_wharfage
```

---

## Data Export Format (JSON)

```json
{
  "td_number": "004369344",
  "trader_reference": "2601SJU1053",
  "header": {
    "supplier": {
      "name": "KEHE DISTRIBUTORS-TREE OF LIFE",
      "street": "San Juan",
      "city_state": "San Juan, PR",
      "country": "US"
    },
    "importer": {
      "id": "100184",
      "name": "Nature's Way Ltd",
      "address1": "Road Town",
      "address2": "Road Town, Tortola"
    },
    "transport": {
      "carrier": "TOR / TORTOLA PR",
      "port_of_arrival": "PP",
      "arrival_date": "23/01/2026"
    },
    "manifest": {
      "no_of_packages": 286,
      "bill_of_lading": "2601SJU1053"
    },
    "shipment_origin": {
      "city": "SAN JUAN",
      "country_direct": "US",
      "country_original": "US"
    },
    "totals": {
      "freight": 125.00,
      "freight_prorated": true,
      "insurance": 15.59,
      "insurance_prorated": true
    }
  },
  "items": [
    {
      "record_number": 1,
      "cpc": "C400",
      "tariff_no": "1905",
      "country_of_origin": "US",
      "no_of_packages": 12,
      "type_of_packages": "CTN",
      "description": "BARBARAS CHEESE PUFF ORIGINAL 7 OZ",
      "quantity": 12.00,
      "fob_value": 37.08,
      "currency": "USD",
      "charges": {
        "freight": 2.97,
        "insurance": 0.37
      },
      "cif_value": 40.42,
      "taxes": {
        "cud": {
          "value": 40.42,
          "rate": 20.00,
          "amount": 8.08
        },
        "wha": {
          "value": 37.08,
          "rate": 2.00,
          "amount": 0.74
        }
      },
      "total_due": 8.82
    }
    // ... more items
  ],
  "summary": {
    "total_records": 34,
    "total_fob": 1558.82,
    "total_freight": 125.00,
    "total_insurance": 15.59,
    "total_cif": 1699.41,
    "total_duty": 145.23,
    "total_wharfage": 31.18,
    "total_payable": 176.41
  }
}
```

---

## Implementation Recommendations

### Option 1: CAPS Worksheet View (Recommended)

Create a new view in your app that displays all declaration data formatted exactly like the CAPS form:

```php
// Route
Route::get('/declarations/{declaration}/caps-worksheet', [DeclarationController::class, 'capsWorksheet']);

// View: resources/views/declarations/caps-worksheet.blade.php
// Display data in CAPS form layout for easy manual entry
```

### Option 2: API Endpoint for Automation

```php
// Route
Route::get('/api/declarations/{declaration}/caps-export', [DeclarationController::class, 'capsExport']);

// Returns JSON in the format above for headless browser scripts
```

### Option 3: Headless Browser Script

Use the JSON export with a Playwright script:

```javascript
const { chromium } = require('playwright');

async function fillCAPS(declarationData) {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  
  // Login
  await page.goto('https://caps.gov.vg/CAPSWeb/TraderLogin.jsp');
  await page.fill('input[type="text"]', username);
  await page.fill('input[type="password"]', password);
  await page.click('button:has-text("Login")');
  
  // Navigate to TD
  await page.goto(`https://caps.gov.vg/CAPSWeb/TDDataEntryServlet?...`);
  
  // Fill each record
  for (const item of declarationData.items) {
    await addRecord(page, item);
  }
  
  await browser.close();
}
```

---

## Database Schema Additions (Suggested)

To better support CAPS integration, consider adding:

```php
// Migration: add_caps_fields_to_organizations
Schema::table('organizations', function (Blueprint $table) {
    $table->string('caps_importer_id')->nullable();
    $table->string('caps_username')->nullable();
    $table->string('caps_password_encrypted')->nullable();
});

// Migration: add_caps_fields_to_trade_contacts
Schema::table('trade_contacts', function (Blueprint $table) {
    $table->string('caps_supplier_id')->nullable();
});

// Migration: add_caps_fields_to_declaration_forms
Schema::table('declaration_forms', function (Blueprint $table) {
    $table->string('caps_td_number')->nullable();
    $table->enum('caps_status', ['draft', 'submitted', 'approved', 'rejected'])->default('draft');
    $table->timestamp('caps_submitted_at')->nullable();
});
```

---

## File Upload Mapping

| CAPS Attachment Type | Our System File |
|---------------------|-----------------|
| Commercial Invoice | `invoice.document_path` |
| Bill of Lading | `shipping_document.file_path` (type: bill_of_lading) |
| Packing List | `shipping_document.file_path` (type: packing_list) |
| Insurance Certificate | `shipping_document.file_path` (type: insurance) |

---

## Quick Reference Card

For agents doing manual entry:

| Step | CAPS Field | Value From |
|------|------------|------------|
| 1 | CPC | Always `C400` |
| 2 | Tariff No | HS Code (remove dots) |
| 3 | Country of Origin | `US` |
| 4 | Packages | Quantity + `CTN` |
| 5 | Description | Product name |
| 6 | Quantity | Item quantity |
| 7 | FOB Value | Unit price × Quantity |
| 8 | Currency | `USD` (use lookup) |
| 9 | FRT Charge | (FOB ÷ Total FOB) × Freight |
| 10 | INS Charge | (FOB ÷ Total FOB) × Insurance |
| 11 | CUD Tax | CIF × Duty Rate% |
| 12 | WHA Tax | FOB × 2% |
| 13 | Save | Click "Save (1)" |
| 14 | Add Record | Click "Add Record (5)" for next item |
