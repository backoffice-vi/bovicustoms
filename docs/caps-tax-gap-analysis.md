# CAPS Tax Gap Analysis

**Date:** 2026-05-05
**Trigger:** TD `004497889` (declaration 63, file `10018405052026.003`) registered by CAPS but came back with a 15-page System Query Report flagging every line for `VALUE FOR TAX NOT CORRECT`, `TAX RATE NOT CORRECT`, `TAX AMOUNT CALCULATION MISMATCH TOO LARGE`, plus header-level `TOTAL INSURANCE AMOUNT NOT CORRECT` and a few `TARIFF NO. NOT KNOWN`.
**Method:** Compared declaration 63 against 50 legacy CAPS-approved declarations (`source_type=legacy`) for the same trader, with a deep dive into legacy 58 / 59 (which share supplier, freight, and currency with our submission).
**Inputs / artefacts:**
- `storage/app/analysis/legacy-ratios.csv` — per-declaration computed ratios
- `storage/app/analysis/decl63-vs-legacy58.txt` — side-by-side dump
- `storage/app/ftp-submissions/1/10018405052026.003` — the actual T12 we sent
- `app/Services/FtpSubmission/CapsT12Generator.php` — generator under audit
- FTP Submission spec v4.0 (CAPS Electronic Submission Guide)

---

## Executive Summary

The good news: **the core value bases in `CapsT12Generator` appear to be right**. Wharfage rate (2%), wharfage base (FOB), customs duty base (CIF), CIF formula (FOB+freight+insurance), and per-line rounding match what CAPS-accepted legacy declarations show. The 1% wharfage rate I initially suspected from legacy 58 turned out to be the outlier — 395 of 429 legacy items use 2% on FOB, exactly what we send.

The bad news, in priority order:

1. **Header totals do not equal the sum of the line items we send.** CAPS recomputes header values by summing the R30/R40/R50 detail rows. For declaration 63 the gaps are: insurance −0.03, CIF −0.08, total payable +0.51, customs duty +0.50, freight −0.05, wharfage +0.02. This is the clearest root cause for the header-level complaints and may contribute to the tax mismatch cascade. **Fix scope: small** — recompute the R10 header totals from the rendered R30/R40/R50 lines just before writing, instead of taking them from the declaration model.

2. **Our system is sending unresolved or invented tariff codes.** 10 of the 28 distinct 7-digit tariffs in declaration 63 are not in our local `customs_codes` table; at least 5 of those are heading-level codes padded with `000` (`1101.000`, `2940.000`, `0712.000`, `1008.000`, `2923.000`). This violates the project's own `no-tariff-guessing` rule and triggers CAPS' `TARIFF NO. NOT KNOWN`. **Fix scope: medium** — these need to be classified before submit, not generated.

3. **The pre-submission validator was loosened from blocking to warning** for "padded" codes earlier today so the user could submit. Now CAPS confirms those padded codes really are rejected, so the validator should go back to **blocking** for unknown exact codes (not just heading-padded ones).

4. **R50 duty rate validation needs a second pass.** The local line math is internally consistent, but CAPS may be rejecting rates because the tariff is unknown or because the selected tariff's official duty rate does not match the rate we send. The next code change should audit each R50 CUD row against the exact tariff record, not only fix header rollups.

Everything else (base values, rounding mode, currency code) checked out. R50 rate field formatting is likely acceptable because the file passed format validation, but it is not fully proven from legacy source T12 files.

---

## Observed Legacy Ratios (50 declarations, 429 items)

### Header-level effective ratios

| Metric            | n  | min     | median  | max      | mean    |
|-------------------|----|---------|---------|----------|---------|
| `wha_total / fob` | 50 | 0       | 1.9997% | 2.0015%  | 1.30%   |
| `wha_total / cif` | 50 | 0       | 1.7358% | 1.9326%  | 1.12%   |
| `cud_total / cif` | 50 | 0       | 4.9997% | 12.4237% | 4.07%   |
| `ins_total / fob` | 50 | 0       | 1.0004% | 163.30%* | 13.83%* |
| `frt_total / fob` | 50 | 0       | 9.20%   | 1250%*   | 102%*   |

\* extreme rows are 12 stub records with FOB=$10 and disproportionate freight/insurance — they are admin/test entries, not real shipments. Median is the meaningful figure.

