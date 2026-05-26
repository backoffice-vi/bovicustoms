<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\CustomsCode;
use App\Models\CustomsCodeRateOverride;
use App\Services\DutyPolicyResolver;
use Illuminate\Http\Request;

/**
 * Admin CRUD for time-bounded duty-rate overrides on individual customs codes.
 *
 * Used when a government temporarily reduces the duty rate on a "basket of
 * goods." Each row pins a single customs code to an override rate over a
 * specified window. The DutyPolicyResolver picks the most-recently effective
 * override for (country, customs_code, date); outside any window it falls
 * back to the base rate on customs_codes.duty_rate. Original rates are never
 * mutated.
 *
 * Bulk add supports two modes:
 *  - by code list: comma- or newline-separated 7-digit / dotted codes
 *  - by chapter:   first 2 digits — applies the override to every code whose
 *                  HS code starts with that chapter prefix
 */
class CustomsCodeRateOverrideController extends Controller
{
    public function index(Request $request)
    {
        $countryId = $request->input('country_id');
        $search = $request->input('search');

        $query = CustomsCodeRateOverride::with(['country', 'customsCode'])
            ->orderBy('country_id')
            ->orderByDesc('effective_from');

        if ($countryId) {
            $query->where('country_id', $countryId);
        }

        if ($search) {
            $query->whereHas('customsCode', function ($q) use ($search) {
                $q->where('code', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        $overrides = $query->paginate(30)->appends($request->query());
        $countries = Country::active()->orderBy('name')->get();

        return view('admin.customs-code-rate-overrides.index', compact('overrides', 'countries', 'countryId', 'search'));
    }

    public function create(Request $request)
    {
        $countries = Country::active()->orderBy('name')->get();
        $selectedCountryId = $request->input('country_id');

        return view('admin.customs-code-rate-overrides.create', compact('countries', 'selectedCountryId'));
    }

    public function store(Request $request, DutyPolicyResolver $resolver)
    {
        $validated = $request->validate([
            'country_id' => 'required|exists:countries,id',
            'customs_code_id' => 'required|exists:customs_codes,id',
            'override_rate' => 'required|numeric|min:0|max:1000',
            'effective_from' => 'required|date',
            'effective_until' => 'nullable|date|after_or_equal:effective_from',
            'source' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        CustomsCodeRateOverride::create($validated);
        $resolver->flush();

        return redirect()
            ->route('admin.customs-code-rate-overrides.index', ['country_id' => $validated['country_id']])
            ->with('success', 'Rate override created.');
    }

    public function edit(CustomsCodeRateOverride $customsCodeRateOverride)
    {
        $countries = Country::active()->orderBy('name')->get();

        return view('admin.customs-code-rate-overrides.edit', [
            'override' => $customsCodeRateOverride->load('customsCode'),
            'countries' => $countries,
        ]);
    }

    public function update(Request $request, CustomsCodeRateOverride $customsCodeRateOverride, DutyPolicyResolver $resolver)
    {
        $validated = $request->validate([
            'country_id' => 'required|exists:countries,id',
            'customs_code_id' => 'required|exists:customs_codes,id',
            'override_rate' => 'required|numeric|min:0|max:1000',
            'effective_from' => 'required|date',
            'effective_until' => 'nullable|date|after_or_equal:effective_from',
            'source' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $customsCodeRateOverride->update($validated);
        $resolver->flush();

        return redirect()
            ->route('admin.customs-code-rate-overrides.index', ['country_id' => $customsCodeRateOverride->country_id])
            ->with('success', 'Rate override updated.');
    }

    public function destroy(CustomsCodeRateOverride $customsCodeRateOverride, DutyPolicyResolver $resolver)
    {
        $countryId = $customsCodeRateOverride->country_id;
        $customsCodeRateOverride->delete();
        $resolver->flush();

        return redirect()
            ->route('admin.customs-code-rate-overrides.index', ['country_id' => $countryId])
            ->with('success', 'Rate override deleted.');
    }

    public function bulkCreate(Request $request)
    {
        $countries = Country::active()->orderBy('name')->get();
        $selectedCountryId = $request->input('country_id');

        return view('admin.customs-code-rate-overrides.bulk', compact('countries', 'selectedCountryId'));
    }

    public function bulkStore(Request $request, DutyPolicyResolver $resolver)
    {
        $validated = $request->validate([
            'country_id' => 'required|exists:countries,id',
            'mode' => 'required|in:codes,chapter',
            'codes' => 'required_if:mode,codes|nullable|string',
            'chapter' => 'required_if:mode,chapter|nullable|string|max:4',
            'override_rate' => 'required|numeric|min:0|max:1000',
            'effective_from' => 'required|date',
            'effective_until' => 'nullable|date|after_or_equal:effective_from',
            'source' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        $codeQuery = CustomsCode::query();

        if ($validated['mode'] === 'codes') {
            $rawCodes = preg_split('/[\s,]+/', (string) $validated['codes'], -1, PREG_SPLIT_NO_EMPTY);
            $normalized = array_unique(array_map(fn ($c) => trim($c), $rawCodes));

            $codeQuery->where(function ($q) use ($normalized) {
                foreach ($normalized as $code) {
                    $q->orWhere('code', $code);
                    $digits = preg_replace('/\D/', '', $code);
                    if ($digits !== '' && $digits !== $code) {
                        $q->orWhere('code', $digits);
                    }
                }
            });
        } else {
            $chapter = preg_replace('/\D/', '', (string) $validated['chapter']);
            if (strlen($chapter) < 2) {
                return back()->withErrors(['chapter' => 'Chapter must contain at least 2 digits.'])->withInput();
            }
            $codeQuery->where('code', 'LIKE', $chapter . '%');
        }

        $matched = $codeQuery->get();

        if ($matched->isEmpty()) {
            return back()->withErrors(['codes' => 'No customs codes matched the input.'])->withInput();
        }

        $created = 0;
        $skipped = 0;
        foreach ($matched as $customsCode) {
            $exists = CustomsCodeRateOverride::where('country_id', $validated['country_id'])
                ->where('customs_code_id', $customsCode->id)
                ->where('effective_from', $validated['effective_from'])
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            CustomsCodeRateOverride::create([
                'country_id' => $validated['country_id'],
                'customs_code_id' => $customsCode->id,
                'override_rate' => $validated['override_rate'],
                'effective_from' => $validated['effective_from'],
                'effective_until' => $validated['effective_until'] ?? null,
                'source' => $validated['source'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'is_active' => true,
            ]);
            $created++;
        }

        $resolver->flush();

        $msg = "Bulk override applied: {$created} created";
        if ($skipped > 0) {
            $msg .= ", {$skipped} skipped (duplicate effective_from)";
        }
        $msg .= ".";

        return redirect()
            ->route('admin.customs-code-rate-overrides.index', ['country_id' => $validated['country_id']])
            ->with('success', $msg);
    }

    /**
     * AJAX endpoint: search customs codes for the typeahead in create/edit.
     */
    public function searchCustomsCodes(Request $request)
    {
        $term = trim((string) $request->input('q', ''));
        if (strlen($term) < 2) {
            return response()->json([]);
        }

        $codes = CustomsCode::where('code', 'LIKE', $term . '%')
            ->orWhere('description', 'LIKE', '%' . $term . '%')
            ->orderBy('code')
            ->limit(30)
            ->get(['id', 'code', 'description', 'duty_rate']);

        return response()->json(
            $codes->map(fn ($c) => [
                'id' => $c->id,
                'code' => $c->code,
                'description' => $c->description,
                'duty_rate' => $c->duty_rate,
                'label' => $c->code . ' — ' . \Illuminate\Support\Str::limit($c->description ?? '', 80),
            ])
        );
    }
}
