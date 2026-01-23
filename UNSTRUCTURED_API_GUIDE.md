# Unstructured API Integration Guide

This document describes the Unstructured API integration for document processing in the BVI Customs application.

## Overview

The Unstructured API is a powerful document processing service that extracts structured data from various document formats including PDFs, images, Word documents, and more. It provides superior extraction capabilities compared to traditional PDF parsers, including:

- **OCR Support**: Extracts text from scanned documents and images
- **Large File Handling**: Processes large documents without memory issues
- **Structured Output**: Returns categorized elements (titles, paragraphs, tables, lists)
- **Page-Level Metadata**: Tracks which page each element came from

## API Endpoint

| Environment | URL |
|-------------|-----|
| Production | `http://34.48.24.135:8888` |

### Health Check
```
GET /healthcheck
```
Returns: `{"healthcheck":"HEALTHCHECK STATUS: EVERYTHING OK!"}`

### Document Processing
```
POST /general/v0/general
Content-Type: multipart/form-data
```

## Configuration

The API is configured in `config/services.php`:

```php
'unstructured' => [
    'url' => env('UNSTRUCTURED_API_URL', 'http://34.48.24.135:8888'),
    'timeout' => env('UNSTRUCTURED_API_TIMEOUT', 300),
],
```

Environment variables (optional):
```env
UNSTRUCTURED_API_URL=http://34.48.24.135:8888
UNSTRUCTURED_API_TIMEOUT=300
```

## Usage

### Basic Usage with UnstructuredApiClient

```php
use App\Services\UnstructuredApiClient;

$client = app(UnstructuredApiClient::class);

// Check if API is available
if ($client->healthCheck()) {
    // Extract from uploaded file
    $elements = $client->extractFromFile($uploadedFile);
    
    // Convert to plain text
    $text = $client->elementsToText($elements);
}
```

### Extract from File Path

```php
$client = new UnstructuredApiClient();

// From file path
$elements = $client->extractFromFile('/path/to/document.pdf');

// With options
$elements = $client->extractFromFile($file, [
    'strategy' => 'hi_res',      // 'auto', 'fast', 'hi_res', 'ocr_only'
    'coordinates' => true,        // Include element coordinates
    'include_page_breaks' => true
]);
```

### Extract from UploadedFile

```php
public function processUpload(Request $request)
{
    $file = $request->file('document');
    
    $client = app(UnstructuredApiClient::class);
    $elements = $client->extractFromFile($file);
    
    // Get plain text
    $text = $client->elementsToText($elements);
    
    // Or get tables only
    $tables = $client->extractTables($elements);
    
    // Or group by page
    $pages = $client->groupByPage($elements);
}
```

### Convenience Method

```php
// One-liner to get text
$text = $client->extractText($file, 'auto');
```

## Processing Strategies

| Strategy | Description | Use Case |
|----------|-------------|----------|
| `auto` | Automatically selects best strategy | General use |
| `fast` | Quick extraction, basic parsing | Large documents, speed priority |
| `hi_res` | High-resolution parsing with layout analysis | Complex layouts, forms |
| `ocr_only` | Forces OCR on all pages | Scanned documents, images |

## Response Format

The API returns an array of elements, each containing:

```json
{
    "type": "NarrativeText",
    "text": "The content of the element...",
    "metadata": {
        "page_number": 1,
        "filename": "document.pdf",
        "filetype": "application/pdf",
        "coordinates": { ... }
    }
}
```

### Element Types

| Type | Description |
|------|-------------|
| `Title` | Headers and titles |
| `NarrativeText` | Regular paragraph text |
| `ListItem` | Bulleted or numbered list items |
| `Table` | Table content |
| `Header` | Page headers |
| `Footer` | Page footers |
| `UncategorizedText` | Text that doesn't fit other categories |

## Real-World Performance Results

### Test Document: Customs Management and Duties Act 2010

A 15 MB PDF with 672 pages was processed with the following results:

| Metric | Result |
|--------|--------|
| **File Size** | 15,042,098 bytes (15 MB) |
| **Pages** | 672 |
| **Processing Time** | 63 seconds |
| **Elements Extracted** | 20,370 |
| **Output Text Size** | 971 KB |
| **Word Count** | ~128,443 |

### Element Type Distribution

| Element Type | Count | Percentage |
|--------------|-------|------------|
| UncategorizedText | 8,276 | 40.6% |
| Title | 7,135 | 35.0% |
| NarrativeText | 4,264 | 20.9% |
| ListItem | 672 | 3.3% |
| Header | 18 | 0.1% |
| Footer | 5 | 0.0% |

### Structure Captured

All 13 parts of the Customs Act were successfully identified:

- PART I: ADMINISTRATION (Page 16)
- PART II: CUSTOMS CONTROLLED AREAS (Page 20)
- PART III: IMPORTATION (Page 27)
- PART IV: EXPORTATION (Page 40)
- PART V: WAREHOUSING (Page 47)
- PART VI: DUTIES, DRAWBACKS, PROHIBITIONS AND RESTRICTIONS (Page 57)
- PART VII: POWERS (Page 74)
- PART VIII: OFFENCES (Page 84)
- PART IX: LEGAL PROCEEDINGS (Page 92)
- PART X: FORFEITURE (Page 97)
- PART XI: SALE OF GOODS (Page 102)
- PART XII: REGULATIONS (Page 105)
- PART XIII: MISCELLANEOUS (Page 106)

