# Database Fixes and Known Issues

## Note Number Column Type Fix (January 23, 2026)

### Issue
Law document processing was failing during the notes extraction phase with the error:

```
SQLSTATE[01000]: Warning: 1265 Data truncated for column 'note_number' at row 1
```

### Root Cause
The `note_number` column in `tariff_chapter_notes` and `tariff_section_notes` tables was defined as an integer, but tariff documents often contain compound note numbers like:
- "5(B)"
- "1(a)"
- "2(i)"
- "3(A)(1)"

When the system tried to insert "5(B)" into an integer column, the database rejected it.

### Impact
- Document processing would fail partway through
- Chapters, some notes, exemptions, and prohibited/restricted goods would be extracted
- **Customs codes would NOT be extracted** (this step runs after notes extraction)
- The document status would show as "failed"

### Fix Applied
Migration `2026_01_23_102130_change_note_number_to_string_in_tariff_notes.php` changes:
- `tariff_chapter_notes.note_number` from `unsignedInteger` to `string(20)`
- `tariff_section_notes.note_number` from `unsignedInteger` to `string(20)`

### After Applying Fix
If you have documents that failed processing before this fix:
1. Go to **Admin → Law Documents**
2. Find the affected document
3. Click **"Reprocess"** to re-run the extraction

The document will now successfully extract all notes (including those with compound numbers) and proceed to extract customs codes.

### Related Files
- `database/migrations/2026_01_23_102130_change_note_number_to_string_in_tariff_notes.php`
- `app/Services/LawDocumentProcessor.php` (extractNotes method)
- `app/Models/TariffChapterNote.php`
- `app/Models/TariffSectionNote.php`