**Conclusion:** wharfage at **2% of FOB** and insurance at **1% of FOB** are the rules CAPS uses. These match what `CapsT12Generator::deriveItemValues()` already does.

### Per-item rate distribution across legacy items

| Tax | Rate | Item count |
|-----|------|------------|
| WHA | 2    | 395        |
| WHA | 1    | 34         |
| CUD | 5    | 172        |
| CUD | 10   | 120        |
| CUD | 0    | 89         |
| CUD | 15   | 18         |
| CUD | 20   | 16         |

Wharfage is essentially always 2%. Customs duty rates vary by tariff (correct — they come from the per-tariff table). The 34 WHA-at-1% items all live in legacy 58 only, which appears to be an erroneous/superseded duplicate of legacy 59 (same form_number lineage, same FOB/freight/CIF, but 1% wharfage). **Treat legacy 58 as bad data.**

### Per-item base detection

For every legacy item, the script classified the R50 `value-for-tax` against four candidates (CIF, derived-FOB, line_total, OTHER). Result:

- **CUD value-for-tax = item CIF** in all 429 classifiable items.
- **WHA value-for-tax = item FOB** (= CIF − freight − insurance) in all 429 items.

Both match our generator (`CapsT12Generator.php` lines 406, 419). No change needed.

---

## Side-By-Side: Declaration 63 vs Legacy 58 / 59

### Header totals

| Field               | Ours #63           | Legacy #58 | Legacy #59 |
|---------------------|--------------------|------------|------------|
| `form_number`       | DEC-20260505-KOSEOE | 004438784  | 004439253  |
| `currency`          | USD                | USD        | USD        |
| `fob_value`         | 2377.03            | 1558.82    | 1558.82    |
| `freight_total`     | 125.00             | 125.00     | 125.00     |
| `insurance_total`   | 23.77              | 15.59      | 15.59      |
| `cif_value`         | 2525.80            | 1699.41    | 1699.41    |
| `customs_duty_total`| 192.65             | 139.70     | 139.70     |
| `wharfage_total`    | 47.54              | 16.99      | 31.18      |
| `total_duty`        | 240.19             | 156.69     | 170.88     |
| item_count          | 68                 | 34         | 34         |

| Effective ratio  | Ours #63 | Legacy #58 | Legacy #59 |
|------------------|----------|------------|------------|
| WHA / FOB        | 2.0000%  | 1.0899%    | 2.0002%    |
| WHA / CIF        | 1.8822%  | 0.9998%    | 1.8348%    |
| CUD / CIF        | 7.6273%  | 8.2205%    | 8.2205%    |
| INS / FOB        | 1.0000%  | 1.0001%    | 1.0001%    |

Our header ratios match legacy 59 (the "good" version). The shape of the file is right.

### Header-vs-detail rollup for `10018405052026.003`

| Aggregate                     | Header value | Sum of detail rows | Δ (detail − header) |
|-------------------------------|--------------|--------------------|---------------------|
| FOB Value                     | 2377.03      | 2377.03            |  0.00 ✓             |
| CIF Value                     | 2525.80      | 2525.72            | **−0.08**           |
| Total Due / Total Payable     |  240.19      |  240.70            | **+0.51**           |
| Total Freight                 |  125.00      |  124.95            | **−0.05**           |
| Total Insurance               |   23.77      |   23.74            | **−0.03**           |
| Customs Duty                  |  192.65      |  193.15            | **+0.50**           |
| Wharfage                      |   47.54      |   47.56            | **+0.02**           |

Every aggregate except FOB drifts by more than rounding tolerance. **CAPS validates header == sum-of-details and that's the source of the "not correct" cascade.**

### Sampled item, R30/R40/R50 (item #2 of declaration 63)

```
R30  CPC=C400 tariff=2501001 origin=US pkgs=1 desc="A VOGEL SALT SEA HERBAMARE 8.80 OZ"
     qty=6.00 netWt=6.00 fob=40.92 cif=43.48 totalDue=5.17 currency=USD
R40  FRT  2.15
R40  INS  0.41
R50  CUD value=43.48 rate=10.000 amount=4.35   [v*r/100=4.348  Δ=+0.002]
R50  WHA value=40.92 rate=2.000  amount=0.82   [v*r/100=0.818  Δ=+0.002]
```