### Sample Extracted Content

```text
--- Page 17 ---
Any act or thing required or authorised by a customs enactment to be done by 
the Commissioner may be done by an officer authorised generally or specifically 
in that behalf, in writing or otherwise, by the Commissioner, except that where, 
the post of Commissioner is vacant, any authorisation given by a previous 
Commissioner which has not been revoked shall continue in force until revoked 
by a person subsequently appointed as Commissioner.
```

## Integration with Existing Services

### DocumentTextExtractor

The `DocumentTextExtractor` service automatically uses the Unstructured API as the primary method for PDF extraction, with fallback to the local PdfParser:

```php
// In DocumentTextExtractor.php
protected function extractFromPdf(string $path): string
{
    // Try Unstructured API first (handles OCR, large files better)
    if ($this->unstructuredApi) {
        try {
            $text = $this->unstructuredApi->extractText($path, 'auto');
            if (!empty(trim($text))) {
                return $text;
            }
        } catch (\Throwable $e) {
            // Falls back to PdfParser
        }
    }
    
    // Fallback to local PdfParser
    // ...
}
```

### InvoiceDocumentExtractor

Large PDFs (>2MB) and scanned PDFs are automatically routed through the Unstructured API:

```php
// For large PDFs
if ($fileSize > 2_000_000 && $this->unstructuredApi) {
    $text = $this->unstructuredApi->extractText($file, 'hi_res');
}

// For scanned PDFs (when standard extraction fails)
if (mb_strlen($text) < 50 && $this->unstructuredApi) {
    $text = $this->unstructuredApi->extractText($file, 'ocr_only');
}
```

## Error Handling

```php
try {
    $elements = $client->extractFromFile($file);
} catch (\Exception $e) {
    // API error - log and handle gracefully
    Log::error('Unstructured API error', [
        'error' => $e->getMessage(),
        'file' => $file->getClientOriginalName()
    ]);
    
    // Fall back to alternative extraction method
}
```

## Best Practices

1. **Check API Health**: Before processing critical documents, verify the API is available:
   ```php
   if (!$client->healthCheck()) {
       // Use fallback method
   }
   ```

2. **Choose Appropriate Strategy**:
   - Use `fast` for large documents when speed matters
   - Use `hi_res` for complex layouts or forms
   - Use `ocr_only` for scanned documents

3. **Handle Timeouts**: Large documents may take several minutes. Configure appropriate timeouts:
   ```php
   // In .env
   UNSTRUCTURED_API_TIMEOUT=600  // 10 minutes for very large docs
   ```

4. **Process Results**: Use the helper methods to work with extracted data:
   ```php
   $text = $client->elementsToText($elements);   // Plain text
   $tables = $client->extractTables($elements);   // Tables only
   $pages = $client->groupByPage($elements);      // Grouped by page
   ```

## Comparison: Unstructured API vs PdfParser

| Feature | Unstructured API | PdfParser |
|---------|------------------|-----------|
| OCR Support | ✅ Yes | ❌ No |
| Large Files (>2MB) | ✅ Handles well | ⚠️ Memory issues |
| Scanned PDFs | ✅ Yes | ❌ No |
| Processing Speed | ~1 page/second | Faster for text PDFs |
| Structured Output | ✅ Element types | Plain text only |
| Page Metadata | ✅ Yes | Limited |
| Network Required | ✅ Yes | ❌ Local |

## Files Generated

When processing the Customs Act, the following files were created:

| File | Description | Size |
|------|-------------|------|
| `storage/app/customs_act_extracted.json` | Raw JSON with all elements | ~5 MB |
| `storage/app/customs_act_full_text.txt` | Clean readable text | 971 KB |

## Troubleshooting

### Connection Timeout
```
cURL Error: Operation timed out
```
**Solution**: Increase timeout in config or use `fast` strategy for large documents.

### Empty Text Returned
```php
if (mb_strlen($text) < 50) {
    // Document may be image-only
    $text = $client->extractText($file, 'ocr_only');
}
```

### API Unavailable
Always implement fallback logic:
```php
if (!$client->healthCheck()) {
    // Use local PdfParser or reject with user-friendly message
}
```

## API Reference

### UnstructuredApiClient Methods

| Method | Parameters | Returns | Description |
|--------|------------|---------|-------------|
| `healthCheck()` | none | `bool` | Check if API is available |
| `extractFromFile()` | `$file, $options = []` | `array` | Extract elements from file |
| `extractText()` | `$file, $strategy = 'auto'` | `string` | Convenience: get plain text |
| `elementsToText()` | `$elements` | `string` | Convert elements to text |
| `extractTables()` | `$elements` | `array` | Get table elements only |
| `groupByPage()` | `$elements` | `array` | Group elements by page number |

---

*Last updated: January 2026*
