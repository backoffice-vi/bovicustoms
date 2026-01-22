<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\Request;

class CountryController extends Controller
{
    /**
     * Display a listing of countries
     */
    public function index()
    {
        $countries = Country::orderBy('name')->paginate(20);
        return view('admin.countries.index', compact('countries'));
    }

    /**
     * Show the form for creating a new country
     */
    public function create()
    {
        return view('admin.countries.create');
    }

    /**
     * Store a newly created country
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:3|unique:countries',
            'name' => 'required|string|max:255',
            'currency_code' => 'required|string|max:3',
            'flag_emoji' => 'nullable|string|max:10',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');

        Country::create($validated);

        return redirect()->route('admin.countries.index')
            ->with('success', 'Country created successfully');
    }

    /**
     * Show the form for editing a country
     */
    public function edit(Country $country)
    {
        return view('admin.countries.edit', compact('country'));
    }

    /**
     * Update the specified country
     */
    public function update(Request $request, Country $country)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:3|unique:countries,code,' . $country->id,
            'name' => 'required|string|max:255',
            'currency_code' => 'required|string|max:3',
            'flag_emoji' => 'nullable|string|max:10',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');

        $country->update($validated);

        return redirect()->route('admin.countries.index')
            ->with('success', 'Country updated successfully');
    }

    /**
     * Remove the specified country
     */
    public function destroy(Country $country)
    {
        $country->delete();

        return redirect()->route('admin.countries.index')
            ->with('success', 'Country deleted successfully');
    }
}