CIF check: 40.92 + 2.15 + 0.41 = 43.48 ✓. Both R50 amounts within 0.5¢ of value×rate ✓. Per-item math is right.

### Sampled legacy item (legacy 59, item #1)

```
hs=1904100  desc="BARBARAS CHEESE PUFF ORIGINAL 7 OZ"  qty=1  line_total=37.08
meta cif_value=40.42 freight=2.97 insurance=0.37 total_due=4.78
tax CUD value=40.42 rate=10 amount=4.04  [v*r/100=4.042  Δ=−0.002]
tax WHA value=37.08 rate=2  amount=0.74  [v*r/100=0.7416 Δ=−0.0016]
```

Legacy uses identical bases and identical rounding tolerance. Our per-item rows are spec-correct.

---

## Suspect Audit (the seven items from the plan)

| # | Suspect                  | Verdict     | Evidence                                                                                          |
|---|--------------------------|-------------|---------------------------------------------------------------------------------------------------|
| 1 | Wharfage rate            | **CORRECT** | 92% of legacy items at 2%; legacy median wha/fob = 1.9997%.                                       |
| 2 | Wharfage base            | **CORRECT** | All 429 legacy items use FOB-shaped value-for-tax in R50 WHA.                                     |
| 3 | R50 Tax Rate format      | **LIKELY OK** | Spec v4.0: `FLOAT 3,7` = decimal rate field. Our `2.000` / `10.000` passed format validation. Raw legacy T12 files were not available, so this is not fully proven. |
| 4 | R50 Value for Tax (base) | **CORRECT** | Per-item check: CUD=CIF, WHA=FOB across all legacy items.                                         |
| 5 | R50 Tax Amount rounding  | **CORRECT** at line level | All ours and legacy lines are within ±0.005 of `value × rate / 100`. **WRONG** in aggregate. |
| 6 | Header TOTAL INSURANCE   | **WRONG**   | Header 23.77 vs sum of R40 INS 23.74. Mismatch −0.03. Same pattern for CIF, CUD, WHA, FRT, total. |
| 7 | TARIFF NO. NOT KNOWN     | **WRONG**   | 10 of 28 distinct tariffs absent from `customs_codes`; at least 5 are padded headings (`xxxx.000`). |

---

## Root Causes

### RC-1 (high impact): Header totals are taken from the declaration model, not recomputed from rendered lines

`CapsT12Generator::generateHeader()` writes the R10 record using `$declaration->insurance_total`, `$declaration->freight_total`, `$declaration->total_duty`, etc. (file `app/Services/FtpSubmission/CapsT12Generator.php:264-268`). Those numbers were calculated upstream by the duty engine and rounded to 2dp once. The R30/R40/R50 lines are then independently rounded per-item to 2dp from the same upstream values; the per-item rounding errors do not cancel out, so the sum of details ≠ the header.

CAPS' validator appears to recompute and reject these mismatches.

This root cause likely explains:
- `TOTAL INSURANCE AMOUNT NOT CORRECT` (header insurance ≠ sum of R40 INS)
- some `VALUE FOR TAX NOT CORRECT` cases where header CIF / detail CIF are inconsistent
- some `TAX AMOUNT CALCULATION MISMATCH TOO LARGE` cases where detail totals and header totals diverge

It does **not** prove every per-line `TAX RATE NOT CORRECT` issue. CAPS may also validate each item against the official rate for its tariff code.

### RC-2: Our system is sending unresolved or padded tariff codes

CAPS rejected 3 lines from declaration 63 with `TARIFF NO. NOT KNOWN`. The actual T12 contains:
- `1101.000`, `2940.000`, `0712.000`, `1008.000`, `2923.000` — heading-level codes padded with `000`. These are **explicit no-tariff-guessing rule violations**.
- `1212.900`, `1513.290`, `0813.200`, `2009.801`, `1207.901`, `1904.900` — non-padded codes that may be valid CAPS subheadings but are not in our local `customs_codes` table.

The fix here is upstream of the T12 generator: classification needs to resolve every line to a real exact 7-digit code before submit.

### RC-3: R50 CUD rate is not being independently validated against the exact tariff row

