# Classification System Architecture

## Overview

The BVI Customs application uses a **Tiered Classification System** that intelligently routes queries through progressively more sophisticated search methods. This approach optimizes for speed, cost, and accuracy.

## Primary Flow: Tiered Classification

```
User Input
    │
    ▼
┌─────────────────────────────────────────────────────────────┐
│  TIER 1: Exact Code Match                                   │
│  ─────────────────────────                                  │
│  • User typed a code directly (e.g., "02.07", "8517")       │
│  • Instant lookup from database                             │
│  • Cost: FREE | Speed: <100ms                               │
└─────────────────────────────────────────────────────────────┘
    │ Not a code? ──▶
    ▼
┌─────────────────────────────────────────────────────────────┐
│  TIER 2: Database Keyword Search + Claude AI                │
│  ───────────────────────────────────────────                │
│  1. Extract keywords from description                       │
│  2. Search database for matching codes (candidates)         │
│  3. Claude AI selects best match from candidates            │
│  4. If Claude returns status: "insufficient/ambiguous"      │
│     → Expand keywords with AI (chicken → poultry, meat)     │
│     → Search broader chapters                               │
│     → Claude selects from expanded results                  │
│  5. Build alternatives from remaining candidates            │
│     → Prioritize same heading (8471.x for 84.71 result)     │
│     → Then same chapter codes                               │
│     → Up to 5 alternatives shown                            │
│  • Cost: 1-2 Claude API calls | Speed: 5-30 seconds         │
└─────────────────────────────────────────────────────────────┘
    │ Still no match? ──▶
    ▼
┌─────────────────────────────────────────────────────────────┐
│  TIER 3: Qdrant Vector Search + Claude AI                   │
│  ─────────────────────────────────────────                  │
│  1. Generate embedding from item description                │
│  2. Semantic search in Qdrant (law text + codes)            │
│  3. Claude AI reasons from vector results                   │
│  4. Returns alternatives for ambiguous items                │
│  • Cost: OpenAI embedding + Claude | Speed: 10-20 seconds   │
└─────────────────────────────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────────────────────────────┐
│  Database Enrichment (ALWAYS)                               │
│  ─────────────────────────────                              │
│  • Duty rates ALWAYS come from database (source of truth)   │
│  • AI-suggested codes mapped to actual database codes       │
│  • Alternatives enriched with correct rates                 │
└─────────────────────────────────────────────────────────────┘
```

## Claude AI Response Format

All Claude prompts use a **structured status field** for reliable routing:

```json
{
    "status": "success",
    "code": "8471.30",
    "description": "Portable digital automatic data processing machines",
    "duty_rate": 10,
    "confidence": 95,
    "explanation": "Detailed reasoning...",
    "alternatives": ["8517.00", "8473.30"]
}
```

### Status Values

| Status | Meaning | Action |
|--------|---------|--------|
| `success` | Found a matching code | Use the result |
| `insufficient` | Item doesn't match provided codes | Expand keywords or go to Tier 3 |
| `ambiguous` | Multiple codes could apply | Show alternatives to user |
| `not_found` | Cannot determine classification | Try Tier 3 or report failure |

## Test Results

### Example: "samsung tablet"

| Step | Action | Result |
|------|--------|--------|
| 1 | Keywords extracted | "samsung", "tablet" |
| 2 | Database search | Found 2 codes (pharma tablets - wrong!) |
| 3 | Claude classification | Status: `insufficient` (not electronics) |
| 4 | Keyword expansion | "electronic", "machine", "data", "computer" |
| 5 | Expanded search | Found ~180 codes in chapters 84, 85 |
| 6 | Claude selection | **84.71** - Automatic data processing machines |
| 7 | Enrichment | Duty rate: **10%** from database |
| 8 | Alternatives built | 8471.10, 8471.30, 8471.41, 8471.49, 8471.50 (same heading) |

### Example: "frozen chicken"

| Step | Action | Result |
|------|--------|--------|
| 1 | Keywords extracted | "frozen", "chicken" |
| 2 | Database search | Found candidate codes in chapter 02 |
| 3 | Claude classification | Status: `success` |
| 4 | Claude selection | **0207.40** - Poultry cuts and offal, frozen |
| 5 | Enrichment | Duty rate: **0%** from database |
| 6 | Alternatives built | 0207.20, 0207.50, 02.07, 02.02, 02.03 (same heading/chapter) |

### Example: "02.07" (Exact Code)

| Step | Action | Result |
|------|--------|--------|
| 1 | Detected as code | Tier 1 direct lookup |
| 2 | Database match | **02.07** - Meat and edible offal of poultry |
| 3 | Result | Duty rate: **0%**, Confidence: **100%** |

## Data Sources in Qdrant

