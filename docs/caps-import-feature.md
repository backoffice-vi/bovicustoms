# CAPS Import Feature

## Overview

The CAPS Import feature connects to the **BVI CAPS portal** (https://caps.gov.vg) to download historical Trade Declarations (TDs) and their supporting documents (invoices, B/L). This data is imported into the Legacy Clearances system and used as **classification precedents** — the AI classifier references approved TDs to determine the correct HS codes for new invoice items.

## Why This Matters

The core strength of the system is linking **invoice line items** to **approved Trade Declarations**. When a TD has been approved by BVI Customs, the HS codes on that declaration are treated as authoritative. During classification, the AI uses these as reference precedents, boosting accuracy for similar items in future invoices.

## Architecture

### Data Flow

```
CAPS Portal → Playwright Scraper → Local Storage → Legacy Clearances DB → AI Classifier
```

1. **Fetch TD List** — Playwright logs into CAPS, scrapes the list of all TDs
2. **Download TDs** — For each TD: scrapes structured data (header, items, taxes), takes screenshots, downloads attachment PDFs (invoices, B/L)
3. **Import to Legacy** — Creates `Shipment`, `Invoice`, `DeclarationForm`, and `DeclarationFormItem` records with `source_type = 'legacy'`
4. **Process Invoices** — Extracts line items from invoice PDFs using Claude AI, then matches them to declaration items via `InvoiceDeclarationMatcher`
5. **Classification** — `ItemClassifier::findApprovedReferencePrecedents()` queries legacy `DeclarationFormItem` records, prioritizes them, and injects them into the AI prompt

### Key Components

| Component | Path | Purpose |
|-----------|------|---------|
| Playwright Scraper | `playwright/caps-td-downloader.mjs` | Browser automation for CAPS portal |
| Import Service | `app/Services/CapsImportService.php` | Orchestrates download, import, and invoice processing |
| Tracking Model | `app/Models/CapsImport.php` | Tracks status of each TD through the pipeline |
| Controller | `app/Http/Controllers/CapsImportController.php` | UI endpoints for the import workflow |
| UI View | `resources/views/caps-import/index.blade.php` | User-facing import dashboard |
| Download Jobs | `app/Jobs/ProcessCapsDownload.php` | Background TD download |
| Invoice Jobs | `app/Jobs/ProcessCapsInvoices.php` | Background invoice PDF processing |
| Invoice Extractor | `app/Services/InvoiceDocumentExtractor.php` | AI-based extraction of line items from PDFs |
| Declaration Matcher | `app/Services/InvoiceDeclarationMatcher.php` | Matches invoice items to declaration items |
| Item Classifier | `app/Services/ItemClassifier.php` | Uses legacy data as classification precedents |

### Database Tables

- **`caps_imports`** — Tracks each TD through the import pipeline (pending → downloading → downloaded → importing → imported → processing_invoices → completed)
- **`organization_submission_credentials`** — Stores encrypted per-organization CAPS credentials (type: `caps`)
- **`shipments`** — Legacy shipment records created from CAPS data
- **`invoices` / `invoice_items`** — Invoice records and extracted line items
- **`declaration_forms` / `declaration_form_items`** — Approved declarations and their items with HS codes
- **`invoice_declaration_matches`** — Links between invoice line items and declaration items

## Per-Customer Credentials

CAPS credentials are stored **per-organization**, encrypted in the `organization_submission_credentials` table using the existing credential system (same as FTP and Web submission credentials).

- Each customer enters their own CAPS email and password from the CAPS Import UI
- Credentials are encrypted with Laravel's `Crypt` facade
- The service resolves credentials in this order:
  1. Organization's stored CAPS credential (per-org, per-country)
  2. Fallback to `.env` variables (`CAPS_USERNAME`, `CAPS_PASSWORD`) for admin users
- Credentials are passed to the Playwright script via `--username` and `--password` CLI flags

## Country Restriction

CAPS Import is **BVI-only**. The controller resolves the BVI country record (`code = VGB`) and all operations are scoped to it. If the BVI country is not configured in the system, the UI displays a warning and disables all functionality.

## UI Workflow

The CAPS Import page (`/caps-import`) provides a step-by-step workflow:

1. **Enter Credentials** — Username (email) and password for the CAPS portal
2. **Fetch TD List** — Connects to CAPS to discover available TDs, creates tracking records
3. **Download All Pending** — Queues Playwright downloads for each TD
4. **Import All Downloaded** — Creates legacy clearance records from scraped data
5. **Process All Invoices** — Extracts line items from invoice PDFs using AI

Each TD shows its current status, attachment count, item count, and has individual action buttons for retry/reprocessing.

### Status Dashboard

The page includes a status summary showing counts for: Total, Pending, Downloaded, Imported, Completed, Failed, and Processing TDs.

### Retry Logic

- Failed downloads can be retried (up to configurable max retries)
- Individual TDs can be re-downloaded or re-processed independently
- Configurable timeout and max retries via `config/services.php`

## Search & Verification

The Legacy Clearances page (`/legacy-clearances`) includes a search feature:

- **Word-based search** with basic stemming (handles singular/plural)
- **Two result sections**: Approved Declaration Items (with HS codes, duty rates, total due, relevance score) and Invoice Line Items (with matched HS codes and confidence)
- Results sorted by relevance score
- Pagination for browsing all imported clearances

## Configuration

### Environment Variables (`.env`)

```
CAPS_URL=https://caps.gov.vg
CAPS_USERNAME=fallback@email.com    # Fallback only; prefer per-org credentials
CAPS_PASSWORD=fallback_password     # Fallback only; prefer per-org credentials
```

### Service Config (`config/services.php`)

```php
'caps' => [
    'url' => env('CAPS_URL', 'https://caps.gov.vg'),
    'username' => env('CAPS_USERNAME'),
    'password' => env('CAPS_PASSWORD'),
    'download_timeout' => env('CAPS_DOWNLOAD_TIMEOUT', 60),
    'max_retries' => env('CAPS_MAX_RETRIES', 3),
],
```

## Playwright Script

The scraper (`playwright/caps-td-downloader.mjs`) uses Chromium via Playwright to:

- Log into CAPS with provided credentials
- Navigate TD list pages with pagination
- Scrape structured data from each TD (header fields, line items with tariff numbers, taxes)
- Take full-page screenshots of declarations
- Download attachment files by parsing `onclick` handlers for download URLs
- Save everything to `storage/app/caps-downloads/{td_number}/`

### Usage

```bash
# Download all TDs
node playwright/caps-td-downloader.mjs --all

# Download a specific TD
node playwright/caps-td-downloader.mjs --td=002841062

# With explicit credentials
node playwright/caps-td-downloader.mjs --all --username=user@email.com --password=secret

# Non-headless mode for debugging
node playwright/caps-td-downloader.mjs --td=002841062 --headless=false
```

## How Imported Data Improves Classifications

The `ItemClassifier` service (`findApprovedReferencePrecedents`) works as follows:

1. Queries `DeclarationFormItem` records where `source_type = 'legacy'`
2. Matches items by description similarity to the current item being classified
3. Boosts relevance scores for legacy items (they carry more weight than other sources)
4. Injects matched precedents into the Claude AI prompt as authoritative classification references
5. The AI uses these approved HS codes as strong signals when determining the code for new items

More granular invoice line items (from processed PDFs) provide better matching precision than the coarser TD line item descriptions alone.
