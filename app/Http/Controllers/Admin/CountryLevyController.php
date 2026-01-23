<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CountryLevy;
use App\Models\Country;
use Illuminate\Http\Request;

class CountryLevyController extends Controller
{
    /**
     * Display a listing of country levies
     */
    public function index(Request $request)
    {
        $countryId = $request->input('country_id');
        
        $query = CountryLevy::with('country')->orderBy('country_id')->orderBy('display_order');
        
        if ($countryId) {
            $query->where('country_id', $countryId);
        }
        
        $levies = $query->paginate(20);
        $countries = Country::active()->orderBy('name')->get();
        
        return view('admin.country-levies.index', compact('levies', 'countries', 'countryId'));
    }

    /**
     * Show the form for creating a new levy
     */
    public function create(Request $request)
    {
        $countries = Country::active()->orderBy('name')->get();
        $selectedCountryId = $request->input('country_id');
        
        return view('admin.country-levies.create', compact('countries', 'selectedCountryId'));
    }

    /**
     * Store a newly created levy
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'country_id' => 'required|exists:countries,id',
            'levy_code' => 'required|string|max:20',
            'levy_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'rate' => 'required|numeric|min:0',
            'rate_type' => 'required|in:percentage,fixed_amount,per_unit',
            'unit' => 'nullable|string|max:50',
            'calculation_basis' => 'required|in:fob,cif,duty,quantity,weight',
            'applies_to_all_tariffs' => 'boolean',
            'applicable_tariff_chapters' => 'nullable|string',
            'exempt_tariff_codes' => 'nullable|string',
            'exempt_organization_types' => 'nullable|string',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'legal_reference' => 'nullable|string|max:255',
            'effective_from' => 'nullable|date',
            'effective_until' => 'nullable|date|after_or_equal:effective_from',
        ]);

        // Parse JSON arrays from comma-separated strings
        $validated['applies_to_all_tariffs'] = $request->boolean('applies_to_all_tariffs', true);
        $validated['is_active'] = $request->boolean('is_active', true);
        
        if (!empty($validated['applicable_tariff_chapters'])) {
            $validated['applicable_tariff_chapters'] = array_map('trim', explode(',', $validated['applicable_tariff_chapters']));
        }
        
        if (!empty($validated['exempt_tariff_codes'])) {
            $validated['exempt_tariff_codes'] = array_map('trim', explode(',', $validated['exempt_tariff_codes']));
        }
        
        if (!empty($validated['exempt_organization_types'])) {
            $validated['exempt_organization_types'] = array_map('trim', explode(',', $validated['exempt_organization_types']));
        }

        CountryLevy::create($validated);

        return redirect()->route('admin.country-levies.index', ['country_id' => $validated['country_id']])
            ->with('success', 'Levy created successfully.');
    }

    /**
     * Show the form for editing a levy
     */
    public function edit(CountryLevy $countryLevy)
    {
        $countries = Country::active()->orderBy('name')->get();
        
        return view('admin.country-levies.edit', compact('countryLevy', 'countries'));
    }

    /**
     * Update the specified levy
     */
    public function update(Request $request, CountryLevy $countryLevy)
    {
        $validated = $request->validate([
            'country_id' => 'required|exists:countries,id',
            'levy_code' => 'required|string|max:20',
            'levy_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'rate' => 'required|numeric|min:0',
            'rate_type' => 'required|in:percentage,fixed_amount,per_unit',
            'unit' => 'nullable|string|max:50',
            'calculation_basis' => 'required|in:fob,cif,duty,quantity,weight',
            'applies_to_all_tariffs' => 'boolean',
            'applicable_tariff_chapters' => 'nullable|string',
            'exempt_tariff_codes' => 'nullable|string',
            'exempt_organization_types' => 'nullable|string',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'legal_reference' => 'nullable|string|max:255',
            'effective_from' => 'nullable|date',
            'effective_until' => 'nullable|date|after_or_equal:effective_from',
        ]);

        // Parse arrays
        $validated['applies_to_all_tariffs'] = $request->boolean('applies_to_all_tariffs', true);
        $validated['is_active'] = $request->boolean('is_active', true);
        
        if (!empty($validated['applicable_tariff_chapters'])) {
            $validated['applicable_tariff_chapters'] = array_map('trim', explode(',', $validated['applicable_tariff_chapters']));
        } else {
            $validated['applicable_tariff_chapters'] = null;
        }
        
        if (!empty($validated['exempt_tariff_codes'])) {
            $validated['exempt_tariff_codes'] = array_map('trim', explode(',', $validated['exempt_tariff_codes']));
        } else {
            $validated['exempt_tariff_codes'] = null;
        }
        
        if (!empty($validated['exempt_organization_types'])) {
            $validated['exempt_organization_types'] = array_map('trim', explode(',', $validated['exempt_organization_types']));
        } else {
            $validated['exempt_organization_types'] = null;
        }

        $countryLevy->update($validated);

        return redirect()->route('admin.country-levies.index', ['country_id' => $countryLevy->country_id])
            ->with('success', 'Levy updated successfully.');
    }

    /**
     * Remove the specified levy
     */
    public function destroy(CountryLevy $countryLevy)
    {
        $countryId = $countryLevy->country_id;
        $countryLevy->delete();

        return redirect()->route('admin.country-levies.index', ['country_id' => $countryId])
            ->with('success', 'Levy deleted successfully.');
    }
}
