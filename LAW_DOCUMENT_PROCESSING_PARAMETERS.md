# Law Document Processing - Testing Parameters Guide

This document explains the testing parameters and configuration options for the Law Document Processing module.

---

## 🆕 Text Caching (NEW)

Extracted text is now **cached in the database** to avoid re-calling the Unstructured API on reprocessing.

### How It Works

| Run | Unstructured API | Time Saved |
|-----|------------------|------------|
| First process | ✅ Called (60s) | - |
| Reprocess | ❌ Skipped (cached) | **60 seconds!** |

### Database Columns

```sql
law_documents.extracted_text   -- LONGTEXT (stores ~950K chars)
law_documents.extracted_at     -- TIMESTAMP (when extracted)
```

### Benefits
- **60 seconds saved** on each reprocess
- **No network calls** to Unstructured API
- **Instant text access** from database
- **Reliable** - text preserved even if API is down

### Force Re-extraction
To force re-extraction (e.g., if PDF was updated):
```php
$document->clearExtractedText();  // Clears cache
// Next process will call Unstructured API again
```

---

## 📋 Overview

The Law Document Processor has two modes:

| Mode | Purpose | Codes Extracted | Time |
|------|---------|-----------------|------|
| **Test Mode** ✅ | Quality testing, debugging | ~10 codes | ~5 minutes |
| **Full Mode** | Production extraction | All codes (hundreds) | 15-30 minutes |

**Current Setting**: Test Mode is **ENABLED**

---

## ⚙️ Test Mode Parameters

### Location
`app/Services/LawDocumentProcessor.php`

### Parameter 1: Chunk Limit (Line ~299-303)

```php
// TEST MODE: Only process first chunk to get ~10 codes
$testMode = true;
$maxChunks = $testMode ? 1 : count($chunks);
```

| Setting | Effect |
|---------|--------|
| `$testMode = true` | Processes only 1 chunk (~10 codes) |
| `$testMode = false` | Processes ALL chunks (full extraction) |

### Parameter 2: Code Count Limit (Line ~682-684)

```php
// TEST MODE: Only extract 10 codes for quality testing
return <<<PROMPT
Extract EXACTLY 10 tariff/customs codes from this document...
```

| Setting | Effect |
|---------|--------|
| `EXACTLY 10` | Limits AI to return only 10 codes per chunk |
| Remove limit | AI extracts all codes from each chunk |

---

## 🔧 How to Enable Full Extraction Mode

### Step 1: Disable Test Mode Flag

Edit `app/Services/LawDocumentProcessor.php` around line 299-301:

**From (Test Mode):**
```php
// TEST MODE: Only process first chunk to get ~10 codes
$testMode = true;
$maxChunks = $testMode ? 1 : count($chunks);
```

**To (Full Mode):**
```php
// FULL MODE: Process all chunks for complete extraction
$testMode = false;
$maxChunks = $testMode ? 1 : count($chunks);
```

### Step 2: Update AI Prompt for Full Extraction

Edit the `buildCodesPrompt()` method around line 680-700:

**From (Test Mode):**
```php
return <<<PROMPT
Extract EXACTLY 10 tariff/customs codes from this document. Choose codes from different chapters to test variety.
...
```

**To (Full Mode):**
```php
return <<<PROMPT
Extract ALL tariff/customs codes from this document section. Be thorough and extract every code you find.
...
```

---

## 📊 Expected Results by Mode

### Test Mode (Current)

| Metric | Value |
|--------|-------|
| **Chunks Processed** | 1 of 3 |
| **Codes per Chunk** | ~10 |
| **Total Codes** | ~10-15 |
| **Processing Time** | ~5 minutes |
| **API Calls** | Minimal |
| **Cost** | Low |

### Full Mode

| Metric | Value |
|--------|-------|
| **Chunks Processed** | All (3 for 15MB PDF) |
| **Codes per Chunk** | 50-150 |
| **Total Codes** | 150-500+ |
| **Processing Time** | 15-30 minutes |
| **API Calls** | Many |
| **Cost** | Higher |

---

## ⏱️ Time/Resource Limits

These limits are configured throughout the processor to prevent timeouts:

### PHP Execution Limits

| Location | Setting | Value | Purpose |
|----------|---------|-------|---------|
| `process()` method | `set_time_limit()` | 1800s (30 min) | Queue worker timeout |
| `process()` method | `memory_limit` | 1GB | Large document handling |
| `extractFromPdf()` | `set_time_limit()` | 600s (10 min) | PDF extraction timeout |
| `public/index.php` | `set_time_limit()` | 900s (15 min) | Web request timeout |

### API Timeouts

| Service | Setting | Value |
|---------|---------|-------|
| Unstructured API | `timeout` | 600 seconds |
| Claude API | Per-call timeout | 180 seconds |

### Configuration Files

**.env:**
```env
UNSTRUCTURED_API_TIMEOUT=600
CLAUDE_MAX_TOKENS=16384
```

**config/services.php:**
```php
'unstructured' => [
    'url' => env('UNSTRUCTURED_API_URL', 'http://34.48.24.135:8888'),
    'timeout' => env('UNSTRUCTURED_API_TIMEOUT', 300),
],
```

