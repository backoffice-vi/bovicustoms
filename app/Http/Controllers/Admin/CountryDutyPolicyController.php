<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\CountryDutyPolicy;
use App\Services\DutyPolicyResolver;
use Illuminate\Http\Request;

/**
 * Admin CRUD for country-level customs-duty calculation policies.
 *
 * A policy row says: "Between effective_from and effective_until, country X
 * computes Customs Duty on basis Y (FOB or CIF)." When no row matches a date,
 * the resolver falls back to CIF (CountryDutyPolicy::DEFAULT_BASIS).
 */
class CountryDutyPolicyController extends Controller
{
    public function index(Request $request)
    {
        $countryId = $request->input('country_id');

        $query = CountryDutyPolicy::with('country')
            ->orderBy('country_id')
            ->orderByDesc('effective_from');

        if ($countryId) {
            $query->where('country_id', $countryId);
        }

        $policies = $query->paginate(20)->appends($request->query());
        $countries = Country::active()->orderBy('name')->get();

        return view('admin.country-duty-policies.index', compact('policies', 'countries', 'countryId'));
    }

    public function create(Request $request)
    {
        $countries = Country::active()->orderBy('name')->get();
        $selectedCountryId = $request->input('country_id');
        $basisOptions = CountryDutyPolicy::getBasisOptions();

        return view('admin.country-duty-policies.create', compact('countries', 'selectedCountryId', 'basisOptions'));
    }

    public function store(Request $request, DutyPolicyResolver $resolver)
    {
        $validated = $this->validatePayload($request);

        CountryDutyPolicy::create($validated);
        $resolver->flush();

        return redirect()
            ->route('admin.country-duty-policies.index', ['country_id' => $validated['country_id']])
            ->with('success', 'Duty policy created.');
    }

    public function edit(CountryDutyPolicy $countryDutyPolicy)
    {
        $countries = Country::active()->orderBy('name')->get();
        $basisOptions = CountryDutyPolicy::getBasisOptions();

        return view('admin.country-duty-policies.edit', [
            'policy' => $countryDutyPolicy,
            'countries' => $countries,
            'basisOptions' => $basisOptions,
        ]);
    }

    public function update(Request $request, CountryDutyPolicy $countryDutyPolicy, DutyPolicyResolver $resolver)
    {
        $validated = $this->validatePayload($request);

        $countryDutyPolicy->update($validated);
        $resolver->flush();

        return redirect()
            ->route('admin.country-duty-policies.index', ['country_id' => $countryDutyPolicy->country_id])
            ->with('success', 'Duty policy updated.');
    }

    public function destroy(CountryDutyPolicy $countryDutyPolicy, DutyPolicyResolver $resolver)
    {
        $countryId = $countryDutyPolicy->country_id;
        $countryDutyPolicy->delete();
        $resolver->flush();

        return redirect()
            ->route('admin.country-duty-policies.index', ['country_id' => $countryId])
            ->with('success', 'Duty policy deleted.');
    }

    protected function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'country_id' => 'required|exists:countries,id',
            'basis' => 'required|in:fob,cif',
            'effective_from' => 'required|date',
            'effective_until' => 'nullable|date|after_or_equal:effective_from',
            'source' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        return $validated;
    }
}
