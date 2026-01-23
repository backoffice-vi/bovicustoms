# Vector Classification System Comparison

## Overview

This document compares the two classification systems available in the BVI Customs application:

1. **Database + Claude AI** (Traditional) - Searches database for keyword matches, then uses Claude AI to select the best code - **TEMPORARILY DISABLED**
2. **Qdrant Vector Search** (Active) - Uses semantic similarity with OpenAI embeddings to find relevant codes

## Test Results

### Test Case 1: "Frozen Chicken Breast"

| System | Top Code | Description | Duty Rate | Confidence |
|--------|----------|-------------|-----------|------------|
| **Claude AI** | 02.07 | Meat and edible offal of poultry | 0% | 100% |
| **Qdrant Vector** | 02.07 | Meat and edible offal of poultry | 0% | 38.2% score |

**Result: ✅ Both systems agree**

### Test Case 2: "Nutritional Supplements" (Full Live Test)

| Rank | Code | Description | Duty Rate | Match Score |
|------|------|-------------|-----------|-------------|
| 🏆 **Top** | 3001.00 | Glands and other organs for organo-therapeutic uses | 0.00% | 33.7% |
| 2 | 31.05 | Mineral or chemical fertilizers (tablets/packages) | N/A | 33.2% |
| 3 | 21.04 | Soups, broths, homogenised composite food preparations | N/A | 32.6% |
| 4 | 1302.10 | Vegetable saps and extracts | N/A | 31.4% |
| 5 | 31.02 | Mineral or chemical fertilizers, nitrogenous | N/A | 31.3% |
| 6 | 29.37 | Hormones, natural or synthetic | N/A | 31.2% |

**Result: ✅ Vector search identifies ambiguity - multiple categories have similar scores**

### Database Search Result (For Comparison)

| Search Term | Database Result |
|-------------|-----------------|
| "supplement" | ❌ No matches |
| "vitamin" | ❌ No matches |
| "nutritional" | ❌ No matches |
| "dietary" | ❌ No matches |

**Conclusion:** Database/Claude AI approach fails to find relevant codes when keywords don't match exactly. Vector search finds semantically similar codes regardless of exact wording.

## Key Findings

### Advantages of Qdrant Vector Search

1. **Semantic Understanding**: Finds relevant codes even when exact keywords don't match
2. **Always Returns Results**: Unlike database search which may find nothing
3. **Provides Alternatives**: Shows multiple relevant options with confidence scores
4. **Handles Ambiguity**: When items could fall under multiple categories, it shows all options

### Limitations of Database + Claude AI

1. **Keyword Dependent**: Requires matching keywords in classification_keywords or description
2. **May Return Nothing**: If no keywords match, classification fails
3. **Single Answer Focus**: Designed to return one "best" answer

### When Database Search Fails

The database search for "Nutritional Supplements" found:
- No codes with "supplement", "vitamin", "nutritional", or "dietary"
- Had to fall back to broader searches for "food preparation" or "medicament"

The Qdrant vector search immediately found semantically relevant codes without requiring exact keyword matches.

## Recommendation

**Use Qdrant Vector Search as the primary classification method** when:
- Database codes don't have comprehensive keyword coverage
- Items could legitimately fall under multiple categories
- Users need to see alternative classifications

**Show alternatives** to help users make informed decisions, especially for ambiguous items like:
- Nutritional supplements (food vs. pharmaceutical)
- Electronic devices (by function vs. by component)
- Mixed materials (by primary material vs. by use)

## Configuration

### Current Mode: **Qdrant Vector Only** ✅

The database/Claude AI classification has been temporarily disabled. Classification now uses:
1. Qdrant vector search for semantic matching
2. Shows top match as primary result with vector similarity score
3. Shows alternatives in a table below the result
4. "View All" button opens a modal with all matching categories
5. Ambiguity warning displays when top scores are close together

### To Re-enable Database/Claude AI Classification

In `app/Services/ItemClassifier.php`, change:
```php
protected bool $useVectorOnly = true;  // Change to false
```

Or call the method programmatically:
```php
$classifier->setVectorOnly(false);
```

## UI Features

### Classification Result Card
- **Item Searched**: Shows the query
- **Best Matching Category**: Top code with full description
- **Vector Score**: Progress bar showing match percentage
- **Duty Rate**: Displays the applicable duty
- **Explanation**: AI-generated reasoning about the classification

### Ambiguity Warning ⚠️
When top matches have similar scores (within 10%), a warning displays:
> "**Multiple Categories Possible:** This item could fall under multiple categories. Please review the alternatives below to select the most appropriate classification."

### Alternatives Section
- Shows top 3 alternative codes in a compact table
- Each alternative has a **"Select"** button to choose that classification
- "View All" button opens modal with complete list

### Selecting an Alternative
When you click "Select" on an alternative:
1. The main display updates to show the selected code
2. Badge changes to **"User Selected"**
3. Explanation updates to show the original suggestion and selected code
4. A notification appears: **"Alternative Selected: You have selected a different classification."**
5. **"Revert to Original"** button appears to undo the selection

### Modal: All Matching Categories
- Full-width table showing all vector search results
- Currently selected code highlighted in green with "Selected" badge
- Original AI suggestion marked with "Original" badge if different
- Each row has a **"Select"** button (except currently selected)
- Shows: Code, Description, Duty Rate, Match Score (with progress bar)
- Modal closes automatically when you select an alternative

## Data Synced to Qdrant

| Data Type | Count | Purpose |
|-----------|-------|---------|
| Customs Codes | 457 | Primary classification targets |
| Chapter Notes | 432 | Context for classification rules |
| Exclusion Rules | 182 | Understanding what's excluded from chapters |
| Exemptions | 45 | Available duty exemptions |
| **Total** | **1,116** | Full tariff context |

## Files Modified

| File | Changes |
|------|---------|
| `app/Services/ItemClassifier.php` | Added `$useVectorOnly` flag and `classifyWithVectorOnly()` method |
| `app/Services/VectorClassifier.php` | Added `getPointCount()` and `isReady()` public methods |
| `app/Http/Controllers/ClassificationController.php` | Added vector-specific response fields |
| `resources/views/classification/search.blade.php` | New UI with alternatives table, modal, and ambiguity warning |
| `config/services.php` | Qdrant and OpenAI configuration |

## How to Restore Database/Claude AI Mode

1. Open `app/Services/ItemClassifier.php`
2. Find line: `protected bool $useVectorOnly = true;`
3. Change to: `protected bool $useVectorOnly = false;`
4. Save and test

This will restore the original 9-step classification algorithm that uses database search + Claude AI, with Qdrant as a verification layer rather than the primary classifier.

---

*Last Updated: January 23, 2026*
*Classification powered by Qdrant Vector Search with OpenAI Embeddings*