---

## 🧪 Test Mode Benefits

1. **Fast Iteration**: Process document in 5 minutes instead of 30
2. **Quality Verification**: Check if extraction is accurate before full run
3. **Cost Savings**: Fewer API calls during development
4. **Debugging**: Easier to identify issues with smaller dataset
5. **Database Testing**: Verify schema compatibility with sample data

---

## 🚀 When to Use Each Mode

### Use Test Mode When:
- ✅ First time processing a new document type
- ✅ Testing code changes or fixes
- ✅ Debugging extraction issues
- ✅ Verifying database schema changes
- ✅ Development and QA environments

### Use Full Mode When:
- ✅ Production deployment
- ✅ Final extraction after test mode verification
- ✅ Complete tariff schedule needed
- ✅ Data quality has been verified in test mode

---

## 📁 Document Chunking Parameters

The document is split into chunks based on token limits:

**Location**: `app/Services/DocumentChunker.php` and `config/services.php`

```php
'claude' => [
    'chunk_size' => env('CLAUDE_CHUNK_SIZE', 95000), // Safe chunk size under 100k
    'max_context_tokens' => env('CLAUDE_MAX_CONTEXT_TOKENS', 200000),
],
```

| Parameter | Value | Purpose |
|-----------|-------|---------|
| `chunk_size` | 95,000 tokens | Maximum text per AI call |
| `max_context_tokens` | 200,000 | Claude's context window |

### Chunk Statistics (15MB PDF)

From the live test:
```
Total estimated tokens: 237,673
Max tokens per chunk: 95,000
Estimated chunks: 3
Actual chunk sizes: [94,967, 95,000, 44,906]
```

---

## 🔄 Processing Passes

The processor runs 8 sequential passes:

| Pass | Name | Purpose | Test Mode Impact |
|------|------|---------|------------------|
| 1 | Structure | Extract sections/chapters | Full extraction |
| 2 | Notes | Extract chapter notes | Full extraction |
| 3 | Exclusion Rules | Parse exclusions from notes | Full extraction |
| **4** | **Tariff Codes** | Extract customs codes | **LIMITED in test mode** |
| 5 | Exemptions | Extract Schedule 5 items | Full extraction |
| 6 | Prohibited/Restricted | Identify banned goods | Full extraction |
| 7 | Levies | Extract additional taxes | Full extraction |
| 8 | Classification Aids | Compute search aids | Based on extracted codes |

**Note**: Only Pass 4 (Tariff Codes) is limited in test mode. All other passes run fully.

---

## 📈 Live Test Results (Test Mode)

**Document**: Customs Management and Duties Act 2010 (14.35 MB)

### Extraction Statistics:

| Category | Count |
|----------|-------|
| Tariff Chapters | 20 |
| Chapter Notes | 10 |
| **Tariff Codes** | **14** (test mode limited) |
| Exemptions | 17 |
| Prohibited Goods | 4 |
| Restricted Goods | 6 |
| Exclusion Rules | 1 |
| Additional Levies | 1 |

### Timing:

| Phase | Duration |
|-------|----------|
| Unstructured API Extraction | 59 seconds |
| Pass 1 (Structure) | 15 seconds |
| Pass 2 (Notes) | 2 min 9 sec |
| Pass 3 (Exclusions) | Instant |
| Pass 4 (Codes) | 29 seconds |
| Pass 5 (Exemptions) | 45 seconds |
| Pass 6 (Prohibited) | 19 seconds |
| Pass 7 (Levies) | 3 seconds |
| Pass 8 (Aids) | 4 seconds |
| **Total** | **~5 minutes** |

---

## 🛠️ Quick Reference: Switching Modes

### Enable Test Mode (Default)
```php
// In extractTariffCodes() method
$testMode = true;
```

### Enable Full Mode
```php
// In extractTariffCodes() method
$testMode = false;
```

### Verify Current Mode
Check the logs when processing:
```
[INFO] Extracting codes from chunk 1 of 1 (test mode: 1)  // Test mode
[INFO] Extracting codes from chunk 1 of 3 (test mode: 0)  // Full mode
```

---

## 📝 Recommendations

1. **Always test first**: Run test mode before full extraction
2. **Monitor logs**: Watch for errors during first chunk
3. **Check data quality**: Verify extracted codes are accurate
4. **Plan for time**: Full extraction takes 15-30 minutes
5. **Have patience**: Large documents require multiple AI calls

---

## 🔍 Troubleshooting

### Issue: Too few codes extracted
**Cause**: Test mode is enabled  
**Solution**: Set `$testMode = false` in `extractTariffCodes()`

### Issue: Processing takes too long
**Cause**: Full mode on large document  
**Solution**: Enable test mode for initial testing, or increase timeouts

### Issue: Memory exhaustion
**Cause**: Document too large for local parser  
**Solution**: Ensure Unstructured API is used (automatic for PDFs)

### Issue: API timeout
**Cause**: Chunk too large or API slow  
**Solution**: Reduce `CLAUDE_CHUNK_SIZE` in `.env`

---

*Last Updated: January 23, 2026*  
*Current Mode: **Test Mode (10 codes per run)***