| Data Type | Count | Purpose |
|-----------|-------|---------|
| Law Document Text | ~23 chunks | Legal context, definitions, rules |
| Customs Codes | 3,199 | Primary classification targets with duty rates |
| Chapter Notes | 397 | Classification rules and exclusions |
| Exclusion Rules | 22 | What's excluded from chapters |
| Exemptions | 95 | Available duty exemptions |
| **Total** | **~3,736** | Full tariff and legal context |

## Key Principles

### 1. Database is Source of Truth for Rates
- Duty rates **NEVER** come from Qdrant payloads
- AI suggestions are always enriched with database values
- This ensures rates match the official tariff schedule

### 2. Structured AI Responses
- No pattern matching on error strings
- Claude returns explicit `status` field
- Reliable routing based on status value

### 3. Progressive Fallback
- Start with cheapest/fastest method
- Only escalate when necessary
- Each tier adds more context/capability

### 4. Keyword Expansion
- When initial keywords fail, AI expands semantically
- "tablet" → "computer", "electronic", "data processing"
- "chicken" → "poultry", "meat", "animal"

## Configuration

### Current Mode: **Tiered Classification** ✅

Located in `app/Services/ItemClassifier.php`:

```php
// Tier 1: Exact code match (always enabled)
// Tier 2: Database keyword search (always enabled)
// Tier 3: Qdrant + Claude (enabled when vector classifier available)

protected bool $useHybridMode = true;   // Enables Tier 3 with Claude
protected bool $useVectorOnly = false;  // Don't skip database search
```

### To Modify Behavior

```php
// Use ONLY Qdrant (skip database search)
$classifier->setVectorOnly(true);

// Use Qdrant + Claude hybrid mode
$classifier->setHybridMode(true);

// Disable vector search entirely (database + Claude only)
$classifier->setHybridMode(false);
$classifier->setVectorOnly(false);
```

## Files Involved

| File | Purpose |
|------|---------|
| `app/Services/ItemClassifier.php` | Main classification logic, tiered routing, alternatives building |
| `app/Services/VectorClassifier.php` | Qdrant search and vector operations |
| `app/Services/EmbeddingService.php` | OpenAI embeddings generation |
| `app/Services/QdrantClient.php` | Qdrant API communication |
| `app/Console/Commands/SyncTariffVectors.php` | Sync database to Qdrant |
| `app/Console/Commands/VectorizeLawDocument.php` | Upload law PDF/text to Qdrant |

## Key Methods in ItemClassifier

### `buildAlternativesFromCandidates()`
Builds relevant alternatives from database candidates after Claude selects the best match:

```php
protected function buildAlternativesFromCandidates(
    $candidates,           // Collection of codes from database search
    string $selectedCode,  // The code Claude selected
    array $aiAlternatives, // Any alternatives Claude suggested
    int $maxAlternatives   // Max alternatives to return (default: 5)
): array
```

**Prioritization Logic:**
1. First adds any alternatives Claude explicitly suggested (if they exist in candidates)
2. Sorts remaining candidates by relevance:
   - Same heading (first 4 digits match) → Priority 0
   - Same chapter (first 2 digits match) → Priority 1
   - Other chapters → Priority 2
3. Returns up to 5 alternatives with code, description, duty_rate, and customs_code_id

## UI Features

### Classification Result Card
- **Item Searched**: The query
- **Best Matching Category**: Top code with description
- **Duty Rate**: From database (always accurate)
- **Confidence**: AI confidence percentage
- **Explanation**: AI reasoning about the classification

### Alternatives Section

Alternatives are **populated from Tier 2 database candidates** after Claude selects the best match:

| Priority | Source | Example |
|----------|--------|---------|
| 1st | Same heading (4-digit) | If result is 84.71, show 8471.10, 8471.30, 8471.41 |
| 2nd | Same chapter (2-digit) | If no same heading, show 84.14, 84.15, etc. |
| 3rd | Other matched chapters | Remaining candidates from search |

**When Alternatives Display:**
- Tier 2 success → Shows up to 5 related codes from the database search candidates
- Hidden if no valid alternatives exist (code = N/A or empty)
- Each alternative has "Select" button to choose that code instead

**Example: "samsung tablet" → 84.71 (10%)**
| Alternative | Description | Duty |
|-------------|-------------|------|
| 8471.10 | Analogue or hybrid ADP machines | 10% |
| 8471.30 | Portable digital ADP machines | 10% |
| 8471.41 | Other digital ADP machines - Single unit | 10% |
| 8471.49 | Other digital ADP machines - Other | 10% |
| 8471.50 | Digital processing units | 10% |

### Ambiguity Warning
Displays when classification is uncertain:
> "This item could fall under multiple categories. Please review the alternatives."

---

*Last Updated: January 26, 2026*
*Classification powered by Tiered Search with Claude AI and Qdrant Vector Search*
*Alternatives populated from Tier 2 database candidates, prioritized by heading/chapter relevance*