The T12 row math is internally consistent (`value × rate / 100 ≈ amount`), but internal consistency is not enough. CAPS validates the rate against the tariff code. If our tariff is unknown, padded, or mismatched to the product, CAPS can report both `TARIFF NO. NOT KNOWN` and `TAX RATE NOT CORRECT`.

### RC-4: Pre-submission validator was downgraded from blocking to warning earlier today

`CapsPreValidationService::validateTariff()` (file `app/Services/WebFormSubmission/CapsPreValidationService.php`) was changed earlier today so that "tariff appears to be a padded or shorter heading" became a warning instead of an error so the user could submit. Now we have CAPS-side proof those padded codes are rejected, so it should go back to **blocking** — at minimum for codes ending in `000` that don't exist as exact subheadings in `customs_codes`.

---

## Prioritized Fix List

| # | Fix                                                                                                              | CAPS errors it eliminates                                                                                     | Scope                                                                                                       | Risk |
|---|------------------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------|------|
| 1 | Recompute R10 header totals (FOB, CIF, freight, insurance, total payable, and per-tax totals if any) from the rendered R30/R40/R50 sums right before emitting the file. Apply the same to `total_duty`. | TOTAL INSURANCE NOT CORRECT, header/detail value mismatches, some tax amount mismatches | Single method in `CapsT12Generator` (~30 lines). Add a unit test that asserts header == sum-of-details after generation. | low  |
| 2 | Re-block tariff pre-validation: any 7-digit code not present as an exact match in `customs_codes` (dotted or undotted) blocks submit, with a "Look up in CAPS" prompt. | TARIFF NO. NOT KNOWN                                                                                          | Flip warning → error in `CapsPreValidationService::validateTariff()` and add a UI message.  | low  |
| 3 | Add an R50 audit before FTP submission: for each CUD row, verify the tariff is exact and the duty rate sent matches the local exact tariff row. | TAX RATE NOT CORRECT, TARIFF NO. NOT KNOWN, tariff/rate mismatch before submission | Extend `CapsPreValidationService`; depends on available duty-rate columns in `customs_codes`. | medium |
| 4 | Resolve the 10 currently-unresolved tariffs in declaration 63 against the BVI tariff schedule (manual classification or admin import), back-fill the items, then resubmit (or amendment). | TARIFF NO. NOT KNOWN for declaration 63 specifically.                                                         | Data fix, not code. Use the CAPS Tariff Lookup. Do not auto-pad.                                            | low  |
| 5 | Add an integration smoke-test: generate T12 for a fixture declaration with known totals, parse it back, assert sum(R40 INS) == header insurance, sum(R50 CUD amount) == header customs_duty_total, etc. | Regression net for #1.                                                                                        | One PHPUnit test in `tests/Feature/FtpSubmission`.                                                          | low  |

Fix #1 is the fastest high-confidence change. Fixes #2 and #3 are equally important before any live amendment because unresolved tariff/rate mismatches can produce the same CAPS query categories even after header totals are corrected.

---

## Open Questions

1. **CAPS rounding tolerance.** The spec v4.0 doesn't state a tolerance for header-vs-detail mismatch. Current observed gap of 0.03 on insurance was rejected. We need either an officially documented tolerance (ideal) or to simply force exact equality (safe).
2. **Whether CAPS expects an `R40 OTH` line for any other levies on declaration 63.** Our generator emits `OTH` only when `levy_breakdown` is populated. Declaration 63's `other_levies_total` was 0 so no R40 OTH was sent. The query report didn't flag missing R40 OTH; this question is parked.
3. **Whether "TAX RATE NOT CORRECT" survives Fix #1.** Hypothesis: after we make header == sum(detail) the rate complaint disappears because CAPS' recomputed `amount/value` rate becomes consistent. If it persists, look at switching `formatDecimal(rate, 7, 3)` from `2.000` to `2.00` to mirror the spec sample exactly.
4. **Whether legacy 58 (the 1% wharfage outlier) is a CAPS-rejected duplicate.** Worth confirming with the trader — if it is, exclude it from any future ratio analysis.

---

## Out Of Scope (per plan)

- Implementing the fixes themselves — this report ends with the prioritized list. Once you confirm the priorities a separate plan / PR will follow.
- Capturing TD numbers in `web_form_submissions` (still a follow-up from the prior chat).
- Resubmitting declaration 63 (TD 004497889 already exists; an amendment would be the right path once tariffs are corrected).
